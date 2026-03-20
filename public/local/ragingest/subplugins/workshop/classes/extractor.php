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

namespace ragingestextractor_workshop;

use local_ragingest\content_extractor;

/**
 * Content extractor for mod_workshop activities.
 *
 * Extracts the workshop intro, author/reviewer instructions, and
 * conclusion. No student submissions or assessments are included.
 *
 * @package    ragingestextractor_workshop
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extractor implements content_extractor {
    /**
     * Check whether this extractor supports the given module.
     *
     * @param \cm_info $cm The course module info.
     * @return bool True if this is a workshop module.
     */
    public function supports(\cm_info $cm): bool {
        return $cm->modname === 'workshop';
    }

    /**
     * Extract content from a workshop activity.
     *
     * @param \cm_info $cm The course module info.
     * @return array|null Extracted document data, or null if no content.
     */
    public function extract(\cm_info $cm): ?array {
        global $DB;

        $workshop = $DB->get_record('workshop', ['id' => $cm->instance],
            'id, name, intro, instructauthors, instructreviewers, conclusion', MUST_EXIST);

        $context = \context_module::instance($cm->id);
        $parts = [];

        $fields = [
            'intro' => 'intro',
            'instructauthors' => 'instructauthors',
            'instructreviewers' => 'instructreviewers',
            'conclusion' => 'conclusion',
        ];

        foreach ($fields as $dbfield => $filearea) {
            if (!empty($workshop->$dbfield)) {
                $parts[] = file_rewrite_pluginfile_urls(
                    $workshop->$dbfield, 'pluginfile.php', $context->id,
                    'mod_workshop', $filearea, 0,
                );
            }
        }

        if (empty($parts)) {
            return null;
        }

        return [
            'content' => implode("\n", $parts),
            'content_type' => 'text/html',
            'title' => $workshop->name,
        ];
    }
}
