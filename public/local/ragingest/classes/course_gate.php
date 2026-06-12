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
 * Decides whether a course's content may be sent to the RAG service.
 *
 * Marking is **opt-in**: a course is ingested only when it is explicitly
 * selected. Two mechanisms combine, with the per-course override taking
 * precedence over the institution-wide category allow-list:
 *
 * 1. **Category allow-list** (admin setting `enabledcategories`): a course is
 *    eligible when its category — or any ancestor category — is on the list.
 * 2. **Pilot-course list** (admin setting `pilotcourses`): named courses are
 *    eligible regardless of category. Intended for test/pilot phases where a
 *    specific set of courses is selected centrally.
 * 3. **Per-course override** (the `ragingest` course custom field): `Include`
 *    forces ingestion regardless of the above; `Exclude` forbids it regardless
 *    of the above; `Default` defers to the admin lists. During a pilot phase the
 *    field can be locked so only managers/admins set it (see settings.php).
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_gate {
    /** @var string Custom field shortname carrying the per-course override. */
    public const FIELD = 'ragingest';

    /** @var string Override option: defer to the category rule. */
    public const OVERRIDE_DEFAULT = 'Default';

    /** @var string Override option: always ingest. */
    public const OVERRIDE_INCLUDE = 'Include';

    /** @var string Override option: never ingest. */
    public const OVERRIDE_EXCLUDE = 'Exclude';

    /**
     * Whether the given course is marked for ingestion.
     *
     * @param int $courseid The course id.
     * @return bool
     */
    public static function should_ingest(int $courseid): bool {
        if ($courseid <= 0 || $courseid == SITEID) {
            return false;
        }

        $override = self::override($courseid);
        if ($override === self::OVERRIDE_INCLUDE) {
            return true;
        }
        if ($override === self::OVERRIDE_EXCLUDE) {
            return false;
        }

        // Admin "include" sources: the central pilot list or the category list.
        return self::in_pilot_list($courseid) || self::category_enabled($courseid);
    }

    /**
     * Read the per-course override value from the custom field.
     *
     * @param int $courseid The course id.
     * @return string One of the OVERRIDE_* constants (OVERRIDE_DEFAULT when unset).
     */
    public static function override(int $courseid): string {
        // The field may not exist yet (fresh install before setup); be tolerant.
        try {
            $handler = \core_course\customfield\course_handler::create();
            $data = $handler->get_instance_data($courseid, true);
        } catch (\Throwable $e) {
            return self::OVERRIDE_DEFAULT;
        }

        foreach ($data as $datum) {
            if ($datum->get_field()->get('shortname') !== self::FIELD) {
                continue;
            }
            $value = (string) $datum->export_value();
            if ($value === self::OVERRIDE_INCLUDE || $value === self::OVERRIDE_EXCLUDE) {
                return $value;
            }
            return self::OVERRIDE_DEFAULT;
        }

        return self::OVERRIDE_DEFAULT;
    }

    /**
     * Whether the course is named in the central pilot-course list.
     *
     * @param int $courseid The course id.
     * @return bool
     */
    private static function in_pilot_list(int $courseid): bool {
        return isset(self::pilot_course_ids()[$courseid]);
    }

    /**
     * Resolve the configured pilot-course list to a set of course ids.
     *
     * Each non-empty line is a course shortname, or a numeric course id when it
     * matches an existing course. Cached per request, keyed on the raw setting.
     *
     * @return array<int, true>
     */
    private static function pilot_course_ids(): array {
        global $DB;
        static $cache = [];

        $raw = (string) get_config('local_ragingest', 'pilotcourses');
        if (trim($raw) === '') {
            return [];
        }
        if (array_key_exists($raw, $cache)) {
            return $cache[$raw];
        }

        $set = [];
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // Numeric line that is a real course id.
            if (ctype_digit($line) && $DB->record_exists('course', ['id' => (int) $line])) {
                $set[(int) $line] = true;
                continue;
            }
            // Otherwise treat as a (unique) course shortname.
            $id = $DB->get_field('course', 'id', ['shortname' => $line]);
            if ($id) {
                $set[(int) $id] = true;
            }
        }

        $cache[$raw] = $set;
        return $set;
    }

    /**
     * Whether the course's category (or an ancestor) is on the allow-list.
     *
     * @param int $courseid The course id.
     * @return bool
     */
    private static function category_enabled(int $courseid): bool {
        $enabled = self::enabled_category_ids();
        if (empty($enabled)) {
            // Opt-in: nothing is eligible by category until categories are chosen.
            return false;
        }

        try {
            $course = get_course($courseid);
        } catch (\Throwable $e) {
            return false;
        }
        $catid = (int) $course->category;
        if ($catid <= 0) {
            return false;
        }

        $category = \core_course_category::get($catid, IGNORE_MISSING, true);
        if (!$category) {
            return false;
        }

        $chain = array_merge([$catid], array_map('intval', $category->get_parents()));
        foreach ($chain as $id) {
            if (isset($enabled[$id])) {
                return true;
            }
        }
        return false;
    }

    /**
     * The configured allow-list of category ids, as a lookup set.
     *
     * @return array<int, true>
     */
    private static function enabled_category_ids(): array {
        $raw = (string) get_config('local_ragingest', 'enabledcategories');
        if (trim($raw) === '') {
            return [];
        }
        $set = [];
        foreach (explode(',', $raw) as $id) {
            $id = (int) trim($id);
            if ($id > 0) {
                $set[$id] = true;
            }
        }
        return $set;
    }
}
