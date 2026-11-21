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
 * Site-wide list of current and upcoming notification pauses.
 *
 * @package    local_timedroles
 * @copyright  2026 Daniel Maloney
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_timedroles\pause_manager;

admin_externalpage_setup('local_timedroles_pauses');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pausesoverview', 'local_timedroles'));
echo html_writer::div(get_string('pausesoverview_desc', 'local_timedroles'), 'mb-3');

$sql = "SELECT p.*
          FROM {local_timedroles_pause} p
          JOIN {user} u ON u.id = p.userid AND u.deleted = 0
         WHERE p.status IN (:s1, :s2)
      ORDER BY p.status DESC, p.timestart ASC";
$pauses = $DB->get_records_sql($sql, ['s1' => pause_manager::STATUS_SCHEDULED, 's2' => pause_manager::STATUS_ACTIVE]);

if (!$pauses) {
    echo $OUTPUT->notification(get_string('pausesnonesite', 'local_timedroles'), 'info');
} else {
    $userids = array_unique(array_merge(array_column($pauses, 'userid'), array_column($pauses, 'createdby')));
    $people = $DB->get_records_list('user', 'id', $userids);
    $fmt = get_string('strftimedatetimeshort', 'langconfig');

    $table = new html_table();
    $table->head = [
        get_string('user'), get_string('pausestart', 'local_timedroles'), get_string('pauseend', 'local_timedroles'),
        get_string('status'), get_string('pausecreatedby', 'local_timedroles'), get_string('pausenote', 'local_timedroles'),
    ];
    foreach ($pauses as $p) {
        $name = isset($people[$p->userid]) ? fullname($people[$p->userid]) : '-';
        $table->data[] = [
            html_writer::link(new moodle_url('/local/timedroles/pause.php', ['userid' => $p->userid]), $name),
            userdate($p->timestart, $fmt),
            userdate($p->timeend, $fmt),
            pause_manager::status_name((int) $p->status),
            isset($people[$p->createdby]) ? fullname($people[$p->createdby]) : '-',
            s($p->note),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
