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
 * Unit tests for course ingestion-state reconciliation.
 *
 * Uses empty courses so reconcile makes no HTTP calls (an empty course has no
 * modules to upsert or delete), letting us assert the state-transition logic
 * directly.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ragingest\course_state
 */
final class course_state_test extends \advanced_testcase {
    /**
     * Configure the API so the manager is "configured".
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('rag_endpoint_url', 'http://localhost:8001/documents/upsert', 'local_ragingest');
        set_config('rag_api_key', 'k', 'local_ragingest');
    }

    /**
     * Enabling a previously-unindexed course re-indexes it and records state.
     */
    public function test_reconcile_enables(): void {
        $cat = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        set_config('enabledcategories', (string) $cat->id, 'local_ragingest');

        $this->assertFalse(course_state::is_ingested((int) $course->id));

        $action = course_state::reconcile((int) $course->id);

        $this->assertSame('reindexed', $action);
        $this->assertTrue(course_state::is_ingested((int) $course->id));
    }

    /**
     * Failed reindex attempts do not mark a course as indexed.
     */
    public function test_reconcile_does_not_mark_failed_reindex_as_ingested(): void {
        $cat = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        set_config('enabledcategories', (string) $cat->id, 'local_ragingest');

        $manager = new class ([[
            'cmid' => 17,
            'success' => false,
            'status' => 'error',
            'message' => 'transport failed',
        ]]) extends ingestion_manager {
            /** @var array<int, array> */
            private array $results;

            /**
             * Constructor.
             *
             * @param array<int, array> $results Result rows.
             */
            public function __construct(array $results) {
                $this->results = $results;
            }

            /**
             * Return injected reindex results.
             *
             * @param int $courseid Course id.
             * @return array<int, array>
             */
            public function reindex_course(int $courseid): array {
                return $this->results;
            }
        };

        $this->assertSame('reindexed', course_state::reconcile((int) $course->id, $manager));
        $this->assertFalse(course_state::is_ingested((int) $course->id));
    }

    /**
     * Un-marking an indexed course purges it and clears state.
     */
    public function test_reconcile_disables_and_purges(): void {
        $cat = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);

        // Pretend it is currently indexed.
        course_state::set_ingested((int) $course->id, true);
        // Not marked (no categories enabled) → desired = false.
        set_config('enabledcategories', '', 'local_ragingest');

        $action = course_state::reconcile((int) $course->id);

        $this->assertSame('purged', $action);
        $this->assertFalse(course_state::is_ingested((int) $course->id));
    }

    /**
     * No state change → no action.
     */
    public function test_reconcile_noop(): void {
        $course = $this->getDataGenerator()->create_course();
        // Desired false (opt-in default) and state false (default) → noop.
        $this->assertSame('noop', course_state::reconcile((int) $course->id));
    }

    /**
     * queue_divergent_reconciles() queues a task only where marking and state
     * diverge.
     */
    public function test_queue_divergent_reconciles(): void {
        global $DB;
        $cat = $this->getDataGenerator()->create_category();
        set_config('enabledcategories', (string) $cat->id, 'local_ragingest');

        // Marked but not yet ingested → diverges → should be queued.
        $marked = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        // Unmarked and not ingested → matches → not queued.
        $this->getDataGenerator()->create_course();

        $DB->delete_records(
            'task_adhoc',
            ['classname' => '\\local_ragingest\\task\\reconcile_course_task']
        );

        $queued = course_state::queue_divergent_reconciles();

        $this->assertSame(1, $queued);
        $tasks = $DB->get_records(
            'task_adhoc',
            ['classname' => '\\local_ragingest\\task\\reconcile_course_task']
        );
        $this->assertCount(1, $tasks);
        $this->assertEquals($marked->id, json_decode(reset($tasks)->customdata)->courseid);
    }

    /**
     * forget() removes the state row (e.g. on course deletion).
     */
    public function test_forget(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        course_state::set_ingested((int) $course->id, true);
        $this->assertTrue($DB->record_exists('local_ragingest_course', ['courseid' => $course->id]));

        course_state::forget((int) $course->id);
        $this->assertFalse($DB->record_exists('local_ragingest_course', ['courseid' => $course->id]));
    }
}
