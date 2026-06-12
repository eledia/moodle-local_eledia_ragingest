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
 * Admin settings for the RAG ingestion plugin.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_ragingest_settings', get_string('pluginname', 'local_ragingest'));

    // RAG endpoint URL.
    $settings->add(new admin_setting_configtext(
        'local_ragingest/rag_endpoint_url',
        get_string('rag_endpoint_url', 'local_ragingest'),
        get_string('rag_endpoint_url_desc', 'local_ragingest'),
        'http://rag-service:8001/documents/upsert',
        PARAM_URL
    ));

    // API key (password field, server-side only).
    $settings->add(new admin_setting_configpasswordunmask(
        'local_ragingest/rag_api_key',
        get_string('rag_api_key', 'local_ragingest'),
        get_string('rag_api_key_desc', 'local_ragingest'),
        ''
    ));

    // Note: there is deliberately NO tenant setting. The tenant identity is
    // derived from $CFG->wwwroot (see \local_ragingest\tenant), matching what
    // the RAG service verifies on the retrieval path.

    // Course marking — category allow-list (opt-in). A course is ingested when
    // its category (or an ancestor) is selected here, unless overridden on the
    // course itself via the "RAG ingestion" custom field.
    $categoryoptions = [];
    if (during_initial_install() === false) {
        $categoryoptions = \core_course_category::make_categories_list();
    }
    $categorysetting = new admin_setting_configmultiselect(
        'local_ragingest/enabledcategories',
        get_string('enabledcategories', 'local_ragingest'),
        get_string('enabledcategories_desc', 'local_ragingest'),
        [],
        $categoryoptions
    );
    // Re-evaluate every course's marking promptly when the list changes.
    $categorysetting->set_updatedcallback(function () {
        \local_ragingest\course_state::queue_divergent_reconciles();
    });
    $settings->add($categorysetting);

    // Central pilot-course list — names specific courses (one shortname or
    // course id per line) to ingest regardless of category. Intended for
    // test/pilot phases.
    $pilotsetting = new admin_setting_configtextarea(
        'local_ragingest/pilotcourses',
        get_string('pilotcourses', 'local_ragingest'),
        get_string('pilotcourses_desc', 'local_ragingest'),
        '',
        PARAM_RAW
    );
    $pilotsetting->set_updatedcallback(function () {
        \local_ragingest\course_state::queue_divergent_reconciles();
    });
    $settings->add($pilotsetting);

    // Lock teacher editing of the per-course "RAG ingestion" override. When on
    // (test-phase lock-down), only managers/admins can change a course's
    // marking; teachers still see it read-only.
    $locksetting = new admin_setting_configcheckbox(
        'local_ragingest/lockcoursemarking',
        get_string('lockcoursemarking', 'local_ragingest'),
        get_string('lockcoursemarking_desc', 'local_ragingest'),
        0
    );
    $locksetting->set_updatedcallback(function () {
        \local_ragingest\setup::sync_field_lock();
        // Toggling the lock changes which rules apply (overrides become inert
        // or effective again) — reconcile affected courses promptly.
        \local_ragingest\course_state::queue_divergent_reconciles();
    });
    $settings->add($locksetting);

    // Max document size in MB.
    $settings->add(new admin_setting_configtext(
        'local_ragingest/max_document_size_mb',
        get_string('max_document_size_mb', 'local_ragingest'),
        get_string('max_document_size_mb_desc', 'local_ragingest'),
        '20',
        PARAM_INT
    ));

    // Request timeout in seconds.
    $settings->add(new admin_setting_configtext(
        'local_ragingest/request_timeout_seconds',
        get_string('request_timeout_seconds', 'local_ragingest'),
        get_string('request_timeout_seconds_desc', 'local_ragingest'),
        '30',
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);

    // Reindex external page.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_ragingest_reindex',
        get_string('reindex', 'local_ragingest'),
        new moodle_url('/local/ragingest/reindex.php')
    ));
}
