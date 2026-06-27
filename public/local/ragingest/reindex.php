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
 * Admin page for manual course content reindexing.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/output/shell.php');

use local_ragingest\output\shell;

require_login();
$context = \core\context\system::instance();
require_capability('local/ragingest:reindex', $context);

$courseid = optional_param('courseid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$queuepending = optional_param('queuepending', 0, PARAM_BOOL);

$pageurl = new moodle_url('/local/ragingest/reindex.php');
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('reindex', 'local_ragingest'));
$PAGE->set_heading(get_string('reindex', 'local_ragingest'));
$PAGE->add_body_class('path-local-ragingest');
$PAGE->activityheader->disable();
shell::require_css();

echo $OUTPUT->header();

echo html_writer::start_div('lh-plugin-shell rg-shell-page');
if (shell::is_available()) {
    echo $OUTPUT->render_from_template(
        'local_lernhive/plugin_shell_header',
        shell::context(shell::ACTIVE_REINDEX)
    );
}
echo html_writer::start_div('lh-plugin-content-area rg-shell-card');

if ($queuepending && confirm_sesskey()) {
    $queued = \local_ragingest\course_state::queue_pending_ingestions();
    echo $OUTPUT->notification(get_string('pendingindexingqueued', 'local_ragingest', $queued), 'success');
    echo $OUTPUT->single_button($pageurl, get_string('back'), 'get');
} else if ($courseid && $confirm && confirm_sesskey()) {
    // Perform reindex.
    try {
        $course = get_course($courseid);
    } catch (\dml_missing_record_exception $e) {
        echo $OUTPUT->notification(get_string('coursenotfound', 'local_ragingest'), 'error');
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo $OUTPUT->footer();
        die();
    }

    echo $OUTPUT->heading(get_string('ingesting', 'local_ragingest', $course->fullname), 3);

    $manager = new \local_ragingest\ingestion_manager();
    $results = $manager->reindex_course($courseid);

    $haserror = array_reduce($results, static function (bool $carry, array $result): bool {
        return $carry || (($result['status'] ?? '') === 'error');
    }, false);
    if (!$haserror) {
        // Record the index state so that later un-marking the course triggers a
        // purge. Failed API runs are intentionally left pending for retry.
        \local_ragingest\course_state::set_ingested(
            $courseid,
            \local_ragingest\course_gate::should_ingest($courseid)
        );
    }

    // Display results table.
    $table = new html_table();
    $table->head = [
        get_string('modulename', 'local_ragingest'),
        get_string('status', 'local_ragingest'),
        get_string('details', 'local_ragingest'),
    ];
    $table->attributes['class'] = 'generaltable table-striped table-hover table-sm';

    $successcount = 0;
    foreach ($results as $result) {
        $row = new html_table_row();

        $modname = $result['module_name'] ?? get_string('unknownmodule', 'local_ragingest', $result['cmid']);
        $row->cells[] = $modname;

        if ($result['success']) {
            $row->cells[] = html_writer::tag(
                'span',
                get_string('statussuccess', 'local_ragingest'),
                ['class' => 'badge badge-success bg-success']
            );
            $successcount++;
        } else if ($result['status'] === 'skipped') {
            $row->cells[] = html_writer::tag(
                'span',
                get_string('statusskipped', 'local_ragingest'),
                ['class' => 'badge badge-warning bg-warning text-dark']
            );
        } else {
            $row->cells[] = html_writer::tag(
                'span',
                get_string('statuserror', 'local_ragingest'),
                ['class' => 'badge badge-danger bg-danger']
            );
        }

        $row->cells[] = s((string) ($result['message'] ?? ''));
        $table->data[] = $row;
    }

    echo html_writer::start_tag('div', ['role' => 'status', 'aria-live' => 'polite']);
    echo html_writer::table($table);
    if ($haserror) {
        echo $OUTPUT->notification(get_string('reindexcomplete', 'local_ragingest'), 'warning');
    } else {
        echo $OUTPUT->notification(get_string('reindexsuccess', 'local_ragingest', $successcount), 'success');
    }
    echo html_writer::end_tag('div');

    // Back link.
    echo $OUTPUT->single_button($pageurl, get_string('back'), 'get');
} else if ($courseid) {
    // Show confirmation.
    try {
        $course = get_course($courseid);
    } catch (\dml_missing_record_exception $e) {
        echo $OUTPUT->notification(get_string('coursenotfound', 'local_ragingest'), 'error');
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo $OUTPUT->footer();
        die();
    }

    echo $OUTPUT->heading($course->fullname, 3);

    $confirmurl = new moodle_url('/local/ragingest/reindex.php', [
        'courseid' => $courseid,
        'confirm' => 1,
        'sesskey' => sesskey(),
    ]);
    echo $OUTPUT->single_button($confirmurl, get_string('reindex_btn', 'local_ragingest'), 'post');
    echo $OUTPUT->single_button($pageurl, get_string('cancel'), 'get');
} else {
    // Show course selection form.
    echo html_writer::tag(
        'div',
        html_writer::tag('h2', get_string('reindex', 'local_ragingest'), ['class' => 'rg-page-title']) .
        html_writer::tag('p', get_string('reindexintro', 'local_ragingest'), ['class' => 'rg-page-intro']),
        ['class' => 'rg-page-head']
    );

    $pendingcount = \local_ragingest\course_state::pending_ingestion_count();
    if ($pendingcount > 0) {
        $queueurl = new moodle_url('/local/ragingest/reindex.php', [
            'queuepending' => 1,
            'sesskey' => sesskey(),
        ]);
        echo html_writer::tag(
            'section',
            html_writer::tag(
                'div',
                html_writer::span(
                    html_writer::tag('i', '', ['class' => 'fa fa-upload', 'aria-hidden' => 'true']),
                    'rg-action-panel__icon'
                ) .
                html_writer::tag(
                    'div',
                    html_writer::tag(
                        'h3',
                        get_string('pendingindexingtitle', 'local_ragingest'),
                        ['class' => 'rg-action-panel__title']
                    ) .
                    html_writer::tag(
                        'p',
                        get_string('pendingindexingcount', 'local_ragingest', $pendingcount),
                        ['class' => 'rg-action-panel__body']
                    ),
                    ['class' => 'rg-action-panel__text']
                ),
                ['class' => 'rg-action-panel__main']
            ) .
            html_writer::tag(
                'form',
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'queuepending', 'value' => '1']) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
                html_writer::tag(
                    'button',
                    html_writer::tag('i', '', ['class' => 'fa fa-play', 'aria-hidden' => 'true']) .
                    html_writer::span(get_string('indexreleasedcourses', 'local_ragingest')),
                    ['type' => 'submit', 'class' => 'btn btn-primary rg-primary-action']
                ),
                ['method' => 'post', 'action' => $queueurl->out_omit_querystring(), 'class' => 'rg-action-panel__action']
            ),
            ['class' => 'rg-action-panel rg-action-panel--warning']
        );
    }

    echo html_writer::tag(
        'section',
        html_writer::tag('h3', get_string('manualreindex', 'local_ragingest'), ['class' => 'rg-section-title']) .
        html_writer::tag('p', get_string('manualreindex_desc', 'local_ragingest'), ['class' => 'rg-section-desc']) .
        html_writer::start_tag('form', [
            'method' => 'get',
            'action' => $pageurl->out_omit_querystring(),
            'class' => 'rg-inline-form',
        ]) .
        html_writer::tag('label', get_string('courseid', 'local_ragingest'), [
            'for' => 'id_courseid',
            'class' => 'rg-inline-form__label',
        ]) .
        html_writer::empty_tag('input', [
            'type' => 'number',
            'name' => 'courseid',
            'id' => 'id_courseid',
            'class' => 'form-control rg-inline-form__input',
            'required' => 'required',
            'min' => '1',
        ]) .
        html_writer::tag(
            'button',
            html_writer::tag('i', '', ['class' => 'fa fa-rotate', 'aria-hidden' => 'true']) .
            html_writer::span(get_string('reindexcourse', 'local_ragingest')),
            ['type' => 'submit', 'class' => 'btn btn-secondary rg-secondary-action']
        ) .
        html_writer::end_tag('form'),
        ['class' => 'rg-panel']
    );
}

echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
