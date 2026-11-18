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
 * Manage timed roles in a course.
 *
 * @package    local_timedroles
 * @copyright  2026 Daniel Maloney
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use local_timedroles\manager;
use local_timedroles\form\assign_form;

$courseid = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$recid = optional_param('recid', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/timedroles:manage', $context);
require_capability('moodle/role:assign', $context);

$url = new moodle_url('/local/timedroles/index.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'local_timedroles') . ': ' . format_string($course->shortname));
$PAGE->set_heading(format_string($course->fullname));

if ($action === 'revoke' && $recid) {
    require_sesskey();
    manager::revoke($recid, $context->id);
    redirect($url, get_string('revoked', 'local_timedroles'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Only roles this user is allowed to assign here (respects Define roles > Allow role assignments).
$roles = get_assignable_roles($context, ROLENAME_ALIAS);

$enrolled = get_enrolled_users($context, '', 0, 'u.*', 'u.lastname, u.firstname');
$currentroles = manager::get_current_roles($context, array_keys($enrolled));

$users = [];
foreach ($enrolled as $u) {
    $label = fullname($u) . ($u->email ? ' (' . $u->email . ')' : '');
    // Show current roles in the picker too, so you can also search by role (e.g. type "student").
    $label .= ' — ' . (!empty($currentroles[$u->id]) ? implode(', ', $currentroles[$u->id])
        : get_string('currentroles_none', 'local_timedroles'));
    $users[$u->id] = $label;
}

$form = new assign_form($url, ['users' => $users, 'roles' => $roles, 'currentroles' => $currentroles]);
$PAGE->requires->js_call_amd('local_timedroles/current_roles', 'init', ['id_userid', 'local-timedroles-currentroles']);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
} else if ($data = $form->get_data()) {
    manager::create(
        $course->id,
        (int) $data->userid,
        (int) $data->roleid,
        (int) $data->timestart,
        (int) $data->timeend,
        (string) ($data->note ?? '')
    );
    redirect($url, get_string('created', 'local_timedroles'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_timedroles'));
echo html_writer::div(get_string('intro', 'local_timedroles'), 'mb-3');
if (has_capability('report/log:view', $context)) {
    $logurl = new moodle_url('/report/log/index.php', ['id' => $course->id, 'chooselog' => 1, 'logreader' => 'logstore_standard']);
    echo html_writer::div(html_writer::link($logurl, get_string('viewlogs', 'local_timedroles')), 'mb-3');
}

if (empty($roles)) {
    echo $OUTPUT->notification(get_string('noassignableroles', 'local_timedroles'), 'warning');
} else {
    $form->display();
}

$records = $DB->get_records('local_timedroles', ['contextid' => $context->id], 'status ASC, timeend ASC');
echo $OUTPUT->heading(get_string('currentandpast', 'local_timedroles'), 3);

if (!$records) {
    echo $OUTPUT->notification(get_string('norecords', 'local_timedroles'), 'info');
} else {
    $rolenames = role_get_names($context, ROLENAME_ALIAS, true);
    $userids = array_unique(array_merge(array_column($records, 'userid'), array_column($records, 'assignedby')));
    $people = $DB->get_records_list('user', 'id', $userids);

    $table = new html_table();
    $table->head = [
        get_string('user'), get_string('role'), get_string('timestart', 'local_timedroles'),
        get_string('timeend', 'local_timedroles'), get_string('status'), get_string('assignedby', 'local_timedroles'),
        get_string('note', 'local_timedroles'), get_string('actions'),
    ];
    $fmt = get_string('strftimedatetimeshort', 'langconfig');
    foreach ($records as $r) {
        $actions = '';
        if (in_array((int) $r->status, [manager::STATUS_SCHEDULED, manager::STATUS_ACTIVE])) {
            $revokeurl = new moodle_url($url, ['action' => 'revoke', 'recid' => $r->id, 'sesskey' => sesskey()]);
            $actions = $OUTPUT->action_link(
                $revokeurl,
                get_string('revoke', 'local_timedroles'),
                new confirm_action(get_string('confirmrevoke', 'local_timedroles'))
            );
        }
        $table->data[] = [
            isset($people[$r->userid]) ? fullname($people[$r->userid]) : '-',
            $rolenames[$r->roleid] ?? '-',
            userdate($r->timestart, $fmt),
            userdate($r->timeend, $fmt),
            manager::status_name((int) $r->status),
            isset($people[$r->assignedby]) ? fullname($people[$r->assignedby]) : '-',
            s($r->note),
            $actions,
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
