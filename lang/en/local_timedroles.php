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
 * English strings.
 *
 * @package    local_timedroles
 * @copyright  2026 Daniel Maloney
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actions'] = 'Actions';
$string['assign'] = 'Assign timed role';
$string['assignedby'] = 'Assigned by';
$string['assignheader'] = 'Give a user a role for a set period';
$string['confirmrevoke'] = 'End this timed role now? The user keeps their other roles.';
$string['created'] = 'Timed role saved.';
$string['currentandpast'] = 'Timed roles in this course';
$string['currentroles'] = 'Current roles';
$string['currentroles_help'] = 'The roles this user has in the course right now, including any inherited from the category or site, and any timed roles already in place. These are the roles they go back to when the new timed role ends.';
$string['currentroles_none'] = 'No roles in this course';
$string['currentroles_select'] = 'Choose a user to see their current roles.';
$string['errorendbeforestart'] = 'The end time must be after the start time.';
$string['errorendinpast'] = 'The end time must be in the future.';
$string['errornotenrolled'] = 'Choose a user enrolled in this course.';
$string['errorrole'] = 'You can\'t assign that role here.';
$string['eventcreated'] = 'Timed role set up';
$string['eventexpired'] = 'Timed role expired';
$string['eventrevoked'] = 'Timed role ended early';
$string['eventstarted'] = 'Timed role started';
$string['intro'] = 'Give an enrolled user an extra role until a set date and time. When it ends, the extra role is removed automatically and the user keeps whatever roles they already had.';
$string['messageprovider:assignerexpired'] = 'A timed role you set up has ended';
$string['messageprovider:roleended'] = 'Your timed role has ended';
$string['messageprovider:rolestarted'] = 'You have been given a timed role';
$string['msgassignerexpiredbody'] = 'The {$a->role} role you gave {$a->user} in {$a->course} ended on {$a->timeend}. Their other roles in the course have not changed.

{$a->url}';
$string['msgassignerexpiredsubject'] = '{$a->user}\'s {$a->role} role in {$a->course} has ended';
$string['msgexpiredbody'] = 'Your {$a->role} role in {$a->course} ended on {$a->timeend}. Your other roles in the course have not changed.

{$a->url}';
$string['msgexpiredsubject'] = 'Your {$a->role} role in {$a->course} has ended';
$string['msgrevokedbody'] = 'Your {$a->role} role in {$a->course} has been ended early. It was due to end on {$a->timeend}. Your other roles in the course have not changed.

{$a->url}';
$string['msgrevokedsubject'] = 'Your {$a->role} role in {$a->course} has ended';
$string['msgstartedbody'] = 'You have been given the {$a->role} role in {$a->course} until {$a->timeend}. After that you will go back to your usual role(s) in the course.

{$a->url}';
$string['msgstartedsubject'] = 'You have the {$a->role} role in {$a->course} until {$a->timeend}';
$string['noassignableroles'] = 'There are no roles you are allowed to assign in this course.';
$string['norecords'] = 'No timed roles have been set up in this course yet.';
$string['note'] = 'Reason (optional)';
$string['note_help'] = 'A short note for the record, e.g. "Covering marking while Sam is on leave". Shown to other course managers.';
$string['notifications'] = 'Notifications';
$string['notifications_desc'] = 'Users can still turn individual notifications off in their own notification preferences.';
$string['notifyassigner'] = 'Notify the person who set it up';
$string['notifyassigner_desc'] = 'Tell the person who set up a timed role when it expires automatically.';
$string['notifyend'] = 'Notify the user when the role ends';
$string['notifyend_desc'] = 'Tell the user when their timed role expires or is ended early.';
$string['notifystart'] = 'Notify the user when the role starts';
$string['notifystart_desc'] = 'Tell the user when they are given a timed role, including when it ends.';
$string['pluginname'] = 'Timed roles';
$string['privacy:metadata:local_timedroles'] = 'Time-limited role assignments made in courses.';
$string['privacy:metadata:local_timedroles:assignedby'] = 'The user who set up the timed role.';
$string['privacy:metadata:local_timedroles:note'] = 'An optional reason entered when the role was set up.';
$string['privacy:metadata:local_timedroles:roleid'] = 'The role given.';
$string['privacy:metadata:local_timedroles:timeend'] = 'When the role ends.';
$string['privacy:metadata:local_timedroles:timestart'] = 'When the role starts.';
$string['privacy:metadata:local_timedroles:userid'] = 'The user given the role.';
$string['revoke'] = 'End now';
$string['revoked'] = 'Timed role ended.';
$string['rolelabelinherited'] = '{$a->role} (from {$a->context})';
$string['rolelabeltimed'] = '{$a->role} (timed, until {$a->timeend})';
$string['selectuser'] = 'Search for a user';
$string['statusactive'] = 'Active';
$string['statusexpired'] = 'Expired';
$string['statusrevoked'] = 'Ended early';
$string['statusscheduled'] = 'Scheduled';
$string['taskprocess'] = 'Start and end timed roles';
$string['timedroles:manage'] = 'Manage timed roles in a course';
$string['timeend'] = 'Ends';
$string['timestart'] = 'Starts';
$string['viewlogs'] = 'View the audit trail in the course logs';
