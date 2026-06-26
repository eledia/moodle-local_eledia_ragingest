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
            'context' => \core\context\module::instance($page->cmid),
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
            'context' => \core\context\course::instance($course->id),
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

    /**
     * Test that updating a book chapter queues an ingestion task for the book.
     */
    public function test_book_chapter_updated_queues_task(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $book = $this->getDataGenerator()->create_module('book', [
            'course' => $course->id,
        ]);

        // Create a chapter via the generator.
        $bookgenerator = $this->getDataGenerator()->get_plugin_generator('mod_book');
        $chapter = $bookgenerator->create_chapter([
            'bookid' => $book->id,
            'title' => 'Test Chapter',
            'content' => '<p>Original content</p>',
        ]);

        // Clear any tasks from creation.
        $DB->delete_records('task_adhoc', [
            'classname' => '\\local_ragingest\\task\\ingest_module_task',
        ]);

        // Trigger chapter_updated event.
        $context = \core\context\module::instance($book->cmid);
        $bookrecord = $DB->get_record('book', ['id' => $book->id]);
        $event = \mod_book\event\chapter_updated::create_from_chapter($bookrecord, $context, $chapter);
        $event->trigger();

        $count = $DB->count_records('task_adhoc', [
            'classname' => '\\local_ragingest\\task\\ingest_module_task',
        ]);

        $this->assertGreaterThanOrEqual(
            1,
            $count,
            'An ingestion ad-hoc task should have been queued when a book chapter is updated.'
        );

        // Verify the task has the book's cmid, not the chapter id.
        $tasks = $DB->get_records('task_adhoc', [
            'classname' => '\\local_ragingest\\task\\ingest_module_task',
        ]);
        $task = reset($tasks);
        $data = json_decode($task->customdata);
        $this->assertEquals($book->cmid, $data->cmid);
    }

    /**
     * Test that creating a book chapter queues an ingestion task.
     */
    public function test_book_chapter_created_queues_task(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $book = $this->getDataGenerator()->create_module('book', [
            'course' => $course->id,
        ]);

        // Clear any tasks from book creation.
        $DB->delete_records('task_adhoc', [
            'classname' => '\\local_ragingest\\task\\ingest_module_task',
        ]);

        // Trigger chapter_created event.
        $context = \core\context\module::instance($book->cmid);
        $bookrecord = $DB->get_record('book', ['id' => $book->id]);
        $chapter = new \stdClass();
        $chapter->id = 999;
        $chapter->bookid = $book->id;
        $chapter->title = 'New Chapter';

        $event = \mod_book\event\chapter_created::create_from_chapter($bookrecord, $context, $chapter);
        $event->trigger();

        $count = $DB->count_records('task_adhoc', [
            'classname' => '\\local_ragingest\\task\\ingest_module_task',
        ]);

        $this->assertGreaterThanOrEqual(
            1,
            $count,
            'An ingestion ad-hoc task should have been queued when a book chapter is created.'
        );
    }

    /**
     * Test that updating a glossary entry queues an ingestion task for the glossary.
     */
    public function test_glossary_entry_updated_queues_task(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $glossary = $this->getDataGenerator()->create_module('glossary', [
            'course' => $course->id,
        ]);

        // Clear any tasks from creation.
        $DB->delete_records('task_adhoc', [
            'classname' => '\\local_ragingest\\task\\ingest_module_task',
        ]);

        // Trigger entry_updated event.
        $context = \core\context\module::instance($glossary->cmid);
        $event = \mod_glossary\event\entry_updated::create([
            'context' => $context,
            'objectid' => 123,
            'other' => ['concept' => 'Test Term'],
        ]);
        $event->trigger();

        $count = $DB->count_records('task_adhoc', [
            'classname' => '\\local_ragingest\\task\\ingest_module_task',
        ]);

        $this->assertGreaterThanOrEqual(
            1,
            $count,
            'An ingestion ad-hoc task should have been queued when a glossary entry is updated.'
        );

        // Verify the task has the glossary's cmid.
        $tasks = $DB->get_records('task_adhoc', [
            'classname' => '\\local_ragingest\\task\\ingest_module_task',
        ]);
        $task = reset($tasks);
        $data = json_decode($task->customdata);
        $this->assertEquals($glossary->cmid, $data->cmid);
    }

    /**
     * Test that creating a database record queues a re-ingest for the activity.
     */
    public function test_data_record_created_queues_task(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $data = $this->getDataGenerator()->create_module('data', ['course' => $course->id]);

        $DB->delete_records('task_adhoc', [
            'classname' => '\\local_ragingest\\task\\ingest_module_task',
        ]);

        // record_created fires in the module context.
        $recordid = $DB->insert_record('data_records', (object) [
            'dataid' => $data->id, 'userid' => $USER->id,
            'timecreated' => time(), 'timemodified' => time(), 'approved' => 1,
        ]);
        \mod_data\event\record_created::create([
            'objectid' => $recordid,
            'context' => \core\context\module::instance($data->cmid),
            'courseid' => $course->id,
            'other' => ['dataid' => $data->id],
        ])->trigger();

        $tasks = $DB->get_records('task_adhoc', [
            'classname' => '\\local_ragingest\\task\\ingest_module_task',
        ]);
        $this->assertGreaterThanOrEqual(1, count($tasks));
        $this->assertEquals($data->cmid, json_decode(reset($tasks)->customdata)->cmid);
    }

    /**
     * Test that adding a question to a quiz (slot_created) re-ingests the quiz.
     */
    public function test_quiz_slot_created_queues_task(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        $DB->delete_records('task_adhoc', [
            'classname' => '\\local_ragingest\\task\\ingest_module_task',
        ]);

        \mod_quiz\event\slot_created::create([
            'objectid' => 1,
            'context' => \core\context\module::instance($quiz->cmid),
            'courseid' => $course->id,
            'other' => ['quizid' => $quiz->id, 'slotnumber' => 1, 'page' => 1],
        ])->trigger();

        $tasks = $DB->get_records('task_adhoc', [
            'classname' => '\\local_ragingest\\task\\ingest_module_task',
        ]);
        $this->assertGreaterThanOrEqual(1, count($tasks));
        $this->assertEquals($quiz->cmid, json_decode(reset($tasks)->customdata)->cmid);
    }

    /**
     * Test that editing a question re-ingests every quiz that references it.
     */
    public function test_question_updated_reingest_referencing_quiz(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        // Build a question in the quiz's context and add it to the quiz.
        $qgen = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $qgen->create_question_category([
            'contextid' => \core\context\module::instance($quiz->cmid)->id,
        ]);
        $question = $qgen->create_question('truefalse', null, ['category' => $cat->id]);
        quiz_add_quiz_question($question->id, $quiz);

        $DB->delete_records('task_adhoc', [
            'classname' => '\\local_ragingest\\task\\ingest_module_task',
        ]);

        // Editing the question fires question_updated; the bank entry maps back
        // to the quiz slot that references it.
        \core\event\question_updated::create_from_question_instance(
            \question_bank::load_question_data($question->id),
            \core\context\module::instance($quiz->cmid),
        )->trigger();

        $tasks = $DB->get_records('task_adhoc', [
            'classname' => '\\local_ragingest\\task\\ingest_module_task',
        ]);
        $cmids = array_map(static fn($t) => json_decode($t->customdata)->cmid, $tasks);
        $this->assertContains((int) $quiz->cmid, array_map('intval', $cmids),
            'Editing a question should re-ingest the quiz that references it.');
    }
}
