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
 * LernHive Plugin Shell adapter for eLeDia.ai RagIngest pages.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ragingest\output;

use html_writer;
use moodle_url;

/**
 * Builds the shared LernHive Plugin Shell context for eLeDia.ai RagIngest.
 */
final class shell {
    /** @var string Settings section key. */
    public const ACTIVE_SETTINGS = 'settings';

    /** @var string Reindex section key. */
    public const ACTIVE_REINDEX = 'reindex';

    /** @var string Documentation section key. */
    public const ACTIVE_DOCS = 'docs';

    /**
     * Check whether the LernHive shell helper is installed and autoloadable.
     *
     * @return bool
     */
    public static function is_available(): bool {
        return class_exists('\local_lernhive\output\plugin_shell');
    }

    /**
     * Require styles used by the shell and eLeDia.ai RagIngest admin UI.
     */
    public static function require_css(): void {
        global $PAGE;

        $PAGE->requires->css('/local/ragingest/styles.css');
        if (self::is_available()) {
            $PAGE->requires->css('/local/lernhive/styles.css');
        }
    }

    /**
     * Build the shell context.
     *
     * @param string $active Active section key.
     * @return array<string, mixed>
     */
    public static function context(string $active = self::ACTIVE_SETTINGS): array {
        if (!self::is_available()) {
            return [];
        }

        return [
            'name' => get_string('pluginname', 'local_ragingest'),
            'tagline' => get_string('shell_tagline', 'local_ragingest'),
            'subtitle' => get_string('shell_subtitle', 'local_ragingest'),
            'sectionnav' => self::sectionnav($active),
        ] + \local_lernhive\output\plugin_shell::action_slots(
            'local_ragingest',
            true,
            null,
            get_string('shell_help_label', 'local_ragingest'),
            null,
            $active === self::ACTIVE_SETTINGS
        );
    }

    /**
     * Build the Plugin Shell section navigation.
     *
     * @param string $active Active section key.
     * @return string Raw HTML for the Plugin Shell section navigation slot.
     */
    public static function sectionnav(string $active): string {
        if (
            class_exists('\block_elediaaitutor\output\shell')
                && method_exists('\block_elediaaitutor\output\shell', 'sectionnav')
        ) {
            return \block_elediaaitutor\output\shell::sectionnav('ragingest');
        }

        $items = [
            self::ACTIVE_SETTINGS => [
                'url' => new moodle_url('/admin/settings.php', ['section' => 'local_ragingest_settings']),
                'icon' => 'fa-gear',
                'label' => get_string('nav_settings', 'local_ragingest'),
            ],
            self::ACTIVE_REINDEX => [
                'url' => new moodle_url('/local/ragingest/reindex.php'),
                'icon' => 'fa-rotate',
                'label' => get_string('nav_reindex', 'local_ragingest'),
            ],
            self::ACTIVE_DOCS => [
                'url' => new moodle_url('/local/ragingest/docs.php'),
                'icon' => 'fa-book',
                'label' => get_string('nav_docs', 'local_ragingest'),
            ],
        ];

        $links = '';
        foreach ($items as $key => $item) {
            $attrs = [
                'class' => 'lh-plugin-section-nav__item',
                'href' => $item['url']->out(false),
            ];
            if ($active === $key) {
                $attrs['aria-current'] = 'page';
            }
            $links .= html_writer::tag(
                'a',
                html_writer::tag('i', '', ['class' => 'fa ' . $item['icon'], 'aria-hidden' => 'true']) .
                ' ' . s($item['label']),
                $attrs
            );
        }

        return html_writer::tag('nav', $links, [
            'class' => 'lh-plugin-section-nav',
            'aria-label' => get_string('nav_label', 'local_ragingest'),
        ]);
    }
}
