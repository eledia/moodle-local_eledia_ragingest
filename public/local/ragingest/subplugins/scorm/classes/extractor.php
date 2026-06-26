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

namespace ragingestextractor_scorm;

use local_ragingest\content_extractor;

/**
 * Content extractor for mod_scorm activities.
 *
 * Extracts the SCORM intro, the table of contents (SCO titles), and
 * the text content of any HTML launch pages from locally-stored
 * SCORM packages.
 *
 * @package    ragingestextractor_scorm
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extractor implements content_extractor {
    /** @var int Maximum HTML size for regex body extraction. */
    private const MAX_BODY_REGEX_BYTES = 2097152;

    /**
     * Check whether this extractor supports the given module.
     *
     * @param \cm_info $cm The course module info.
     * @return bool True if this is a scorm module.
     */
    public function supports(\cm_info $cm): bool {
        return $cm->modname === 'scorm';
    }

    /**
     * Extract content from a SCORM activity.
     *
     * Gathers the activity intro, all SCO titles as a table of contents,
     * and attempts to extract text from locally-stored HTML launch pages.
     *
     * @param \cm_info $cm The course module info.
     * @return array|null Extracted document data, or null if no content.
     */
    public function extract(\cm_info $cm): ?array {
        global $DB;

        $scorm = $DB->get_record(
            'scorm',
            ['id' => $cm->instance],
            'id, name, intro, scormtype',
            MUST_EXIST,
        );
        $context = \core\context\module::instance($cm->id);

        $html = '';

        // SCORM intro.
        if (!empty($scorm->intro)) {
            $html .= file_rewrite_pluginfile_urls(
                $scorm->intro,
                'pluginfile.php',
                $context->id,
                'mod_scorm',
                'intro',
                0,
            );
        }

        // Get all SCOs (learning objects) for this package.
        $scoes = $DB->get_records(
            'scorm_scoes',
            ['scorm' => $scorm->id],
            'sortorder ASC, id ASC',
            'id, title, launch, scormtype',
        );

        if (empty($scoes)) {
            return empty($html) ? null : [
                'content' => $html,
                'content_type' => 'text/html',
                'title' => $scorm->name,
            ];
        }

        // Build a file index for locally-stored packages.
        $filesbypath = [];
        if ($scorm->scormtype === 'local') {
            $fs = get_file_storage();
            $files = $fs->get_area_files(
                $context->id,
                'mod_scorm',
                'content',
                0,
                'sortorder, filepath, filename',
                false,
            );

            foreach ($files as $file) {
                $relpath = ltrim($file->get_filepath() . $file->get_filename(), '/');
                $filesbypath[$relpath] = $file;
            }
        }

        // Output SCO titles and extract HTML content where possible.
        foreach ($scoes as $sco) {
            if (empty($sco->title)) {
                continue;
            }

            $html .= '<h2>' . htmlspecialchars($sco->title, ENT_QUOTES, 'UTF-8') . '</h2>' . "\n";

            // Try to extract text from the launch page if it's an HTML file.
            if (!empty($sco->launch) && !empty($filesbypath)) {
                $launchpath = preg_replace('/[?#].*$/', '', $sco->launch);
                if (isset($filesbypath[$launchpath])) {
                    $file = $filesbypath[$launchpath];
                    $mimetype = $file->get_mimetype();

                    if ($mimetype === 'text/html' || $mimetype === 'application/xhtml+xml') {
                        $content = $file->get_content();
                        if (!empty($content)) {
                            $bodytext = self::extract_body_content($content);
                            // Only include if the body has meaningful text.
                            $plaintext = trim(strip_tags($bodytext));
                            if (!empty($plaintext)) {
                                $html .= $bodytext . "\n";
                            }
                        }
                    }
                }
            }
        }

        if (empty($html)) {
            return null;
        }

        return [
            'content' => $html,
            'content_type' => 'text/html',
            'title' => $scorm->name,
        ];
    }

    /**
     * Extract the body content from a full HTML document.
     *
     * If the content has a <body> tag, returns only its inner HTML.
     * Otherwise returns the content as-is.
     *
     * @param string $html The full HTML document.
     * @return string The body content.
     */
    private static function extract_body_content(string $html): string {
        if (strlen($html) > self::MAX_BODY_REGEX_BYTES) {
            return $html;
        }
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $matches)) {
            return trim($matches[1]);
        }
        return $html;
    }
}
