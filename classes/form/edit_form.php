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

use local_timedroles\manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Form for changing the dates or reason of a timed role.
 *
 * @package    local_timedroles
 * @copyright  2026 Daniel Maloney
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $record = $this->_customdata['record'];
        $scheduled = (int) $record->status === manager::STATUS_SCHEDULED;

        $mform->addElement('header', 'editheader', get_string('editheader', 'local_timedroles'));
        $mform->addElement('static', 'user', get_string('user'), s($this->_customdata['username']));
        $mform->addElement('static', 'role', get_string('role'), $this->_customdata['rolename']);

        if ($scheduled) {
            $mform->addElement('date_time_selector', 'timestart', get_string('timestart', 'local_timedroles'));
        } else {
            $started = userdate($record->timestart, get_string('strftimedatetime', 'langconfig'));
            $mform->addElement(
                'static',
                'timestartdisplay',
                get_string('timestart', 'local_timedroles'),
                get_string('timestartstarted', 'local_timedroles', $started)
            );
        }

        $mform->addElement('date_time_selector', 'timeend', get_string('timeend', 'local_timedroles'));
        $mform->addHelpButton('timeend', 'timeendedit', 'local_timedroles');

        $mform->addElement('text', 'note', get_string('note', 'local_timedroles'), ['size' => 60]);
        $mform->setType('note', PARAM_TEXT);
        $mform->addRule('note', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('note', 'note', 'local_timedroles');

        $this->add_action_buttons(true, get_string('savechanges'));
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
        $record = $this->_customdata['record'];
        $timestart = isset($data['timestart']) ? (int) $data['timestart'] : (int) $record->timestart;
        if ($data['timeend'] <= $timestart) {
            $errors['timeend'] = get_string('errorendbeforestart', 'local_timedroles');
        } else if ($data['timeend'] <= time()) {
            $errors['timeend'] = get_string('errorendinpast', 'local_timedroles');
        }
        return $errors;
    }
}
