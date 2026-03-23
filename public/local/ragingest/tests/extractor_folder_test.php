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
 * Unit tests for the folder content extractor.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \ragingestextractor_folder\extractor
 */
final class extractor_folder_test extends \advanced_testcase {
    /**
     * Test that the folder extractor supports folder modules.
     */
    public function test_supports_folder(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $folder = $this->getDataGenerator()->create_module('folder', [
            'course' => $course->id,
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($folder->cmid);

        $extractor = new \ragingestextractor_folder\extractor();
        $this->assertTrue($extractor->supports($cm));
    }

    /**
     * Test that the folder extractor does not support page modules.
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

        $extractor = new \ragingestextractor_folder\extractor();
        $this->assertFalse($extractor->supports($cm));
    }

    /**
     * Test extracting a single text file from a folder.
     */
    public function test_extract_single_text_file(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $folder = $this->getDataGenerator()->create_module('folder', [
            'course' => $course->id,
            'name' => 'Course Resources',
        ]);

        $this->add_file_to_folder($folder, 'readme.txt', 'Important course information.', 'text/plain');

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($folder->cmid);

        $extractor = new \ragingestextractor_folder\extractor();
        $result = $extractor->extract($cm);

        $this->assertNotNull($result);
        // Single file without intro — native MIME type preserved.
        $this->assertEquals('text/plain', $result['content_type']);
        $this->assertEquals('Important course information.', $result['content']);
        $this->assertEquals('readme.txt', $result['title']);
    }

    /**
     * Test extracting multiple files wraps them in HTML.
     */
    public function test_extract_multiple_files_as_html(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $folder = $this->getDataGenerator()->create_module('folder', [
            'course' => $course->id,
            'name' => 'Multi File Folder',
        ]);

        $this->add_file_to_folder($folder, 'notes.txt', 'First file content.', 'text/plain');
        $this->add_file_to_folder($folder, 'guide.html', '<p>Second file guide.</p>', 'text/html');

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($folder->cmid);

        $extractor = new \ragingestextractor_folder\extractor();
        $result = $extractor->extract($cm);

        $this->assertNotNull($result);
        $this->assertEquals('text/html', $result['content_type']);
        $this->assertEquals('Multi File Folder', $result['title']);
        $this->assertStringContainsString('First file content.', $result['content']);
        $this->assertStringContainsString('Second file guide.', $result['content']);
        $this->assertStringContainsString('<h2>notes.txt</h2>', $result['content']);
        $this->assertStringContainsString('<h2>guide.html</h2>', $result['content']);
    }

    /**
     * Test that unsupported file types are skipped.
     */
    public function test_extract_skips_unsupported_files(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $folder = $this->getDataGenerator()->create_module('folder', [
            'course' => $course->id,
            'name' => 'Image Folder',
        ]);

        $this->add_file_to_folder($folder, 'photo.png', 'fakepngdata', 'image/png');

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($folder->cmid);

        $extractor = new \ragingestextractor_folder\extractor();
        $result = $extractor->extract($cm);

        $this->assertNull($result);
    }

    /**
     * Test that extraction returns null for an empty folder.
     */
    public function test_extract_returns_null_for_empty_folder(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $folder = $this->getDataGenerator()->create_module('folder', [
            'course' => $course->id,
            'intro' => '',
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($folder->cmid);

        $extractor = new \ragingestextractor_folder\extractor();
        $result = $extractor->extract($cm);

        $this->assertNull($result);
    }

    /**
     * Helper to add a file to a folder module's content area.
     *
     * @param \stdClass $folder The folder module record.
     * @param string $filename The filename.
     * @param string $content The file content.
     * @param string $mimetype The MIME type.
     */
    private function add_file_to_folder(
        \stdClass $folder,
        string $filename,
        string $content,
        string $mimetype,
    ): void {
        $context = \context_module::instance($folder->cmid);
        $fs = get_file_storage();

        $filerecord = [
            'contextid' => $context->id,
            'component' => 'mod_folder',
            'filearea' => 'content',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
            'mimetype' => $mimetype,
        ];
        $fs->create_file_from_string($filerecord, $content);
    }
}
