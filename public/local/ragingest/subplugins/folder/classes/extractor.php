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

namespace ragingestextractor_folder;

use local_ragingest\content_extractor;

/**
 * Content extractor for mod_folder activities.
 *
 * Extracts the folder intro and the content of all supported files
 * (PDF, plain text, HTML) stored inside the folder. Unsupported
 * file types (images, videos, etc.) are skipped.
 *
 * @package    ragingestextractor_folder
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extractor implements content_extractor {
    /** @var string[] MIME types that can be extracted as text. */
    private const SUPPORTED_MIMETYPES = [
        'application/pdf',
        'text/plain',
        'text/html',
    ];

    /**
     * Check whether this extractor supports the given module.
     *
     * @param \cm_info $cm The course module info.
     * @return bool True if this is a folder module.
     */
    public function supports(\cm_info $cm): bool {
        return $cm->modname === 'folder';
    }

    /**
     * Extract content from a folder activity.
     *
     * Returns the folder intro plus the concatenated content of all
     * supported files. When the folder contains a single file, the
     * original MIME type is preserved. When it contains multiple files
     * or an intro, the content is wrapped in HTML.
     *
     * @param \cm_info $cm The course module info.
     * @return array|null Extracted document data, or null if no content.
     */
    public function extract(\cm_info $cm): ?array {
        global $DB;

        $folder = $DB->get_record('folder', ['id' => $cm->instance], 'id, name, intro', MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $introhtml = '';
        if (!empty($folder->intro)) {
            $introhtml = file_rewrite_pluginfile_urls(
                $folder->intro,
                'pluginfile.php',
                $context->id,
                'mod_folder',
                'intro',
                0,
            );
        }

        // Get all non-directory files in the folder content area.
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            'mod_folder',
            'content',
            0,
            'sortorder, id',
            false,
        );

        $extractedfiles = [];
        foreach ($files as $file) {
            $mimetype = $file->get_mimetype();
            if (!in_array($mimetype, self::SUPPORTED_MIMETYPES, true)) {
                continue;
            }

            $content = $file->get_content();
            if (empty($content)) {
                continue;
            }

            $extractedfiles[] = [
                'filename' => $file->get_filename(),
                'mimetype' => $mimetype,
                'content' => $content,
            ];
        }

        // No intro and no extractable files.
        if (empty($introhtml) && empty($extractedfiles)) {
            return null;
        }

        // Single file, no intro — return as the file's native MIME type.
        if (empty($introhtml) && count($extractedfiles) === 1) {
            $single = reset($extractedfiles);
            return [
                'content' => $single['content'],
                'content_type' => $single['mimetype'],
                'title' => $single['filename'],
            ];
        }

        // Multiple files or intro present — wrap everything as HTML.
        $html = $introhtml;

        foreach ($extractedfiles as $ef) {
            $html .= '<h2>' . htmlspecialchars($ef['filename'], ENT_QUOTES, 'UTF-8') . '</h2>' . "\n";

            if ($ef['mimetype'] === 'text/html') {
                $html .= $ef['content'] . "\n";
            } else if ($ef['mimetype'] === 'text/plain') {
                $html .= '<pre>' . htmlspecialchars($ef['content'], ENT_QUOTES, 'UTF-8') . '</pre>' . "\n";
            } else {
                // PDF or binary — include as-is and let ingestion manager handle.
                // For multi-file folders with PDFs, we include a reference.
                $html .= '<p>[PDF: ' . htmlspecialchars($ef['filename'], ENT_QUOTES, 'UTF-8') . ']</p>' . "\n";
            }
        }

        if (empty($html)) {
            return null;
        }

        return [
            'content' => $html,
            'content_type' => 'text/html',
            'title' => $folder->name,
        ];
    }
}
