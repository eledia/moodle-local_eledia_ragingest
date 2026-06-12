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
 * Unit tests for the opt-in course ingestion gate.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ragingest\course_gate
 * @covers     \local_ragingest\setup
 */
final class course_gate_test extends \advanced_testcase {
    /**
     * Opt-in: with no categories enabled and no override, nothing is ingested.
     */
    public function test_optin_default_excludes(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->assertFalse(course_gate::should_ingest((int) $course->id));
        // The site course is never ingestable.
        $this->assertFalse(course_gate::should_ingest(SITEID));
    }

    /**
     * A course is eligible when its category — or an ancestor — is allow-listed.
     */
    public function test_category_allow_list(): void {
        $this->resetAfterTest();

        $parent = $this->getDataGenerator()->create_category();
        $child = $this->getDataGenerator()->create_category(['parent' => $parent->id]);
        $other = $this->getDataGenerator()->create_category();

        $incourse = $this->getDataGenerator()->create_course(['category' => $child->id]);
        $outcourse = $this->getDataGenerator()->create_course(['category' => $other->id]);

        // Enable the PARENT only — the child course qualifies via its ancestor.
        set_config('enabledcategories', (string) $parent->id, 'local_ragingest');

        $this->assertTrue(course_gate::should_ingest((int) $incourse->id));
        $this->assertFalse(course_gate::should_ingest((int) $outcourse->id));
    }

    /**
     * The per-course override beats the category rule in both directions.
     */
    public function test_override_beats_category(): void {
        $this->resetAfterTest();
        setup::ensure_course_field();

        $enabledcat = $this->getDataGenerator()->create_category();
        $disabledcat = $this->getDataGenerator()->create_category();
        set_config('enabledcategories', (string) $enabledcat->id, 'local_ragingest');

        // In an enabled category but overridden to Exclude → not ingested.
        $excluded = $this->getDataGenerator()->create_course(['category' => $enabledcat->id]);
        $this->set_override((int) $excluded->id, course_gate::OVERRIDE_EXCLUDE);
        $this->assertFalse(course_gate::should_ingest((int) $excluded->id));

        // In a disabled category but overridden to Include → ingested.
        $included = $this->getDataGenerator()->create_course(['category' => $disabledcat->id]);
        $this->set_override((int) $included->id, course_gate::OVERRIDE_INCLUDE);
        $this->assertTrue(course_gate::should_ingest((int) $included->id));
    }

    /**
     * The install/upgrade setup creates the override field, idempotently.
     */
    public function test_ensure_course_field_idempotent(): void {
        global $DB;
        $this->resetAfterTest();

        setup::ensure_course_field();
        setup::ensure_course_field();

        $this->assertSame(1,
            $DB->count_records('customfield_field', ['shortname' => course_gate::FIELD]));
    }

    /**
     * Set the per-course override custom field value.
     *
     * @param int $courseid The course id.
     * @param string $option One of the course_gate::OVERRIDE_* option labels.
     */
    private function set_override(int $courseid, string $option): void {
        $handler = \core_course\customfield\course_handler::create();
        foreach ($handler->get_instance_data($courseid, true) as $datum) {
            if ($datum->get_field()->get('shortname') !== course_gate::FIELD) {
                continue;
            }
            // Select fields store the 1-based option index.
            $options = [
                course_gate::OVERRIDE_DEFAULT => 1,
                course_gate::OVERRIDE_INCLUDE => 2,
                course_gate::OVERRIDE_EXCLUDE => 3,
            ];
            $datum->set('intvalue', $options[$option]);
            $datum->set('value', (string) $options[$option]);
            $datum->set('contextid', \context_course::instance($courseid)->id);
            $datum->save();
            return;
        }
    }
}
