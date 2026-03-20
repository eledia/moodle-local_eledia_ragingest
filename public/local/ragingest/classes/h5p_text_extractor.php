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

/**
 * Utility to extract educational text from H5P content JSON.
 *
 * H5P stores interactive content as deeply-nested JSON in the
 * `h5p.jsoncontent` database field. The JSON structure varies per
 * content type (40+ types). This class walks the tree generically,
 * collecting string values that look like educational content and
 * filtering out known non-content keys (identifiers, config, CSS).
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class h5p_text_extractor {
    /**
     * Keys whose values should be skipped during recursive extraction.
     *
     * These are identifiers, config or structural keys present across
     * many H5P content types that never contain educational text.
     */
    private const SKIP_KEYS = [
        'subcontentid',
        'library',
        'css',
        'path',
        'mime',
        'mimetype',
        'copyright',
        'metadata',
        'overridesettings',
        'behaviour',
        'confirmationDialog',
        'l10n',
        'a11y',
        'scoreBarLabel',
        'submitAnswer',
        'confirmCheck',
        'confirmRetry',
        'checkAnswer',
        'showSolutionButton',
        'retryButton',
        'tipButtonLabel',
        'lock',
        'reset',
        'scorebar',
        'scoreExplanation',
        'showSolution',
        'tryAgain',
        'check',
        'font',
        'height',
        'width',
        'x',
        'y',
        'action',
    ];

    /** @var int Minimum string length to consider as content. */
    private const MIN_TEXT_LENGTH = 10;

    /**
     * Extract plain text from an H5P jsoncontent string.
     *
     * Decodes the JSON, recursively walks the structure, collects
     * educational text snippets, strips HTML tags, and returns
     * a single plain-text string.
     *
     * @param string $jsoncontent The raw jsoncontent from the h5p table.
     * @return string Extracted plain text (may be empty).
     */
    public static function extract_text_from_json(string $jsoncontent): string {
        $data = json_decode($jsoncontent, true);
        if (!is_array($data)) {
            return '';
        }

        // The jsoncontent may wrap actual params under a 'params' key.
        if (isset($data['params']) && is_array($data['params'])) {
            $data = $data['params'];
        }

        $texts = [];
        self::extract_texts_recursive($data, $texts);

        // Deduplicate while preserving order.
        $texts = array_values(array_unique($texts));

        return implode("\n", $texts);
    }

    /**
     * Recursively walk a decoded H5P JSON structure and collect text values.
     *
     * Skips keys in the blocklist, filters out short strings that are
     * likely identifiers or config values, and strips HTML from text
     * fields that contain markup.
     *
     * @param mixed $data The current node in the JSON tree.
     * @param string[] $texts Collected text snippets (passed by reference).
     * @param string $currentkey The key name of the current node (for filtering).
     */
    private static function extract_texts_recursive(mixed $data, array &$texts, string $currentkey = ''): void {
        if (is_string($data)) {
            self::collect_text($data, $texts);
            return;
        }

        if (!is_array($data)) {
            return;
        }

        foreach ($data as $key => $value) {
            $keystr = is_string($key) ? $key : '';

            // Skip blocked keys.
            if ($keystr !== '' && in_array(strtolower($keystr), self::SKIP_KEYS, true)) {
                continue;
            }

            self::extract_texts_recursive($value, $texts, $keystr);
        }
    }

    /**
     * Process a single string value and add it to the collection if it contains content.
     *
     * Strips HTML tags, trims whitespace, and applies the minimum-length
     * filter. Strings that look like URLs, file paths, or CSS classes are
     * also excluded.
     *
     * @param string $value The raw string value.
     * @param string[] $texts Collected text snippets (passed by reference).
     */
    private static function collect_text(string $value, array &$texts): void {
        // Strip HTML tags and decode entities.
        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim($text);

        if (strlen($text) < self::MIN_TEXT_LENGTH) {
            return;
        }

        // Skip values that look like file paths, URLs, or CSS.
        if (preg_match('#^(https?://|/|\./)#i', $text)) {
            return;
        }
        if (preg_match('/^[a-zA-Z0-9_-]+\.[a-zA-Z]{2,4}$/', $text)) {
            return;
        }
        // Skip hex colour codes.
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $text)) {
            return;
        }
        // Skip values that are entirely non-word (e.g., symbols, numbers).
        if (!preg_match('/[a-zA-Z\p{L}]{2,}/u', $text)) {
            return;
        }

        $texts[] = $text;
    }
}
