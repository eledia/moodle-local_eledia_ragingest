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

require_login();
$context = context_system::instance();
require_capability('local/ragingest:reindex', $context);

$courseid = optional_param('courseid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$pageurl = new moodle_url('/local/ragingest/reindex.php');
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('reindex', 'local_ragingest'));
$PAGE->set_heading(get_string('reindex', 'local_ragingest'));

echo $OUTPUT->header();

if ($courseid && $confirm && confirm_sesskey()) {
    // Perform reindex.
    try {
        $course = get_course($courseid);
    } catch (\dml_missing_record_exception $e) {
        echo $OUTPUT->notification(get_string('coursenotfound', 'local_ragingest'), 'error');
        echo $OUTPUT->footer();
        die();
    }

    echo $OUTPUT->heading(get_string('ingesting', 'local_ragingest', $course->fullname), 3);

    $manager = new \local_ragingest\ingestion_manager();
    $results = $manager->reindex_course($courseid);

    // Display results table.
    $table = new html_table();
    $table->head = [
        get_string('modulename', 'local_ragingest'),
        get_string('status', 'local_ragingest'),
        get_string('details', 'local_ragingest'),
    ];
    $table->attributes['class'] = 'generaltable';

    $successcount = 0;
    foreach ($results as $result) {
        $row = new html_table_row();

        $modname = $result['module_name'] ?? "cmid {$result['cmid']}";
        $row->cells[] = $modname;

        if ($result['success']) {
            $row->cells[] = html_writer::tag('span', get_string('statussuccess', 'local_ragingest'),
                ['class' => 'badge badge-success bg-success']);
            $successcount++;
        } else if ($result['status'] === 'skipped') {
            $row->cells[] = html_writer::tag('span', get_string('statusskipped', 'local_ragingest'),
                ['class' => 'badge badge-warning bg-warning']);
        } else {
            $row->cells[] = html_writer::tag('span', get_string('statuserror', 'local_ragingest'),
                ['class' => 'badge badge-danger bg-danger']);
        }

        $row->cells[] = $result['message'] ?? '';
        $table->data[] = $row;
    }

    echo html_writer::table($table);
    echo $OUTPUT->notification(get_string('reindexsuccess', 'local_ragingest', $successcount), 'success');

    // Back link.
    echo $OUTPUT->single_button($pageurl, get_string('back'), 'get');

} else if ($courseid) {
    // Show confirmation.
    try {
        $course = get_course($courseid);
    } catch (\dml_missing_record_exception $e) {
        echo $OUTPUT->notification(get_string('coursenotfound', 'local_ragingest'), 'error');
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
    echo $OUTPUT->heading(get_string('selectcourse', 'local_ragingest'), 3);

    $html = html_writer::start_tag('form', [
        'method' => 'get',
        'action' => $pageurl->out_omit_querystring(),
        'class' => 'form-inline mb-3',
    ]);
    $html .= html_writer::tag('label', get_string('courseid', 'local_ragingest'), [
        'for' => 'id_courseid',
        'class' => 'mr-2',
    ]);
    $html .= html_writer::empty_tag('input', [
        'type' => 'number',
        'name' => 'courseid',
        'id' => 'id_courseid',
        'class' => 'form-control mr-2',
        'required' => 'required',
        'min' => '1',
    ]);
    $html .= html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => get_string('reindexcourse', 'local_ragingest'),
        'class' => 'btn btn-primary',
    ]);
    $html .= html_writer::end_tag('form');
    echo $html;
}

echo $OUTPUT->footer();
