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
 * Helper class for building deterministic source IDs.
 *
 * Source IDs follow the format: {tenant}:course{courseid}:cmid{cmid}
 * They are stable and deterministic so that re-indexing replaces existing documents.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class source_id_helper {

    /**
     * Build a source ID from a course module info object.
     *
     * @param \cm_info $cm The course module info.
     * @return string The deterministic source ID.
     */
    public static function build(\cm_info $cm): string {
        return self::build_from_ids((int) $cm->course, (int) $cm->id);
    }

    /**
     * Build a source ID from raw course and cmid values.
     *
     * @param int $courseid The course ID.
     * @param int $cmid The course module ID.
     * @return string The deterministic source ID.
     */
    public static function build_from_ids(int $courseid, int $cmid): string {
        $tenant = get_config('local_ragingest', 'tenant_id') ?: 'default';
        return "{$tenant}:course{$courseid}:cmid{$cmid}";
    }
}
