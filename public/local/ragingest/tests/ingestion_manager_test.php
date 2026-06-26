<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_ragingest;

/**
 * Unit tests for the ingestion_manager class.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ragingest\ingestion_manager
 */
final class ingestion_manager_test extends \advanced_testcase {
    /**
     * Set up test configuration.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        // Configure the plugin so the api_client reports configured.
        set_config('rag_endpoint_url', 'http://localhost:8001/documents/upsert', 'local_ragingest');
        set_config('rag_api_key', 'test-key', 'local_ragingest');
        set_config('max_document_size_mb', '20', 'local_ragingest');

        // Mark the default course category for ingestion so generator courses
        // (created there) pass the opt-in gate.
        global $DB;
        $defaultcat = (int) $DB->get_field_select('course_categories', 'MIN(id)', 'parent = 0');
        set_config('enabledcategories', (string) $defaultcat, 'local_ragingest');
    }

    /**
     * Test reindex_course returns error when API is not configured.
     */
    public function test_reindex_course_not_configured(): void {
        set_config('rag_endpoint_url', '', 'local_ragingest');
        set_config('rag_api_key', '', 'local_ragingest');

        $course = $this->getDataGenerator()->create_course();

        $manager = new ingestion_manager();
        $results = $manager->reindex_course($course->id);

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]['success']);
        $this->assertEquals('error', $results[0]['status']);
        $this->assertStringContainsString('not configured', $results[0]['message']);
    }

    /**
     * Test reindex_course with a page activity and mocked API response.
     */
    public function test_reindex_course_with_page(): void {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Test Page',
            'content' => '<p>Hello World</p>',
        ]);

        // Mock the curl response for the upsert call.
        \curl::mock_response('{"status": "ok"}');

        $manager = new ingestion_manager();
        ob_start();
        $results = $manager->reindex_course($course->id);
        ob_end_clean();

        // Should have at least one result for the page.
        $pageresult = null;
        foreach ($results as $result) {
            if ($result['cmid'] == $page->cmid) {
                $pageresult = $result;
                break;
            }
        }

        $this->assertNotNull($pageresult, 'Page module result should be present.');
        $this->assertTrue($pageresult['success']);
        $this->assertEquals('success', $pageresult['status']);
    }

    /**
     * Test reindex_course skips modules without extractors.
     */
    public function test_reindex_course_skips_unsupported_module(): void {
        $course = $this->getDataGenerator()->create_course();
        // Create a forum — no extractor exists for it.
        $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id,
            'name' => 'Test Forum',
        ]);

        $manager = new ingestion_manager();
        $results = $manager->reindex_course($course->id);

        // Forum should be skipped.
        $forumresult = null;
        foreach ($results as $result) {
            if ($result['status'] === 'skipped') {
                $forumresult = $result;
                break;
            }
        }

        $this->assertNotNull($forumresult, 'Forum module should be skipped.');
        $this->assertFalse($forumresult['success']);
        $this->assertEquals('skipped', $forumresult['status']);
    }

    /**
     * Test ingest_module returns error when API is not configured.
     */
    public function test_ingest_module_not_configured(): void {
        set_config('rag_endpoint_url', '', 'local_ragingest');
        set_config('rag_api_key', '', 'local_ragingest');

        $manager = new ingestion_manager();
        $result = $manager->ingest_module(1, 1);

        $this->assertFalse($result['success']);
        $this->assertEquals('error', $result['status']);
    }

    /**
     * Test delete_module returns error when API is not configured.
     */
    public function test_delete_module_not_configured(): void {
        set_config('rag_endpoint_url', '', 'local_ragingest');
        set_config('rag_api_key', '', 'local_ragingest');

        $manager = new ingestion_manager();
        $result = $manager->delete_module(1, 1);

        $this->assertFalse($result['success']);
        $this->assertEquals('error', $result['status']);
    }

    /**
     * Test delete_module with a mocked successful API response.
     */
    public function test_delete_module_success(): void {
        \curl::mock_response('{"status": "ok"}');

        $manager = new ingestion_manager();
        ob_start();
        $result = $manager->delete_module(42, 99);
        ob_end_clean();

        $this->assertTrue($result['success']);
        $this->assertEquals('success', $result['status']);
    }

    /**
     * Test that a multi-document module (a folder with several files) sends one
     * upsert per file after a prefix-scoped clear of the previous set.
     */
    public function test_ingest_multidocument_folder(): void {
        global $DB;
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $folder = $this->getDataGenerator()->create_module('folder', [
            'course' => $course->id,
            'name' => 'Readings',
        ]);
        // Force-clear the generator's default intro so the document count is
        // exactly the two files below.
        $DB->set_field('folder', 'intro', '', ['id' => $folder->id]);

        $context = \core\context\module::instance($folder->cmid);
        $fs = get_file_storage();
        foreach (['a.txt' => 'First.', 'b.txt' => 'Second.'] as $name => $body) {
            $fs->create_file_from_string([
                'contextid' => $context->id, 'component' => 'mod_folder', 'filearea' => 'content',
                'itemid' => 0, 'filepath' => '/', 'filename' => $name, 'mimetype' => 'text/plain',
            ], $body);
        }

        // One prefix-delete + two upserts = three HTTP calls.
        \curl::mock_response('{"status": "ok"}');
        \curl::mock_response('{"status": "ok"}');
        \curl::mock_response('{"status": "ok"}');

        $manager = new ingestion_manager();
        ob_start();
        $result = $manager->ingest_module($course->id, $folder->cmid);
        ob_end_clean();

        $this->assertTrue($result['success']);
        $this->assertSame('success', $result['status']);
        $this->assertStringContainsString('2 document', $result['message']);
    }

    /**
     * Test that the ingestion manager enforces file size limits.
     */
    public function test_reindex_course_enforces_size_limit(): void {
        // Set a tiny size limit of 0.001 MB (1 KB) for testing.
        set_config('max_document_size_mb', '0.001', 'local_ragingest');

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Big Page',
            'content' => '<p>This content exceeds 1 KB limit</p>' . str_repeat('x', 2000),
        ]);

        $manager = new ingestion_manager();
        $results = $manager->reindex_course($course->id);

        // Find the page result — it should be skipped due to size.
        $pagefound = false;
        foreach ($results as $result) {
            if (str_contains($result['message'] ?? '', 'exceeds')) {
                $pagefound = true;
                $this->assertFalse($result['success']);
                $this->assertEquals('skipped', $result['status']);
            }
        }

        $this->assertTrue($pagefound, 'Page should have been skipped due to size limit.');
    }
}
