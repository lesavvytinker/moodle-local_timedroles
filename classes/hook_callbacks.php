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

use core\hook\output\before_standard_top_of_body_html_generation;

/**
 * Hook callbacks.
 *
 * @package    local_timedroles
 * @copyright  2026 Daniel Maloney
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Once per session, remind a user whose notifications are paused, with a link to switch them back on.
     *
     * Uses a user preference (already loaded with the session) so there is no extra database query.
     *
     * @param before_standard_top_of_body_html_generation $hook
     */
    public static function before_standard_top_of_body_html_generation(before_standard_top_of_body_html_generation $hook): void {
        global $SESSION;

        if (during_initial_install() || !isloggedin() || isguestuser() || \core\session\manager::is_loggedinas()) {
            return;
        }
        $until = (int) get_user_preferences(pause_manager::PREF, 0);
        if ($until <= time()) {
            return;
        }
        if (($SESSION->local_timedroles_pausenotice ?? 0) === $until) {
            return;
        }
        $SESSION->local_timedroles_pausenotice = $until;

        $url = new \moodle_url('/local/timedroles/pause.php');
        \core\notification::info(get_string('pausebanner', 'local_timedroles', (object) [
            'until' => userdate($until, get_string('strftimedatetime', 'langconfig')),
            'url' => $url->out(false),
        ]));
    }
}
