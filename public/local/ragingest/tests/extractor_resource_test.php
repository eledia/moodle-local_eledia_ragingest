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
 * Unit tests for the resource content extractor.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \ragingestextractor_resource\extractor
 */
final class extractor_resource_test extends \advanced_testcase {
    /**
     * Test that the resource extractor supports resource modules.
     */
    public function test_supports_resource(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $resource = $this->create_resource_with_file($course, 'test.txt', 'Hello World', 'text/plain');

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($resource->cmid);

        $extractor = new extractor();
        $this->assertTrue($extractor->supports($cm));
    }

    /**
     * Test that the resource extractor does not support page modules.
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

        $extractor = new extractor();
        $this->assertFalse($extractor->supports($cm));
    }

    /**
     * Test extracting a text file from a resource activity.
     */
    public function test_extract_text_file(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $resource = $this->create_resource_with_file($course, 'notes.txt', 'Plain text content', 'text/plain');

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($resource->cmid);

        $extractor = new extractor();
        $result = $extractor->extract($cm);

        $this->assertNotNull($result);
        $this->assertEquals('text/plain', $result['content_type']);
        $this->assertEquals('Plain text content', $result['content']);
        $this->assertEquals('notes.txt', $result['title']);
    }

    /**
     * Test extracting an HTML file from a resource activity.
     */
    public function test_extract_html_file(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $htmlcontent = '<html><body><h1>Hello</h1></body></html>';
        $resource = $this->create_resource_with_file($course, 'page.html', $htmlcontent, 'text/html');

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($resource->cmid);

        $extractor = new extractor();
        $result = $extractor->extract($cm);

        $this->assertNotNull($result);
        $this->assertEquals('text/html', $result['content_type']);
        $this->assertStringContainsString('<h1>Hello</h1>', $result['content']);
    }

    /**
     * Test that unsupported MIME types (e.g., image/png) return null.
     */
    public function test_extract_returns_null_for_unsupported_mimetype(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $resource = $this->create_resource_with_file($course, 'image.png', 'fakepngdata', 'image/png');

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($resource->cmid);

        $extractor = new extractor();
        $result = $extractor->extract($cm);

        $this->assertNull($result);
    }

    /**
     * Helper to create a resource activity with a file attached.
     *
     * @param \stdClass $course The course object.
     * @param string $filename The filename.
     * @param string $content The file content.
     * @param string $mimetype The MIME type.
     * @return \stdClass The resource module record with cmid.
     */
    private function create_resource_with_file(
        \stdClass $course,
        string $filename,
        string $content,
        string $mimetype
    ): \stdClass {
        $resource = $this->getDataGenerator()->create_module('resource', [
            'course' => $course->id,
        ]);

        // Add a file to the resource's content file area.
        $context = \context_module::instance($resource->cmid);
        $fs = get_file_storage();

        // Remove any default files.
        $fs->delete_area_files($context->id, 'mod_resource', 'content');

        $filerecord = [
            'contextid' => $context->id,
            'component' => 'mod_resource',
            'filearea' => 'content',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
            'mimetype' => $mimetype,
        ];
        $fs->create_file_from_string($filerecord, $content);

        return $resource;
    }
}
