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

use local_timedroles\pause_manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Form for scheduling or changing a notification pause.
 *
 * @package    local_timedroles
 * @copyright  2026 Daniel Maloney
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pause_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $record = $this->_customdata['record'] ?? null;
        $canstart = !$record || (int) $record->status === pause_manager::STATUS_SCHEDULED;

        $mform->addElement('header', 'pauseheader', get_string($record ? 'pauseeditheader' : 'pauseaddheader', 'local_timedroles'));

        if ($canstart) {
            $mform->addElement('date_time_selector', 'timestart', get_string('pausestart', 'local_timedroles'));
            $mform->setDefault('timestart', time());
        } else {
            $started = userdate($record->timestart, get_string('strftimedatetime', 'langconfig'));
            $mform->addElement(
                'static',
                'timestartdisplay',
                get_string('pausestart', 'local_timedroles'),
                get_string('timestartstarted', 'local_timedroles', $started)
            );
        }

        $mform->addElement('date_time_selector', 'timeend', get_string('pauseend', 'local_timedroles'));
        $mform->setDefault('timeend', time() + WEEKSECS);
        $mform->addHelpButton('timeend', 'pauseend', 'local_timedroles');

        $mform->addElement('text', 'note', get_string('pausenote', 'local_timedroles'), ['size' => 60]);
        $mform->setType('note', PARAM_TEXT);
        $mform->addRule('note', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->add_action_buttons(true, get_string($record ? 'savechanges' : 'pausesave', $record ? 'moodle' : 'local_timedroles'));
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
        $record = $this->_customdata['record'] ?? null;
        $userid = (int) $this->_customdata['userid'];
        $timestart = isset($data['timestart']) ? (int) $data['timestart'] : (int) $record->timestart;

        if ($data['timeend'] <= $timestart) {
            $errors['timeend'] = get_string('errorendbeforestart', 'local_timedroles');
        } else if ($data['timeend'] <= time()) {
            $errors['timeend'] = get_string('errorendinpast', 'local_timedroles');
        } else if (pause_manager::overlaps($userid, $timestart, (int) $data['timeend'], (int) ($record->id ?? 0))) {
            $errors['timeend'] = get_string('erroroverlap', 'local_timedroles');
        }
        return $errors;
    }
}
