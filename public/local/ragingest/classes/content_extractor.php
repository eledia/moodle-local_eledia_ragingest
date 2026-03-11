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
 * Interface for content extractors.
 *
 * Each subplugin (ragingestextractor_*) must implement this interface
 * to provide content extraction for a specific Moodle activity module.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface content_extractor {

    /**
     * Check whether this extractor supports the given course module.
     *
     * @param \cm_info $cm The course module info object.
     * @return bool True if this extractor can handle the module type.
     */
    public function supports(\cm_info $cm): bool;

    /**
     * Extract content from the given course module.
     *
     * @param \cm_info $cm The course module info object.
     * @return array|null Associative array with keys 'content', 'content_type', 'title',
     *                    or null if no content can be extracted.
     */
    public function extract(\cm_info $cm): ?array;
}
