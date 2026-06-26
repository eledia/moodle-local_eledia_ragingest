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

use local_ragingest\output\shell;
use core_admin\local\settings\autocomplete;

if ($hassiteconfig) {
    require_once(__DIR__ . '/classes/output/shell.php');

    $settings = new admin_settingpage('local_ragingest_settings', get_string('pluginname', 'local_ragingest'));
    $fallbackreindexhtml = '';

    $currentsection = optional_param('section', '', PARAM_ALPHANUMEXT);
    if ($ADMIN->fulltree && $currentsection === 'local_ragingest_settings') {
        global $OUTPUT, $PAGE;

        shell::require_css();

        $pendingcount = \local_ragingest\course_state::pending_ingestion_count();
        $panelclass = $pendingcount > 0 ? 'rg-action-panel--warning' : 'rg-action-panel--success';
        $panelicon = $pendingcount > 0 ? 'fa-upload' : 'fa-check';
        $paneltitle = $pendingcount > 0
            ? get_string('pendingindexingtitle', 'local_ragingest')
            : get_string('indexingreadytitle', 'local_ragingest');
        $panelbody = $pendingcount > 0
            ? get_string('pendingindexingcount', 'local_ragingest', $pendingcount)
            : get_string('indexingreadybody', 'local_ragingest');
        $reindexurl = new moodle_url('/local/ragingest/reindex.php');
        $actionhtml = html_writer::link($reindexurl,
            html_writer::tag('i', '', ['class' => 'fa fa-rotate', 'aria-hidden' => 'true']) .
            html_writer::span(get_string('openreindex', 'local_ragingest')),
            ['class' => 'btn btn-secondary rg-secondary-action rg-action-panel__action']
        );
        if ($pendingcount > 0) {
            $actionhtml = html_writer::tag('form',
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'queuepending', 'value' => '1']) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
                html_writer::tag('button',
                    html_writer::tag('i', '', ['class' => 'fa fa-play', 'aria-hidden' => 'true']) .
                    html_writer::span(get_string('indexreleasedcourses', 'local_ragingest')),
                    ['type' => 'submit', 'class' => 'btn btn-primary rg-primary-action']
                ),
                [
                    'method' => 'post',
                    'action' => $reindexurl->out(false),
                    'class' => 'rg-action-panel__action',
                ]
            );
        }
        $reindexhtml = html_writer::tag('section',
            html_writer::tag('div',
                html_writer::span(
                    html_writer::tag('i', '', ['class' => 'fa ' . $panelicon, 'aria-hidden' => 'true']),
                    'rg-action-panel__icon'
                ) .
                html_writer::tag('div',
                    html_writer::tag('h3', $paneltitle, ['class' => 'rg-action-panel__title']) .
                    html_writer::tag('p', $panelbody, ['class' => 'rg-action-panel__body']),
                    ['class' => 'rg-action-panel__text']
                ),
                ['class' => 'rg-action-panel__main']
            ) .
            $actionhtml,
            ['class' => 'rg-action-panel ' . $panelclass . ' rg-settings-indexing-panel']
        );

        if (shell::is_available()) {
            $headerhtml = $OUTPUT->render_from_template(
                'local_lernhive/plugin_shell_header',
                shell::context(shell::ACTIVE_SETTINGS)
            );
            $PAGE->requires->js_call_amd('local_ragingest/settings_shell', 'init', [[
                'headerHtml' => $headerhtml,
                'pluginTitle' => get_string('pluginname', 'local_ragingest'),
                'reindexHtml' => $reindexhtml,
            ]]);
        } else if ($pendingcount > 0) {
            $fallbackreindexhtml = $reindexhtml;
        }
    }

    if ($fallbackreindexhtml !== '') {
        $settings->add(new admin_setting_description(
            'local_ragingest/pending_indexing_notice',
            '',
            $fallbackreindexhtml
        ));
    }

    // Connection.
    $settings->add(new admin_setting_heading(
        'local_ragingest/head_connection',
        get_string('head_connection', 'local_ragingest'),
        get_string('head_connection_desc', 'local_ragingest')
    ));

    $queuecallback = static function(): void {
        \local_ragingest\course_state::queue_divergent_reconciles();
    };

    // RAG endpoint URL.
    $endpointsetting = new admin_setting_configtext(
        'local_ragingest/rag_endpoint_url',
        get_string('rag_endpoint_url', 'local_ragingest'),
        get_string('rag_endpoint_url_desc', 'local_ragingest'),
        'http://rag-service:8001/documents/upsert',
        PARAM_URL
    );
    $endpointsetting->set_updatedcallback($queuecallback);
    $settings->add($endpointsetting);

    // API key (password field, server-side only).
    $apikeysetting = new admin_setting_configpasswordunmask(
        'local_ragingest/rag_api_key',
        get_string('rag_api_key', 'local_ragingest'),
        get_string('rag_api_key_desc', 'local_ragingest'),
        ''
    );
    $apikeysetting->set_updatedcallback($queuecallback);
    $settings->add($apikeysetting);

    $privatetargetsetting = new admin_setting_configcheckbox(
        'local_ragingest/allow_private_target',
        get_string('allow_private_target', 'local_ragingest'),
        get_string('allow_private_target_desc', 'local_ragingest'),
        0
    );
    $privatetargetsetting->set_updatedcallback($queuecallback);
    $settings->add($privatetargetsetting);

    // Note: there is deliberately NO tenant setting. The tenant identity is
    // derived from $CFG->wwwroot (see \local_ragingest\tenant), matching what
    // the RAG service verifies on the retrieval path.

    // Course selection.
    $settings->add(new admin_setting_heading(
        'local_ragingest/head_courses',
        get_string('head_courses', 'local_ragingest'),
        get_string('head_courses_desc', 'local_ragingest')
    ));

    // Course marking — category allow-list (opt-in). A course is ingested when
    // its category (or an ancestor) is selected here, unless overridden on the
    // course itself via the "RAG ingestion" custom field.
    $categoryoptions = [];
    if (during_initial_install() === false) {
        $categoryoptions = \core_course_category::make_categories_list();
    }
    $categorysetting = new autocomplete(
        'local_ragingest/enabledcategories',
        get_string('enabledcategories', 'local_ragingest'),
        get_string('enabledcategories_desc', 'local_ragingest'),
        [],
        $categoryoptions,
        [
            'multiple' => true,
            'delimiter' => ',',
            'placeholder' => get_string('search'),
            'manageurl' => false,
            'managetext' => '',
        ]
    );
    // Re-evaluate every course's marking promptly when the list changes.
    $categorysetting->set_updatedcallback($queuecallback);
    $settings->add($categorysetting);

    // Central pilot-course list — select specific courses to ingest regardless
    // of category. Intended for test/pilot phases.
    $courseoptions = [];
    if (during_initial_install() === false) {
        global $DB;

        $courses = $DB->get_records_select('course', 'id <> :siteid', ['siteid' => SITEID], 'fullname ASC',
            'id, fullname, shortname');
        foreach ($courses as $course) {
            $courseoptions[(string) $course->id] = format_string($course->fullname)
                . ' (' . s($course->shortname) . ')';
        }
    }
    $pilotsetting = new autocomplete(
        'local_ragingest/pilotcourses',
        get_string('pilotcourses', 'local_ragingest'),
        get_string('pilotcourses_desc', 'local_ragingest'),
        [],
        $courseoptions,
        [
            'multiple' => true,
            'delimiter' => ',',
            'placeholder' => get_string('searchcourses', 'local_ragingest'),
            'manageurl' => false,
            'managetext' => '',
        ]
    );
    $pilotsetting->set_updatedcallback($queuecallback);
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

    // Limits.
    $settings->add(new admin_setting_heading(
        'local_ragingest/head_limits',
        get_string('head_limits', 'local_ragingest'),
        get_string('head_limits_desc', 'local_ragingest')
    ));

    // Max document size in MB.
    $maxsizesetting = new admin_setting_configtext(
        'local_ragingest/max_document_size_mb',
        get_string('max_document_size_mb', 'local_ragingest'),
        get_string('max_document_size_mb_desc', 'local_ragingest'),
        '20',
        PARAM_INT
    );
    $maxsizesetting->set_updatedcallback($queuecallback);
    $settings->add($maxsizesetting);

    // Request timeout in seconds.
    $timeoutsetting = new admin_setting_configtext(
        'local_ragingest/request_timeout_seconds',
        get_string('request_timeout_seconds', 'local_ragingest'),
        get_string('request_timeout_seconds_desc', 'local_ragingest'),
        '30',
        PARAM_INT
    );
    $timeoutsetting->set_updatedcallback($queuecallback);
    $settings->add($timeoutsetting);

    $ADMIN->add('localplugins', $settings);

    // Reindex external page.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_ragingest_reindex',
        get_string('reindex', 'local_ragingest'),
        new moodle_url('/local/ragingest/reindex.php')
    ));

    // DevFlow documentation page.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_ragingest_docs',
        get_string('devflowdocs', 'local_ragingest'),
        new moodle_url('/local/ragingest/docs.php')
    ));
}
