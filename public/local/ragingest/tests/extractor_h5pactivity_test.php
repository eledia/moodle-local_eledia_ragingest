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
 * Unit tests for the h5pactivity content extractor.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \ragingestextractor_h5pactivity\extractor
 */
final class extractor_h5pactivity_test extends \advanced_testcase {
    /**
     * Test that the extractor supports h5pactivity modules.
     */
    public function test_supports_h5pactivity(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $h5p = $this->getDataGenerator()->create_module('h5pactivity', [
            'course' => $course->id,
            'name' => 'Test H5P Activity',
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($h5p->cmid);

        $extractor = new \ragingestextractor_h5pactivity\extractor();
        $this->assertTrue($extractor->supports($cm));
    }

    /**
     * Test that the extractor does not support other module types.
     */
    public function test_does_not_support_page(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Not H5P</p>',
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($page->cmid);

        $extractor = new \ragingestextractor_h5pactivity\extractor();
        $this->assertFalse($extractor->supports($cm));
    }

    /**
     * Test extraction returns null when H5P is not yet deployed.
     */
    public function test_extract_returns_null_when_not_deployed(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $h5p = $this->getDataGenerator()->create_module('h5pactivity', [
            'course' => $course->id,
            'name' => 'Undeployed H5P',
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($h5p->cmid);

        $extractor = new \ragingestextractor_h5pactivity\extractor();
        $result = $extractor->extract($cm);

        // Not deployed → should return null.
        $this->assertNull($result);
    }

    /**
     * Test extraction from a deployed H5P activity with content.
     */
    public function test_extract_returns_content_from_deployed_h5p(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $h5pactivity = $this->getDataGenerator()->create_module('h5pactivity', [
            'course' => $course->id,
            'name' => 'My Quiz Activity',
        ]);

        // Get the .h5p package file that the generator created.
        $context = \context_module::instance($h5pactivity->cmid);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_h5pactivity', 'package', 0, 'id', false);
        $file = reset($files);
        $this->assertNotEmpty($file, 'H5P package file should exist.');

        // Simulate deployment: insert an h5p record linked to this file.
        $jsoncontent = json_encode([
            'params' => [
                'question' => '<p>What is the chemical symbol for water?</p>',
                'answers' => [
                    ['text' => '<p>H2O is the correct chemical formula</p>', 'correct' => true],
                    ['text' => '<p>CO2 is carbon dioxide not water</p>', 'correct' => false],
                ],
            ],
        ]);

        $h5precord = new \stdClass();
        $h5precord->jsoncontent = $jsoncontent;
        $h5precord->mainlibraryid = 1;
        $h5precord->displayoptions = 0;
        $h5precord->pathnamehash = $file->get_pathnamehash();
        $h5precord->contenthash = $file->get_contenthash();
        $h5precord->filtered = '';
        $h5precord->timecreated = time();
        $h5precord->timemodified = time();
        $DB->insert_record('h5p', $h5precord);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($h5pactivity->cmid);

        $extractor = new \ragingestextractor_h5pactivity\extractor();
        $result = $extractor->extract($cm);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('content_type', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertEquals('text/plain', $result['content_type']);
        $this->assertEquals('My Quiz Activity', $result['title']);
        $this->assertStringContainsString('What is the chemical symbol for water?', $result['content']);
        $this->assertStringContainsString('H2O is the correct chemical formula', $result['content']);
        $this->assertStringContainsString('CO2 is carbon dioxide not water', $result['content']);
        // HTML tags must be stripped.
        $this->assertStringNotContainsString('<p>', $result['content']);
    }

    /**
     * Test extraction returns null when jsoncontent yields no usable text.
     */
    public function test_extract_returns_null_for_empty_jsoncontent(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $h5pactivity = $this->getDataGenerator()->create_module('h5pactivity', [
            'course' => $course->id,
            'name' => 'Empty H5P',
        ]);

        $context = \context_module::instance($h5pactivity->cmid);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_h5pactivity', 'package', 0, 'id', false);
        $file = reset($files);

        // Deploy with essentially empty content.
        $h5precord = new \stdClass();
        $h5precord->jsoncontent = json_encode(['params' => ['id' => '123']]);
        $h5precord->mainlibraryid = 1;
        $h5precord->displayoptions = 0;
        $h5precord->pathnamehash = $file->get_pathnamehash();
        $h5precord->contenthash = $file->get_contenthash();
        $h5precord->filtered = '';
        $h5precord->timecreated = time();
        $h5precord->timemodified = time();
        $DB->insert_record('h5p', $h5precord);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($h5pactivity->cmid);

        $extractor = new \ragingestextractor_h5pactivity\extractor();
        $result = $extractor->extract($cm);

        $this->assertNull($result);
    }
}
