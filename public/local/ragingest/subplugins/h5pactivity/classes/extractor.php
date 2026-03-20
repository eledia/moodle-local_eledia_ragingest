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

namespace ragingestextractor_h5pactivity;

use local_ragingest\content_extractor;
use local_ragingest\h5p_text_extractor;

/**
 * Content extractor for mod_h5pactivity activities.
 *
 * Extracts educational text from deployed H5P content by reading
 * the {@code h5p.jsoncontent} field and recursively harvesting
 * text values. Returns null if the H5P package has not been
 * deployed yet (no {@code h5p} row exists).
 *
 * @package    ragingestextractor_h5pactivity
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extractor implements content_extractor {
    /**
     * Check whether this extractor supports the given module.
     *
     * @param \cm_info $cm The course module info.
     * @return bool True if this is an h5pactivity module.
     */
    public function supports(\cm_info $cm): bool {
        return $cm->modname === 'h5pactivity';
    }

    /**
     * Extract text content from a deployed H5P activity.
     *
     * Resolves the activity's .h5p package file, looks up the deployed
     * {@code h5p} record via pathnamehash, and extracts text from the
     * content JSON. Returns null when the package has not been deployed.
     *
     * @param \cm_info $cm The course module info.
     * @return array|null Extracted document data, or null if not extractable.
     */
    public function extract(\cm_info $cm): ?array {
        global $DB;

        $activity = $DB->get_record('h5pactivity', ['id' => $cm->instance], 'id, name', MUST_EXIST);

        // Get the .h5p package file from the activity's file area.
        $context = \context_module::instance($cm->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            'mod_h5pactivity',
            'package',
            0,
            'id',
            false,
        );

        $file = reset($files);
        if (!$file) {
            return null;
        }

        // Look up the deployed H5P content via the file's pathnamehash.
        $h5p = \core_h5p\api::get_content_from_pathnamehash($file->get_pathnamehash());
        if ($h5p === null || empty($h5p->jsoncontent)) {
            // Not yet deployed — skip.
            return null;
        }

        $text = h5p_text_extractor::extract_text_from_json($h5p->jsoncontent);

        if ($text === '') {
            return null;
        }

        return [
            'content' => $text,
            'content_type' => 'text/plain',
            'title' => $activity->name,
        ];
    }
}
