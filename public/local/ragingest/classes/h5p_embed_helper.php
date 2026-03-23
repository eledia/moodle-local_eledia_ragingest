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
 * Helper to resolve H5P placeholders embedded via the text editor.
 *
 * When a teacher inserts H5P content into a rich-text field (e.g., a
 * label or page) via TinyMCE or Atto, the editor stores a
 * {@code <div class="h5p-placeholder">URL</div>} in the HTML.
 * The display filter converts these to iframes at render time, but
 * the stored HTML never contains the actual H5P text.
 *
 * This helper detects those placeholders, resolves each URL to the
 * deployed H5P content record, extracts the educational text via
 * {@see h5p_text_extractor}, and replaces the placeholder inline.
 * If the H5P is not deployed or the file cannot be found, the
 * placeholder is silently stripped.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class h5p_embed_helper {
    /**
     * Resolve H5P placeholders in an HTML string.
     *
     * Finds all {@code <div class="h5p-placeholder"...>URL</div>} blocks,
     * attempts to resolve each URL to the H5P content JSON, extracts
     * text, and replaces the placeholder. Non-resolvable placeholders
     * are stripped entirely.
     *
     * @param string $html The raw HTML content (e.g., from a label intro).
     * @return string The HTML with H5P placeholders replaced or stripped.
     */
    public static function resolve_h5p_placeholders(string $html): string {
        // Match <div class="h5p-placeholder" ...>URL</div>.
        // The URL may be wrapped in whitespace and the div may have extra attributes.
        $pattern = '#<div\b[^>]*class\s*=\s*["\']h5p-placeholder["\'][^>]*>(.*?)</div>#is';

        return preg_replace_callback($pattern, function ($matches) {
            $url = trim(strip_tags($matches[1]));

            if (empty($url)) {
                debugging('[h5p_embed_helper] Empty H5P placeholder found — stripping.', DEBUG_DEVELOPER);
                return '';
            }

            debugging('[h5p_embed_helper] Found H5P placeholder with URL: ' . $url, DEBUG_DEVELOPER);

            $text = self::extract_text_from_url($url);

            if ($text === null || $text === '') {
                // Cannot resolve — strip the placeholder.
                debugging('[h5p_embed_helper] Could not extract text — placeholder stripped.', DEBUG_DEVELOPER);
                return '';
            }

            debugging('[h5p_embed_helper] Extracted ' . strlen($text) . ' chars of H5P text.', DEBUG_DEVELOPER);

            // Return the extracted text wrapped in a paragraph.
            return '<p>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>';
        }, $html);
    }

    /**
     * Attempt to extract text from an H5P content URL.
     *
     * Resolves the URL to a Moodle stored_file, looks up the deployed
     * H5P record via pathnamehash, and extracts text from jsoncontent.
     *
     * @param string $url The pluginfile URL pointing to the .h5p file.
     * @return string|null Extracted text, or null if not resolvable.
     */
    private static function extract_text_from_url(string $url): ?string {
        // Only handle local Moodle URLs that end in .h5p.
        if (!preg_match('/\.h5p(\?|$)/i', $url)) {
            debugging('[h5p_embed_helper] URL does not end in .h5p — skipping.', DEBUG_DEVELOPER);
            return null;
        }

        try {
            $file = self::resolve_stored_file($url);
        } catch (\Exception $e) {
            debugging('[h5p_embed_helper] File resolution failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            debugging('h5p_embed_helper: could not resolve file for URL: ' . $url .
                      ' — ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }

        if ($file === null) {
            debugging('[h5p_embed_helper] Could not find stored file for URL.', DEBUG_DEVELOPER);
            return null;
        }

        debugging('[h5p_embed_helper] Resolved file: ' . $file->get_filename() .
               ' (pathnamehash=' . $file->get_pathnamehash() . ')', DEBUG_DEVELOPER);

        // Look up the deployed H5P content by the file's pathnamehash.
        $h5p = \core_h5p\api::get_content_from_pathnamehash($file->get_pathnamehash());
        if ($h5p === null || empty($h5p->jsoncontent)) {
            debugging('[h5p_embed_helper] H5P not deployed yet (no h5p record found). ' .
                   'View the activity in a browser first to trigger deployment.', DEBUG_DEVELOPER);
            return null;
        }

        debugging('[h5p_embed_helper] Found deployed H5P record (id=' . $h5p->id . '). Extracting text...', DEBUG_DEVELOPER);

        return h5p_text_extractor::extract_text_from_json($h5p->jsoncontent);
    }

    /**
     * Resolve a pluginfile.php URL to a stored_file object.
     *
     * Parses the URL path to extract component, filearea, itemid, and
     * filename, then looks up the file in Moodle's file storage.
     *
     * @param string $url The pluginfile URL.
     * @return \stored_file|null The file, or null if not found.
     */
    private static function resolve_stored_file(string $url): ?\stored_file {
        global $CFG;

        // Parse the URL and extract the path after pluginfile.php or draftfile.php.
        $parsed = parse_url($url);
        if (empty($parsed['path'])) {
            return null;
        }

        $path = $parsed['path'];

        // Find the pluginfile.php part and get everything after it.
        $pluginfilepos = strpos($path, '/pluginfile.php/');
        if ($pluginfilepos === false) {
            return null;
        }

        $relativepath = substr($path, $pluginfilepos + strlen('/pluginfile.php/'));
        $parts = explode('/', $relativepath);

        // Minimum: contextid/component/filearea/itemid/filename.
        if (count($parts) < 5) {
            return null;
        }

        $contextid = (int) array_shift($parts);
        $component = array_shift($parts);
        $filearea = array_shift($parts);

        // The next part could be itemid (numeric) or part of the filepath.
        // For contentbank, the pattern is: contextid/contentbank/public/itemid/filename.
        $itemid = 0;
        if (is_numeric($parts[0])) {
            $itemid = (int) array_shift($parts);
        }

        $filename = array_pop($parts);
        $filepath = '/' . (!empty($parts) ? implode('/', $parts) . '/' : '');

        $fs = get_file_storage();
        $file = $fs->get_file($contextid, $component, $filearea, $itemid, $filepath, $filename);

        return ($file && !$file->is_directory()) ? $file : null;
    }
}
