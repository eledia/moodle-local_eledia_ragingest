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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin-owned help page.
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
require_capability('moodle/site:config', $context);

$url = new moodle_url('/local/ragingest/help.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->blocks->show_only_fake_blocks(true);
$PAGE->set_title(get_string('shell_help_label', 'local_ragingest'));
$PAGE->set_heading(shell::is_available() ? '' : get_string('shell_help_label', 'local_ragingest'));
$PAGE->activityheader->disable();
shell::require_css();

$docfile = str_starts_with(current_language(), 'de')
    ? __DIR__ . '/docs/02-user-doc.de.md'
    : __DIR__ . '/docs/02-user-doc.md';
if (!is_readable($docfile)) {
    $docfile = __DIR__ . '/docs/02-user-doc.md';
}

$markdown = is_readable($docfile) ? (string) file_get_contents($docfile) : '';
$html = $markdown !== ''
    ? format_text($markdown, FORMAT_MARKDOWN, ['context' => $context])
    : html_writer::tag('p', get_string('error'));

echo $OUTPUT->header();

if (shell::is_available()) {
    $header = shell::context('help');
    $header['tagline'] = get_string('help', 'core');
    $header['sectionnav'] = shell::sectionnav('help');
    echo html_writer::start_div('lh-plugin-shell rg-shell-page');
    echo $OUTPUT->render_from_template('local_lernhive/plugin_shell_header', $header);
    echo html_writer::start_div('lh-plugin-content-area');
} else {
    echo $OUTPUT->heading(get_string('shell_help_label', 'local_ragingest'));
}

echo html_writer::tag('article', $html, ['class' => 'rg-docs-content rg-shell-card']);

if (shell::is_available()) {
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
