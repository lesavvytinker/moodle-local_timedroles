<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_timedroles;

/**
 * Event observers that keep the table tidy. Core already removes the role assignments
 * themselves in these cases, so only our records need deleting.
 *
 * @package    local_timedroles
 * @copyright  2026 Daniel Maloney
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Remove records for a deleted course.
     *
     * @param \core\event\course_deleted $event
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;
        $DB->delete_records('local_timedroles', ['courseid' => $event->objectid]);
    }

    /**
     * Remove records for a deleted role.
     *
     * @param \core\event\role_deleted $event
     */
    public static function role_deleted(\core\event\role_deleted $event): void {
        global $DB;
        $DB->delete_records('local_timedroles', ['roleid' => $event->objectid]);
    }

    /**
     * Remove records for a deleted user and anonymise ones they set up.
     *
     * @param \core\event\user_deleted $event
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;
        $DB->delete_records('local_timedroles', ['userid' => $event->objectid]);
        $DB->set_field('local_timedroles', 'assignedby', 0, ['assignedby' => $event->objectid]);
        $DB->delete_records('local_timedroles_pause', ['userid' => $event->objectid]);
        $DB->set_field('local_timedroles_pause', 'createdby', 0, ['createdby' => $event->objectid]);
    }
}
