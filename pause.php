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
 * Pause a user's notifications for a set period.
 *
 * @package    local_timedroles
 * @copyright  2026 Daniel Maloney
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use local_timedroles\pause_manager;
use local_timedroles\form\pause_form;

$userid = optional_param('userid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$pauseid = optional_param('pauseid', 0, PARAM_INT);

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests cannot pause notifications');
}

$userid = $userid ?: (int) $USER->id;
$user = core_user::get_user($userid, '*', MUST_EXIST);
if ($user->deleted || isguestuser($user)) {
    throw new moodle_exception('invaliduser');
}
$own = ((int) $user->id === (int) $USER->id);
$usercontext = context_user::instance($user->id);
require_capability($own ? 'local/timedroles:pauseown' : 'local/timedroles:pauseothers', $usercontext);

$url = new moodle_url('/local/timedroles/pause.php', ['userid' => $user->id]);
$PAGE->set_url($url);
$PAGE->set_context($usercontext);
$PAGE->set_pagelayout('admin');
$title = $own ? get_string('pausenotifications', 'local_timedroles')
    : get_string('pausenotificationsfor', 'local_timedroles', fullname($user));
$PAGE->set_title($title);
$PAGE->set_heading(fullname($user));
$PAGE->navbar->add(get_string('preferences'), new moodle_url('/user/preferences.php', ['userid' => $user->id]));
$PAGE->navbar->add(get_string('pausenotifications', 'local_timedroles'), $url);

if ($action === 'end' && $pauseid) {
    require_sesskey();
    pause_manager::end_now($pauseid, $user->id);
    redirect($url, get_string('pauseendednow', 'local_timedroles'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$record = null;
$formurl = $url;
if ($action === 'edit' && $pauseid) {
    $record = $DB->get_record('local_timedroles_pause', ['id' => $pauseid, 'userid' => $user->id], '*', MUST_EXIST);
    if (!in_array((int) $record->status, [pause_manager::STATUS_SCHEDULED, pause_manager::STATUS_ACTIVE])) {
        redirect(
            $url,
            get_string('errorpausenotchangeable', 'local_timedroles'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }
    $formurl = new moodle_url($url, ['action' => 'edit', 'pauseid' => $record->id]);
}

$form = new pause_form($formurl, ['record' => $record, 'userid' => $user->id]);
if ($record) {
    $form->set_data(['timestart' => $record->timestart, 'timeend' => $record->timeend, 'note' => $record->note]);
}

if ($form->is_cancelled()) {
    redirect($record ? $url : new moodle_url('/user/preferences.php', ['userid' => $user->id]));
} else if ($data = $form->get_data()) {
    if ($record) {
        pause_manager::update(
            $record->id,
            $user->id,
            (int) ($data->timestart ?? $record->timestart),
            (int) $data->timeend,
            (string) ($data->note ?? '')
        );
        $msg = get_string('pauseupdated', 'local_timedroles');
    } else {
        pause_manager::create($user->id, (int) $data->timestart, (int) $data->timeend, (string) ($data->note ?? ''));
        $msg = get_string('pausecreated', 'local_timedroles');
    }
    redirect($url, $msg, null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo html_writer::div(get_string($own ? 'pauseintro' : 'pauseintroother', 'local_timedroles'), 'mb-3');

$fmt = get_string('strftimedatetime', 'langconfig');
$active = $DB->get_record('local_timedroles_pause', ['userid' => $user->id, 'status' => pause_manager::STATUS_ACTIVE]);
if ($active) {
    echo $OUTPUT->notification(get_string('pausecurrentlyactive', 'local_timedroles', userdate($active->timeend, $fmt)), 'info');
} else if (!$record && !empty($user->emailstop)) {
    echo $OUTPUT->notification(get_string('pausealreadyoff', 'local_timedroles'), 'warning');
}

$form->display();

if (!$record) {
    $pauses = $DB->get_records('local_timedroles_pause', ['userid' => $user->id], 'status ASC, timestart DESC', '*', 0, 50);
    echo $OUTPUT->heading(get_string('pauselist', 'local_timedroles'), 3);
    if (!$pauses) {
        echo $OUTPUT->notification(get_string('pausenone', 'local_timedroles'), 'info');
    } else {
        $creators = $DB->get_records_list('user', 'id', array_unique(array_column($pauses, 'createdby')));
        $table = new html_table();
        $table->head = [
            get_string('pausestart', 'local_timedroles'), get_string('pauseend', 'local_timedroles'),
            get_string('status'), get_string('pausecreatedby', 'local_timedroles'),
            get_string('pausenote', 'local_timedroles'), get_string('actions'),
        ];
        foreach ($pauses as $p) {
            $actions = '';
            $status = (int) $p->status;
            if (in_array($status, [pause_manager::STATUS_SCHEDULED, pause_manager::STATUS_ACTIVE])) {
                $editurl = new moodle_url($url, ['action' => 'edit', 'pauseid' => $p->id]);
                $endurl = new moodle_url($url, ['action' => 'end', 'pauseid' => $p->id, 'sesskey' => sesskey()]);
                $endkey = $status === pause_manager::STATUS_ACTIVE ? 'pauseendnow' : 'pausecancel';
                $actions = html_writer::link($editurl, get_string('edit')) . ' | ' . $OUTPUT->action_link(
                    $endurl,
                    get_string($endkey, 'local_timedroles'),
                    new confirm_action(get_string($endkey . 'confirm', 'local_timedroles'))
                );
            }
            $table->data[] = [
                userdate($p->timestart, $fmt),
                userdate($p->timeend, $fmt),
                pause_manager::status_name($status),
                isset($creators[$p->createdby]) ? fullname($creators[$p->createdby]) : '-',
                s($p->note),
                $actions,
            ];
        }
        echo html_writer::table($table);
    }
}

echo $OUTPUT->footer();
