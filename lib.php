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

/**
 * Library callbacks.
 *
 * @package    local_timedroles
 * @copyright  2026 Daniel Maloney
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds a "Pause notifications" link to the user's Preferences page (under Miscellaneous).
 *
 * @param navigation_node $navigation
 * @param stdClass $user
 * @param context_user $usercontext
 * @param stdClass $course
 * @param context_course $coursecontext
 */
function local_timedroles_extend_navigation_user_settings(
    navigation_node $navigation,
    stdClass $user,
    context_user $usercontext,
    stdClass $course,
    context_course $coursecontext
): void {
    global $USER;
    if (isguestuser($user) || !empty($user->deleted)) {
        return;
    }
    $capability = ($user->id == $USER->id) ? 'local/timedroles:pauseown' : 'local/timedroles:pauseothers';
    if (!has_capability($capability, $usercontext)) {
        return;
    }
    $navigation->add(
        get_string('pausenotifications', 'local_timedroles'),
        new moodle_url('/local/timedroles/pause.php', ['userid' => $user->id]),
        navigation_node::TYPE_SETTING,
        null,
        'local_timedroles_pause'
    );
}

/**
 * Adds a "Timed roles" link to the course navigation (More menu).
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context $context
 */
function local_timedroles_extend_navigation_course(navigation_node $navigation, stdClass $course, context $context): void {
    if (!has_all_capabilities(['local/timedroles:manage', 'moodle/role:assign'], $context)) {
        return;
    }
    $navigation->add(
        get_string('pluginname', 'local_timedroles'),
        new moodle_url('/local/timedroles/index.php', ['id' => $course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'local_timedroles',
        new pix_icon('i/assignroles', '')
    );
}
