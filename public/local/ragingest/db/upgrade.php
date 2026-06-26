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

/**
 * Upgrade steps for the RAG ingestion plugin.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute upgrade steps.
 *
 * @param int $oldversion The currently installed version.
 * @return bool
 */
function xmldb_local_ragingest_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026061300) {
        // Course ingestion-state table (see classes/course_state.php).
        $table = new xmldb_table('local_ragingest_course');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('ingested', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN_UNIQUE, ['courseid'], 'course', ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Per-course ingestion-override custom field.
        \local_ragingest\setup::ensure_course_field();

        upgrade_plugin_savepoint(true, 2026061300, 'local', 'ragingest');
    }

    if ($oldversion < 2026061301) {
        // Apply the field lock state from the (new) test-phase setting.
        \local_ragingest\setup::ensure_course_field();
        \local_ragingest\setup::sync_field_lock();

        upgrade_plugin_savepoint(true, 2026061301, 'local', 'ragingest');
    }

    if ($oldversion < 2026061302) {
        // Code-only release: settings shell/docs and extractor hardening.
        upgrade_plugin_savepoint(true, 2026061302, 'local', 'ragingest');
    }

    if ($oldversion < 2026061303) {
        // Code-only release: privacy metadata and RAG-Ingest UX refinements.
        upgrade_plugin_savepoint(true, 2026061303, 'local', 'ragingest');
    }

    return true;
}
