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
 * DevFlow documentation page.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/output/shell.php');

use local_ragingest\local\markdown_renderer;
use local_ragingest\output\shell;

require_login();
$context = \core\context\system::instance();
require_capability('moodle/site:config', $context);

$docs = [
    '00-master' => ['file' => '00-master.md', 'label' => get_string('doc_master', 'local_ragingest')],
    '01-features' => ['file' => '01-features.md', 'label' => get_string('doc_features', 'local_ragingest')],
    '02-user-doc' => ['file' => '02-user-doc.md', 'label' => get_string('doc_user', 'local_ragingest')],
    '03-dev-doc' => ['file' => '03-dev-doc.md', 'label' => get_string('doc_developer', 'local_ragingest')],
    '04-tasks' => ['file' => '04-tasks.md', 'label' => get_string('doc_tasks', 'local_ragingest')],
    '05-quality' => ['file' => '05-quality.md', 'label' => get_string('doc_quality', 'local_ragingest')],
];

$doc = optional_param('doc', '00-master', PARAM_ALPHANUMEXT);
if (!isset($docs[$doc])) {
    $doc = '00-master';
}

$pageurl = new moodle_url('/local/ragingest/docs.php', ['doc' => $doc]);
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('devflowdocs', 'local_ragingest'));
$PAGE->set_heading(get_string('devflowdocs', 'local_ragingest'));
$PAGE->add_body_class('path-local-ragingest');
$PAGE->activityheader->disable();
shell::require_css();

echo $OUTPUT->header();

$content = '';
if (shell::is_available()) {
    $content .= shell::header_html(shell::ACTIVE_DOCS);
}

$nav = html_writer::start_tag('nav', [
    'class' => 'rg-docs-nav rg-shell-card',
    'aria-label' => get_string('devflowdocs', 'local_ragingest'),
]);
foreach ($docs as $key => $docinfo) {
    $attrs = [
        'href' => (new moodle_url('/local/ragingest/docs.php', ['doc' => $key]))->out(false),
    ];
    if ($key === $doc) {
        $attrs['aria-current'] = 'page';
    }
    $nav .= html_writer::link($attrs['href'], s($docinfo['label']), $attrs);
}
$nav .= html_writer::end_tag('nav');

$path = __DIR__ . '/docs/' . $docs[$doc]['file'];
$markdown = file_exists($path) ? file_get_contents($path) : '';
$article = html_writer::tag(
    'article',
    markdown_renderer::render((string) $markdown),
    ['class' => 'rg-docs-content rg-shell-card']
);

$content .= html_writer::tag(
    'div',
    html_writer::tag('div', $nav . $article, ['class' => 'rg-docs-layout']),
    ['class' => 'lh-plugin-content-area']
);

echo html_writer::tag('div', $content, ['class' => 'lh-plugin-shell rg-shell-page']);

echo $OUTPUT->footer();
