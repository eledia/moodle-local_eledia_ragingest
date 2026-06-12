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

use core_customfield\category_controller;
use core_customfield\field_controller;

/**
 * Install/upgrade setup helpers.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setup {
    /**
     * Create the per-course "RAG ingestion" override custom field, if absent.
     *
     * Idempotent: safe to call from both install and upgrade. The field is a
     * three-option select (Default / Include / Exclude) read by
     * {@see course_gate::override()}; the option tokens are stable identifiers,
     * not display strings, so they must not be translated.
     *
     * @return void
     */
    public static function ensure_course_field(): void {
        global $DB;

        // Already present? Nothing to do.
        if ($DB->record_exists('customfield_field', ['shortname' => course_gate::FIELD])) {
            return;
        }

        $handler = \core_course\customfield\course_handler::create();

        // Reuse our category if a previous partial run created it, else make it.
        $categoryid = 0;
        foreach ($handler->get_categories_with_fields() as $category) {
            if ($category->get('name') === self::category_name()) {
                $categoryid = $category->get('id');
                break;
            }
        }
        if ($categoryid === 0) {
            $categoryid = $handler->create_category(self::category_name());
        }

        $catcontroller = category_controller::create($categoryid);
        $field = field_controller::create(0, (object) ['type' => 'select'], $catcontroller);

        $record = (object) [
            'name' => get_string('cffieldname', 'local_ragingest'),
            'shortname' => course_gate::FIELD,
            'type' => 'select',
            'description' => get_string('cffielddesc', 'local_ragingest'),
            'descriptionformat' => FORMAT_HTML,
            'configdata' => [
                'options' => implode("\n", [
                    course_gate::OVERRIDE_DEFAULT,
                    course_gate::OVERRIDE_INCLUDE,
                    course_gate::OVERRIDE_EXCLUDE,
                ]),
                'defaultvalue' => course_gate::OVERRIDE_DEFAULT,
                'required' => 0,
                'uniquevalues' => 0,
                'locked' => 0,
                'visibility' => 2,
            ],
        ];

        $handler->save_field_configuration($field, $record);
    }

    /**
     * The custom-field category name (also used to locate it on re-runs).
     *
     * @return string
     */
    private static function category_name(): string {
        return get_string('cfcategory', 'local_ragingest');
    }
}
