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
 * Unit tests for the event observer class.
 *
 * Verifies that course module lifecycle events correctly queue ad-hoc tasks.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ragingest\observer
 */
final class observer_test extends \advanced_testcase {
    /**
     * Test that creating a course module queues an ingestion task.
     */
    public function test_course_module_created_queues_task(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        // Count adhoc tasks before.
        $countbefore = $DB->count_records('task_adhoc', [
            'classname' => '\\local_ragingest\\task\\ingest_module_task',
        ]);

        // Create a page — this triggers course_module_created event.
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Test</p>',
        ]);

        $countafter = $DB->count_records('task_adhoc', [
            'classname' => '\\local_ragingest\\task\\ingest_module_task',
        ]);

        $this->assertGreaterThan(
            $countbefore,
            $countafter,
            'An ingestion ad-hoc task should have been queued.'
        );
    }

    /**
     * Test that updating a course module queues an ingestion task.
     */
    public function test_course_module_updated_queues_task(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Original</p>',
        ]);

        // Clear any tasks from creation.
        $DB->delete_records('task_adhoc', [
            'classname' => '\\local_ragingest\\task\\ingest_module_task',
        ]);

        // Trigger update event.
        $event = \core\event\course_module_updated::create([
            'objectid' => $page->cmid,
            'courseid' => $course->id,
            'context' => \context_module::instance($page->cmid),
            'other' => [
                'modulename' => 'page',
                'instanceid' => $page->id,
                'name' => 'Test Page',
            ],
        ]);
        $event->trigger();

        $count = $DB->count_records('task_adhoc', [
            'classname' => '\\local_ragingest\\task\\ingest_module_task',
        ]);

        $this->assertGreaterThanOrEqual(
            1,
            $count,
            'An ingestion ad-hoc task should have been queued on update.'
        );
    }

    /**
     * Test that deleting a course module queues a deletion task.
     */
    public function test_course_module_deleted_queues_deletion_task(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Will be deleted</p>',
        ]);

        // Trigger delete event.
        $event = \core\event\course_module_deleted::create([
            'objectid' => $page->cmid,
            'courseid' => $course->id,
            'context' => \context_course::instance($course->id),
            'other' => [
                'modulename' => 'page',
                'instanceid' => $page->id,
            ],
        ]);
        $event->trigger();

        $count = $DB->count_records('task_adhoc', [
            'classname' => '\\local_ragingest\\task\\delete_module_task',
        ]);

        $this->assertGreaterThanOrEqual(
            1,
            $count,
            'A deletion ad-hoc task should have been queued.'
        );
    }
}
