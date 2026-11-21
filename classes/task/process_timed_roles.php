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

namespace local_timedroles\task;

use local_timedroles\manager;

/**
 * Starts and ends timed role assignments.
 *
 * @package    local_timedroles
 * @copyright  2026 Daniel Maloney
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_timed_roles extends \core\task\scheduled_task {
    /**
     * Name shown in the logs and task list.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskprocess', 'local_timedroles');
    }

    /**
     * Run the task.
     */
    public function execute(): void {
        [$activated, $expired] = manager::process_due();
        mtrace("local_timedroles: {$activated} timed roles started, {$expired} ended.");
        [$started, $ended] = \local_timedroles\pause_manager::process_due();
        mtrace("local_timedroles: {$started} notification pauses started, {$ended} ended.");
    }
}
