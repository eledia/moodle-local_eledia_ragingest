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
 * Event observer for course module lifecycle events.
 *
 * Queues ad-hoc tasks to ingest or delete content asynchronously via cron,
 * ensuring that module create/update/delete operations are never blocked
 * by RAG API calls.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Handle course module created event.
     *
     * Queues an ad-hoc ingestion task for the new module.
     *
     * @param \core\event\course_module_created $event The event.
     */
    public static function course_module_created(\core\event\course_module_created $event): void {
        self::queue_ingestion($event->courseid, $event->objectid);
    }

    /**
     * Handle course module updated event.
     *
     * Queues an ad-hoc ingestion task to re-ingest the updated module.
     *
     * @param \core\event\course_module_updated $event The event.
     */
    public static function course_module_updated(\core\event\course_module_updated $event): void {
        self::queue_ingestion($event->courseid, $event->objectid);
    }

    /**
     * Handle course module deleted event.
     *
     * Queues an ad-hoc deletion task to remove the module from the RAG index.
     *
     * @param \core\event\course_module_deleted $event The event.
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        $task = new task\delete_module_task();
        $task->set_custom_data([
            'courseid' => $event->courseid,
            'cmid' => $event->objectid,
        ]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Queue an ingestion task for a course module.
     *
     * @param int $courseid The course ID.
     * @param int $cmid The course module ID.
     */
    private static function queue_ingestion(int $courseid, int $cmid): void {
        $task = new task\ingest_module_task();
        $task->set_custom_data([
            'courseid' => $courseid,
            'cmid' => $cmid,
        ]);
        \core\task\manager::queue_adhoc_task($task, true);
    }
}
