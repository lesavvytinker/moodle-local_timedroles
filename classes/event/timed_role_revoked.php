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

namespace local_timedroles\event;

/**
 * Event: a timed role was ended early.
 *
 * @package    local_timedroles
 * @copyright  2026 Daniel Maloney
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class timed_role_revoked extends timed_role_base {
    /**
     * Init.
     */
    protected function init() {
        parent::init();
        $this->data['crud'] = 'd';
    }

    /**
     * Name shown in the logs and task list.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventrevoked', 'local_timedroles');
    }

    /**
     * Description shown in the logs.
     *
     * @return string
     */
    public function get_description() {
        $end = date('Y-m-d H:i', $this->other['timeend']);
        return "The user with id '{$this->userid}' ended the timed role with id '{$this->other['roleid']}' early " .
            "for the user with id '{$this->relateduserid}' in the course with id '{$this->courseid}' (was due to end {$end}).";
    }
}
