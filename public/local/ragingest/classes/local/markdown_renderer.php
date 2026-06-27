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

declare(strict_types=1);

namespace local_ragingest\local;

use html_writer;

/**
 * Small safe Markdown renderer for bundled DevFlow documents.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class markdown_renderer {
    /**
     * Render a small Markdown subset used by the DevFlow docs.
     *
     * @param string $markdown Markdown source.
     * @return string HTML.
     */
    public static function render(string $markdown): string {
        $html = '';
        $inlist = false;
        $incode = false;
        $code = [];
        $paragraph = '';

        $closeparagraph = static function () use (&$paragraph, &$html): void {
            if ($paragraph !== '') {
                $html .= html_writer::tag('p', self::inline($paragraph));
                $paragraph = '';
            }
        };

        $closelist = static function () use (&$inlist, &$html): void {
            if ($inlist) {
                $html .= html_writer::end_tag('ul');
                $inlist = false;
            }
        };

        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            if (preg_match('/^\x60\x60\x60/', $line)) {
                $closeparagraph();
                $closelist();
                if ($incode) {
                    $html .= html_writer::tag('pre', html_writer::tag('code', s(implode("\n", $code))));
                    $code = [];
                    $incode = false;
                } else {
                    $incode = true;
                }
                continue;
            }

            if ($incode) {
                $code[] = $line;
                continue;
            }

            if (trim($line) === '') {
                $closeparagraph();
                $closelist();
                continue;
            }

            if (preg_match('/^(#{1,3})\s+(.*)$/', $line, $matches)) {
                $closeparagraph();
                $closelist();
                $level = strlen($matches[1]);
                $html .= html_writer::tag('h' . $level, self::inline($matches[2]));
                continue;
            }

            if (preg_match('/^\-\s+(.*)$/', $line, $matches)) {
                $closeparagraph();
                if (!$inlist) {
                    $html .= html_writer::start_tag('ul');
                    $inlist = true;
                }
                $html .= html_writer::tag('li', self::inline($matches[1]));
                continue;
            }

            $paragraph .= ($paragraph === '' ? '' : ' ') . trim($line);
        }

        $closeparagraph();
        $closelist();
        if ($incode) {
            $html .= html_writer::tag('pre', html_writer::tag('code', s(implode("\n", $code))));
        }

        return $html;
    }

    /**
     * Render inline Markdown for code and strong text.
     *
     * @param string $text Markdown text.
     * @return string HTML.
     */
    private static function inline(string $text): string {
        $backtick = chr(96);
        $parts = preg_split('/(\x60[^\x60]+\x60|\*\*[^*]+\*\*)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $html = '';
        foreach ($parts ?: [] as $part) {
            if (str_starts_with($part, $backtick) && str_ends_with($part, $backtick)) {
                $html .= html_writer::tag('code', s(substr($part, 1, -1)));
            } else if (str_starts_with($part, '**') && str_ends_with($part, '**')) {
                $html .= html_writer::tag('strong', s(substr($part, 2, -2)));
            } else {
                $html .= s($part);
            }
        }
        return $html;
    }
}
