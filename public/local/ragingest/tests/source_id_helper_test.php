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
 * Unit tests for the source_id_helper class.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ragingest\source_id_helper
 */
final class source_id_helper_test extends \advanced_testcase {
    /**
     * Test building source IDs from raw course/cmid values with default tenant.
     */
    public function test_build_from_ids_default_tenant(): void {
        $this->resetAfterTest();

        // No tenant configured — should use 'default'.
        set_config('tenant_id', '', 'local_ragingest');

        $sourceid = source_id_helper::build_from_ids(42, 99);
        $this->assertEquals('default:course42:cmid99', $sourceid);
    }

    /**
     * Test building source IDs with a configured tenant.
     */
    public function test_build_from_ids_custom_tenant(): void {
        $this->resetAfterTest();

        set_config('tenant_id', 'uni-heidelberg', 'local_ragingest');

        $sourceid = source_id_helper::build_from_ids(123, 456);
        $this->assertEquals('uni-heidelberg:course123:cmid456', $sourceid);
    }

    /**
     * Test that source IDs are deterministic — calling twice yields the same result.
     */
    public function test_build_from_ids_is_deterministic(): void {
        $this->resetAfterTest();

        set_config('tenant_id', 'test', 'local_ragingest');

        $first = source_id_helper::build_from_ids(10, 20);
        $second = source_id_helper::build_from_ids(10, 20);
        $this->assertSame($first, $second);
    }

    /**
     * Test building source IDs from a cm_info object.
     */
    public function test_build_from_cm_info(): void {
        $this->resetAfterTest();

        set_config('tenant_id', 'eledia', 'local_ragingest');

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Test</p>',
        ]);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($page->cmid);

        $sourceid = source_id_helper::build($cm);

        $expected = "eledia:course{$course->id}:cmid{$page->cmid}";
        $this->assertEquals($expected, $sourceid);
    }

    /**
     * Test that sub-document IDs append a sanitised, separator-free suffix to
     * the module-level id (so the module id stays a clean prefix).
     */
    public function test_build_sub(): void {
        $this->resetAfterTest();
        set_config('tenant_id', 'eledia', 'local_ragingest');

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page',
            ['course' => $course->id, 'content' => '<p>x</p>']);
        $cm = get_fast_modinfo($course->id)->get_cm($page->cmid);

        $base = source_id_helper::build($cm);
        $sub = source_id_helper::build_sub($cm, 'file3');

        $this->assertSame($base . ':file3', $sub);
        // The module id is a clean, ':'-bounded prefix of the sub id.
        $this->assertStringStartsWith($base . ':', $sub);
    }

    /**
     * Test suffix sanitisation removes the ':' separator and other unsafe
     * characters so the prefix boundary can never be broken.
     */
    public function test_sanitise_suffix(): void {
        $this->assertSame('file-3', source_id_helper::sanitise_suffix('file:3'));
        $this->assertSame('a-b-c', source_id_helper::sanitise_suffix('a/b c'));
        $this->assertSame('lecture-pdf', source_id_helper::sanitise_suffix('Lecture.pdf'));
        // Empty / all-unsafe input falls back to a safe token.
        $this->assertSame('x', source_id_helper::sanitise_suffix(':::'));
        $this->assertSame('x', source_id_helper::sanitise_suffix(''));
    }
}
