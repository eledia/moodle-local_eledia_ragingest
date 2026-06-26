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
 * Tracks per-course ingestion state and reconciles it with the marking.
 *
 * The marking ({@see course_gate::should_ingest()}) expresses the *desired*
 * state; this class records the *actual* state (is the course in the index?)
 * and acts only on the transitions:
 *
 * - not-ingested → should-ingest  ⇒  re-index the whole course;
 * - ingested → should-not-ingest  ⇒  purge the course from the index.
 *
 * Acting on transitions (rather than on every course edit) avoids needlessly
 * re-embedding an enabled course whenever its settings are saved, and ensures
 * that *un-marking* a course actually removes its content — the revocation half
 * of an opt-in policy.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_state {
    /** @var string Backing table. */
    private const TABLE = 'local_ragingest_course';

    /**
     * Whether the course is currently recorded as present in the index.
     *
     * @param int $courseid The course id.
     * @return bool
     */
    public static function is_ingested(int $courseid): bool {
        global $DB;
        return (int) $DB->get_field(self::TABLE, 'ingested', ['courseid' => $courseid]) === 1;
    }

    /**
     * Record the course's current index state.
     *
     * @param int $courseid The course id.
     * @param bool $ingested Whether it is now in the index.
     * @return void
     */
    public static function set_ingested(int $courseid, bool $ingested): void {
        global $DB;

        $value = $ingested ? 1 : 0;
        try {
            $existing = $DB->get_record(self::TABLE, ['courseid' => $courseid]);
            if ($existing) {
                $existing->ingested = $value;
                $existing->timemodified = time();
                $DB->update_record(self::TABLE, $existing);
                return;
            }
            $DB->insert_record(self::TABLE, (object) [
                'courseid' => $courseid,
                'ingested' => $value,
                'timemodified' => time(),
            ]);
            return;
        } catch (\dml_exception $e) {
            // A parallel cron worker may have inserted the row after our read.
            $DB->set_field(self::TABLE, 'ingested', $value, ['courseid' => $courseid]);
            $DB->set_field(self::TABLE, 'timemodified', time(), ['courseid' => $courseid]);
        }
    }

    /**
     * Reconcile one course: act on the difference between marking and state.
     *
     * @param int $courseid The course id.
     * @param ingestion_manager|null $manager Optional injected manager (tests).
     * @return string One of 'reindexed', 'purged', 'noop'.
     */
    public static function reconcile(int $courseid, ?ingestion_manager $manager = null): string {
        $desired = course_gate::should_ingest($courseid);
        $current = self::is_ingested($courseid);

        if ($desired === $current) {
            return 'noop';
        }

        $manager ??= new ingestion_manager();

        if ($desired) {
            $results = $manager->reindex_course($courseid);
            if (!self::has_error_result($results)) {
                self::set_ingested($courseid, true);
            }
            return 'reindexed';
        }

        $manager->purge_course($courseid);
        self::set_ingested($courseid, false);
        return 'purged';
    }

    /**
     * Whether a result list contains an ingestion/deletion error.
     *
     * Skipped modules are not errors: an empty or unsupported course can still
     * be considered reconciled, otherwise it would be queued forever.
     *
     * @param array<int, array> $results Result rows from ingestion_manager.
     * @return bool
     */
    private static function has_error_result(array $results): bool {
        foreach ($results as $result) {
            if (($result['status'] ?? '') === 'error') {
                return true;
            }
        }
        return false;
    }

    /**
     * Queue a reconcile task for every course whose marking and index state
     * diverge. Shared by the nightly task and the admin-setting callbacks, so
     * that changing a central list (pilot courses / category allow-list) takes
     * effect promptly rather than only at the next nightly run.
     *
     * @return int Number of reconcile tasks queued.
     */
    public static function queue_divergent_reconciles(): int {
        global $DB;

        if (!(new api_client())->is_configured()) {
            return 0;
        }

        $queued = 0;
        $courses = $DB->get_recordset_select('course', 'id <> :site', ['site' => SITEID], 'id', 'id');
        foreach ($courses as $course) {
            $courseid = (int) $course->id;
            if (course_gate::should_ingest($courseid) === self::is_ingested($courseid)) {
                continue;
            }
            $task = new task\reconcile_course_task();
            $task->set_custom_data(['courseid' => $courseid]);
            \core\task\manager::queue_adhoc_task($task, true);
            $queued++;
        }
        $courses->close();

        return $queued;
    }

    /**
     * Count courses that are allowed for ingestion but are not recorded as
     * indexed yet.
     *
     * @return int Number of courses waiting for their initial indexing.
     */
    public static function pending_ingestion_count(): int {
        global $DB;

        $pending = 0;
        $courses = $DB->get_recordset_select('course', 'id <> :site', ['site' => SITEID], 'id', 'id');
        foreach ($courses as $course) {
            $courseid = (int) $course->id;
            if (course_gate::should_ingest($courseid) && !self::is_ingested($courseid)) {
                $pending++;
            }
        }
        $courses->close();

        return $pending;
    }

    /**
     * Queue indexing for every course that is allowed but not indexed yet.
     *
     * Unlike {@see queue_divergent_reconciles()}, this intentionally does not
     * queue purge tasks. It backs the admin action "index released courses now".
     *
     * @return int Number of indexing tasks queued.
     */
    public static function queue_pending_ingestions(): int {
        global $DB;

        if (!(new api_client())->is_configured()) {
            return 0;
        }

        $queued = 0;
        $courses = $DB->get_recordset_select('course', 'id <> :site', ['site' => SITEID], 'id', 'id');
        foreach ($courses as $course) {
            $courseid = (int) $course->id;
            if (!course_gate::should_ingest($courseid) || self::is_ingested($courseid)) {
                continue;
            }
            $task = new task\reconcile_course_task();
            $task->set_custom_data(['courseid' => $courseid]);
            \core\task\manager::queue_adhoc_task($task, true);
            $queued++;
        }
        $courses->close();

        return $queued;
    }

    /**
     * Forget a course's state row (e.g. when the course is deleted).
     *
     * @param int $courseid The course id.
     * @return void
     */
    public static function forget(int $courseid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['courseid' => $courseid]);
    }
}
