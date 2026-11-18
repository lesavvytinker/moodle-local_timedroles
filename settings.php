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
 * Admin settings.
 *
 * @package    local_timedroles
 * @copyright  2026 Daniel Maloney
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_timedroles', get_string('pluginname', 'local_timedroles'));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_heading(
            'local_timedroles/notifyheading',
            get_string('notifications', 'local_timedroles'),
            get_string('notifications_desc', 'local_timedroles')
        ));
        $settings->add(new admin_setting_configcheckbox(
            'local_timedroles/notifystart',
            get_string('notifystart', 'local_timedroles'),
            get_string('notifystart_desc', 'local_timedroles'),
            1
        ));
        $settings->add(new admin_setting_configcheckbox(
            'local_timedroles/notifyend',
            get_string('notifyend', 'local_timedroles'),
            get_string('notifyend_desc', 'local_timedroles'),
            1
        ));
        $settings->add(new admin_setting_configcheckbox(
            'local_timedroles/notifyassigner',
            get_string('notifyassigner', 'local_timedroles'),
            get_string('notifyassigner_desc', 'local_timedroles'),
            1
        ));
    }
}
