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

namespace ragingestextractor_page;

use local_ragingest\content_extractor;

/**
 * Content extractor for mod_page activities.
 *
 * Extracts the HTML content and title from a page activity.
 * The raw HTML is transmitted without preprocessing — the RAG
 * service handles text extraction and chunking.
 *
 * @package    ragingestextractor_page
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extractor implements content_extractor {

    /**
     * Check whether this extractor supports the given module.
     *
     * @param \cm_info $cm The course module info.
     * @return bool True if this is a page module.
     */
    public function supports(\cm_info $cm): bool {
        return $cm->modname === 'page';
    }

    /**
     * Extract content from a page activity.
     *
     * @param \cm_info $cm The course module info.
     * @return array|null Extracted document data, or null if no content.
     */
    public function extract(\cm_info $cm): ?array {
        global $DB;

        $page = $DB->get_record('page', ['id' => $cm->instance], 'id, name, content', MUST_EXIST);

        if (empty($page->content)) {
            return null;
        }

        return [
            'content' => $page->content,
            'content_type' => 'text/html',
            'title' => $page->name,
        ];
    }
}
