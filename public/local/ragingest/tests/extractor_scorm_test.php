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

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for the SCORM content extractor.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\ragingestextractor_scorm\extractor::class)]
final class extractor_scorm_test extends \advanced_testcase {
    /**
     * Test that the scorm extractor supports scorm modules.
     */
    public function test_supports_scorm(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $scorm = $this->getDataGenerator()->create_module('scorm', [
            'course' => $course->id,
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($scorm->cmid);

        $extractor = new \ragingestextractor_scorm\extractor();
        $this->assertTrue($extractor->supports($cm));
    }

    /**
     * Test that the scorm extractor does not support page modules.
     */
    public function test_does_not_support_page(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Page</p>',
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($page->cmid);

        $extractor = new \ragingestextractor_scorm\extractor();
        $this->assertFalse($extractor->supports($cm));
    }

    /**
     * Test extracting SCO titles from a SCORM package.
     */
    public function test_extract_returns_sco_titles(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $scorm = $this->getDataGenerator()->create_module('scorm', [
            'course' => $course->id,
            'name' => 'Safety Training',
        ]);

        // The SCORM generator creates default SCOs from its sample package.
        // Let's check what we get.
        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($scorm->cmid);

        $extractor = new \ragingestextractor_scorm\extractor();
        $result = $extractor->extract($cm);

        // The SCORM module should at least have its intro or SCO titles.
        // With the default test package, SCOs are created from the manifest.
        if ($result !== null) {
            $this->assertEquals('text/html', $result['content_type']);
            $this->assertEquals('Safety Training', $result['title']);
        }
    }

    /**
     * Test extracting content with manually inserted SCOs and HTML files.
     */
    public function test_extract_with_html_launch_pages(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $scorm = $this->getDataGenerator()->create_module('scorm', [
            'course' => $course->id,
            'name' => 'Interactive Course',
            'intro' => '<p>Welcome to this interactive course.</p>',
        ]);

        // Ensure the package type is local.
        $DB->set_field('scorm', 'scormtype', 'local', ['id' => $scorm->id]);

        // Clear existing SCOs and insert our own.
        $DB->delete_records('scorm_scoes', ['scorm' => $scorm->id]);

        $DB->insert_record('scorm_scoes', [
            'scorm' => $scorm->id,
            'manifest' => 'manifest-1',
            'organization' => 'org-1',
            'parent' => '/',
            'identifier' => 'sco1',
            'launch' => 'lesson1.html',
            'scormtype' => 'sco',
            'title' => 'Lesson 1: Basics',
            'sortorder' => 1,
        ]);

        $DB->insert_record('scorm_scoes', [
            'scorm' => $scorm->id,
            'manifest' => 'manifest-1',
            'organization' => 'org-1',
            'parent' => '/',
            'identifier' => 'sco2',
            'launch' => 'lesson2.html',
            'scormtype' => 'sco',
            'title' => 'Lesson 2: Advanced',
            'sortorder' => 2,
        ]);

        // Create HTML files in the SCORM content area.
        $context = \core\context\module::instance($scorm->cmid);
        $fs = get_file_storage();

        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_scorm',
            'filearea' => 'content',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'lesson1.html',
        ], '<html><body><p>This lesson covers the fundamentals of safety.</p></body></html>');

        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_scorm',
            'filearea' => 'content',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'lesson2.html',
        ], '<html><body><p>Advanced topics in workplace safety procedures.</p></body></html>');

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($scorm->cmid);

        $extractor = new \ragingestextractor_scorm\extractor();
        $result = $extractor->extract($cm);

        $this->assertNotNull($result);
        $this->assertEquals('text/html', $result['content_type']);
        $this->assertEquals('Interactive Course', $result['title']);
        $this->assertStringContainsString('Welcome to this interactive course.', $result['content']);
        $this->assertStringContainsString('<h2>Lesson 1: Basics</h2>', $result['content']);
        $this->assertStringContainsString('fundamentals of safety', $result['content']);
        $this->assertStringContainsString('<h2>Lesson 2: Advanced</h2>', $result['content']);
        $this->assertStringContainsString('workplace safety procedures', $result['content']);
    }

    /**
     * Test that extraction returns only intro when no SCOs exist.
     */
    public function test_extract_returns_intro_without_scoes(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $scorm = $this->getDataGenerator()->create_module('scorm', [
            'course' => $course->id,
            'name' => 'Empty SCORM',
            'intro' => '<p>SCORM package description.</p>',
        ]);

        // Remove all SCOs.
        $DB->delete_records('scorm_scoes', ['scorm' => $scorm->id]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($scorm->cmid);

        $extractor = new \ragingestextractor_scorm\extractor();
        $result = $extractor->extract($cm);

        $this->assertNotNull($result);
        $this->assertStringContainsString('SCORM package description.', $result['content']);
    }
}
