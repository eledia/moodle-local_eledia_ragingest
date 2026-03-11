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

namespace ragingestextractor_resource;

use local_ragingest\content_extractor;

/**
 * Content extractor for mod_resource activities.
 *
 * Handles file-based resources (PDF, TXT, HTML). Reads the main file
 * from Moodle's file storage and returns the raw binary content.
 * PDF parsing is NOT performed here — the RAG service handles it.
 *
 * @package    ragingestextractor_resource
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extractor implements content_extractor {

    /** @var string[] MIME types this extractor can handle. */
    private const SUPPORTED_MIMETYPES = [
        'application/pdf',
        'text/plain',
        'text/html',
    ];

    /**
     * Check whether this extractor supports the given module.
     *
     * @param \cm_info $cm The course module info.
     * @return bool True if this is a resource module.
     */
    public function supports(\cm_info $cm): bool {
        return $cm->modname === 'resource';
    }

    /**
     * Extract the main file from a resource activity.
     *
     * Retrieves the primary file from Moodle file storage, checks its
     * MIME type against the supported list, and returns the raw content.
     *
     * @param \cm_info $cm The course module info.
     * @return array|null Extracted document data, or null if no suitable file found.
     */
    public function extract(\cm_info $cm): ?array {
        $context = \context_module::instance($cm->id);
        $fs = get_file_storage();

        // Get files in the resource content area (excluding directories).
        $files = $fs->get_area_files(
            $context->id,
            'mod_resource',
            'content',
            0,
            'sortorder DESC, id ASC',
            false
        );

        if (empty($files)) {
            return null;
        }

        // Get the main file (first non-directory file).
        $file = reset($files);

        if (!$file) {
            return null;
        }

        $mimetype = $file->get_mimetype();

        // Only handle supported MIME types.
        if (!in_array($mimetype, self::SUPPORTED_MIMETYPES, true)) {
            return null;
        }

        $content = $file->get_content();

        if (empty($content)) {
            return null;
        }

        return [
            'content' => $content,
            'content_type' => $mimetype,
            'title' => $file->get_filename(),
        ];
    }
}
