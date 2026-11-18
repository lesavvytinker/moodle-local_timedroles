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

namespace local_timedroles\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Form for giving a user a role for a set period.
 *
 * @package    local_timedroles
 * @copyright  2026 Daniel Maloney
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $users = $this->_customdata['users'];
        $roles = $this->_customdata['roles'];

        $mform->addElement('header', 'assignheader', get_string('assignheader', 'local_timedroles'));

        $mform->addElement(
            'autocomplete',
            'userid',
            get_string('user'),
            $users,
            ['noselectionstring' => get_string('selectuser', 'local_timedroles')]
        );
        $mform->addRule('userid', null, 'required', null, 'client');

        // Filled in by amd/src/current_roles.js when a user is picked. The text inside is the no-JS fallback.
        $panel = \html_writer::div(get_string('currentroles_select', 'local_timedroles'), '', [
            'id' => 'local-timedroles-currentroles',
            'aria-live' => 'polite',
            'data-roles' => json_encode((object) ($this->_customdata['currentroles'] ?? [])),
            'data-str-select' => get_string('currentroles_select', 'local_timedroles'),
            'data-str-none' => get_string('currentroles_none', 'local_timedroles'),
        ]);
        $mform->addElement('static', 'currentroles', get_string('currentroles', 'local_timedroles'), $panel);
        $mform->addHelpButton('currentroles', 'currentroles', 'local_timedroles');

        $mform->addElement('select', 'roleid', get_string('role'), $roles);
        $mform->addRule('roleid', null, 'required', null, 'client');

        $mform->addElement('date_time_selector', 'timestart', get_string('timestart', 'local_timedroles'));
        $mform->setDefault('timestart', time());

        $mform->addElement('date_time_selector', 'timeend', get_string('timeend', 'local_timedroles'));
        $mform->setDefault('timeend', time() + WEEKSECS);

        $mform->addElement('text', 'note', get_string('note', 'local_timedroles'), ['size' => 60]);
        $mform->setType('note', PARAM_TEXT);
        $mform->addRule('note', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('note', 'note', 'local_timedroles');

        $this->add_action_buttons(true, get_string('assign', 'local_timedroles'));
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (empty($this->_customdata['users'][$data['userid'] ?? 0])) {
            $errors['userid'] = get_string('errornotenrolled', 'local_timedroles');
        }
        if (empty($this->_customdata['roles'][$data['roleid'] ?? 0])) {
            $errors['roleid'] = get_string('errorrole', 'local_timedroles');
        }
        if ($data['timeend'] <= $data['timestart']) {
            $errors['timeend'] = get_string('errorendbeforestart', 'local_timedroles');
        } else if ($data['timeend'] <= time()) {
            $errors['timeend'] = get_string('errorendinpast', 'local_timedroles');
        }
        return $errors;
    }
}
