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
$string['editheader'] = 'Change a timed role';
$string['editintro'] = 'Change when this timed role ends, for example to extend it. If it hasn\'t started yet you can also change when it starts. To change the user or the role, end this one and set up a new one.';
$string['errorendbeforestart'] = 'The end time must be after the start time.';
$string['errorendinpast'] = 'The end time must be in the future.';
$string['errornotchangeable'] = 'This timed role has already ended, so it can\'t be changed. Set up a new one instead.';
$string['errornotenrolled'] = 'Choose a user enrolled in this course.';
$string['erroroverlap'] = 'These dates overlap another pause that is already scheduled or running. Change that one instead.';
$string['errorpausenotchangeable'] = 'This pause has already finished, so it can\'t be changed. Schedule a new one instead.';
$string['errorrole'] = 'You can\'t assign that role here.';
$string['eventcreated'] = 'Timed role set up';
$string['eventexpired'] = 'Timed role expired';
$string['eventpausecreated'] = 'Notification pause scheduled';
$string['eventpauseended'] = 'Notification pause ended';
$string['eventpausestarted'] = 'Notification pause started';
$string['eventpauseupdated'] = 'Notification pause changed';
$string['eventrevoked'] = 'Timed role ended early';
$string['eventstarted'] = 'Timed role started';
$string['eventupdated'] = 'Timed role changed';
$string['intro'] = 'Give an enrolled user an extra role until a set date and time. When it ends, the extra role is removed automatically and the user keeps whatever roles they already had.';
$string['messageprovider:assignerexpired'] = 'A timed role you set up has ended';
$string['messageprovider:pauseended'] = 'Your notification pause has ended';
$string['messageprovider:pausescheduled'] = 'Someone has paused your notifications';
$string['messageprovider:rolechanged'] = 'The end date of your timed role has changed';
$string['messageprovider:roleended'] = 'Your timed role has ended';
$string['messageprovider:rolestarted'] = 'You have been given a timed role';
$string['msgassignerexpiredbody'] = 'The {$a->role} role you gave {$a->user} in {$a->course} ended on {$a->timeend}. Their other roles in the course have not changed.

{$a->url}';
$string['msgassignerexpiredsubject'] = '{$a->user}\'s {$a->role} role in {$a->course} has ended';
$string['msgchangedbody'] = 'The end date of your {$a->role} role in {$a->course} has changed. It now ends on {$a->timeend} (it was {$a->oldtimeend}). After that you will go back to your usual role(s) in the course.

{$a->url}';
$string['msgchangedsubject'] = 'Your {$a->role} role in {$a->course} now ends {$a->timeend}';
$string['msgexpiredbody'] = 'Your {$a->role} role in {$a->course} ended on {$a->timeend}. Your other roles in the course have not changed.

{$a->url}';
$string['msgexpiredsubject'] = 'Your {$a->role} role in {$a->course} has ended';
$string['msgpauseendedbody'] = 'Welcome back. Your notification pause has ended and Moodle notifications are switched back on.

You weren\'t sent notifications about anything that happened while they were paused, but it\'s all still in your courses.

{$a->url}';
$string['msgpauseendedsubject'] = 'Your notifications are back on';
$string['msgpausescheduledbody'] = '{$a->by} has paused your Moodle notifications from {$a->timestart} to {$a->timeend}.

While they are paused you won\'t be sent notifications by email or pop-up, but you can still use all your courses as normal. They switch back on automatically at {$a->timeend}.

To change or end the pause: {$a->url}';
$string['msgpausescheduledsubject'] = 'Your notifications will be paused from {$a->timestart} to {$a->timeend}';
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
$string['notifychange'] = 'Notify the user when the end date changes';
$string['notifychange_desc'] = 'Tell the user when the end date of their active timed role is changed, for example when it is extended.';
$string['notifyend'] = 'Notify the user when the role ends';
$string['notifyend_desc'] = 'Tell the user when their timed role expires or is ended early.';
$string['notifypause'] = 'Notify users about notification pauses';
$string['notifypause_desc'] = 'Tell users when someone else schedules or changes a pause of their notifications, and when a pause ends.';
$string['notifystart'] = 'Notify the user when the role starts';
$string['notifystart_desc'] = 'Tell the user when they are given a timed role, including when it ends.';
$string['pauseaddheader'] = 'Schedule a pause';
$string['pausealreadyoff'] = 'Notifications are already switched off in this user\'s notification preferences (Disable notifications). A pause won\'t change that, and they will stay off afterwards.';
$string['pausebanner'] = 'Your notifications are paused until {$a->until}. <a href="{$a->url}">Change or end the pause</a>.';
$string['pausecancel'] = 'Cancel';
$string['pausecancelconfirm'] = 'Cancel this pause? It hasn\'t started yet.';
$string['pausecreated'] = 'Notification pause scheduled.';
$string['pausecreatedby'] = 'Set up by';
$string['pausecurrentlyactive'] = 'Notifications are paused until {$a}.';
$string['pauseeditheader'] = 'Change this pause';
$string['pauseend'] = 'Back on';
$string['pauseend_help'] = 'When notifications switch back on. Move it later to extend the pause, or earlier to shorten it.';
$string['pauseendednow'] = 'Notification pause ended.';
$string['pauseendnow'] = 'Turn back on now';
$string['pauseendnowconfirm'] = 'Switch notifications back on now?';
$string['pauseintro'] = 'Going on leave? Pause your Moodle notifications so you don\'t come back to a full inbox. While paused, Moodle won\'t send you notifications by email or pop-up (forum posts, submissions, grading and so on), but you can still use all your courses as normal. They switch back on automatically at the time you choose. A few things still come through: password resets, and any notifications your site administrator requires everyone to receive.';
$string['pauseintroother'] = 'Pause this person\'s Moodle notifications, for example while they are on leave. While paused, Moodle won\'t send them notifications by email or pop-up, but they keep full access to their courses. They switch back on automatically at the end time, and the person is told about the pause.';
$string['pauselist'] = 'Pauses';
$string['pausenone'] = 'No pauses scheduled.';
$string['pausenote'] = 'Reason (optional)';
$string['pausenotifications'] = 'Pause notifications';
$string['pausenotificationsfor'] = 'Pause notifications for {$a}';
$string['pausesave'] = 'Schedule pause';
$string['pausesnonesite'] = 'No notification pauses are scheduled or running.';
$string['pausesoverview'] = 'Notification pauses';
$string['pausesoverview_desc'] = 'Everyone whose notifications are currently paused or scheduled to be paused. To pause someone\'s notifications, open their profile, go to Preferences, then Pause notifications.';
$string['pausestart'] = 'Pause from';
$string['pauseupdated'] = 'Notification pause updated.';
$string['pluginname'] = 'Timed roles';
$string['privacy:metadata:local_timedroles'] = 'Time-limited role assignments made in courses.';
$string['privacy:metadata:local_timedroles:assignedby'] = 'The user who set up the timed role.';
$string['privacy:metadata:local_timedroles:note'] = 'An optional reason entered when the role was set up.';
$string['privacy:metadata:local_timedroles:roleid'] = 'The role given.';
$string['privacy:metadata:local_timedroles:timeend'] = 'When the role ends.';
$string['privacy:metadata:local_timedroles:timestart'] = 'When the role starts.';
$string['privacy:metadata:local_timedroles:userid'] = 'The user given the role.';
$string['privacy:metadata:local_timedroles_pause'] = 'Scheduled pauses of a user\'s notifications.';
$string['privacy:metadata:local_timedroles_pause:createdby'] = 'The user who scheduled the pause.';
$string['privacy:metadata:local_timedroles_pause:note'] = 'An optional reason entered when the pause was scheduled.';
$string['privacy:metadata:local_timedroles_pause:prevemailstop'] = 'Whether the user had already disabled notifications before the pause started, so it can be put back.';
$string['privacy:metadata:local_timedroles_pause:timeend'] = 'When notifications switch back on.';
$string['privacy:metadata:local_timedroles_pause:timestart'] = 'When the pause starts.';
$string['privacy:metadata:local_timedroles_pause:userid'] = 'The user whose notifications are paused.';
$string['privacy:metadata:preference:pauseduntil'] = 'While notifications are paused, when they will switch back on. Used to show a reminder.';
$string['revoke'] = 'End now';
$string['revoked'] = 'Timed role ended.';
$string['rolelabelinherited'] = '{$a->role} (from {$a->context})';
$string['rolelabeltimed'] = '{$a->role} (timed, until {$a->timeend})';
$string['selectuser'] = 'Search for a user';
$string['statusactive'] = 'Active';
$string['statuscancelled'] = 'Cancelled';
$string['statusended'] = 'Ended';
$string['statusexpired'] = 'Expired';
$string['statuspaused'] = 'Paused now';
$string['statusrevoked'] = 'Ended early';
$string['statusscheduled'] = 'Scheduled';
$string['taskprocess'] = 'Start and end timed roles and notification pauses';
$string['timedroles:manage'] = 'Manage timed roles in a course';
$string['timedroles:pauseothers'] = 'Pause other people\'s notifications';
$string['timedroles:pauseown'] = 'Pause your own notifications';
$string['timeend'] = 'Ends';
$string['timeendedit'] = 'Ends';
$string['timeendedit_help'] = 'Move this later to extend the role, or earlier to shorten it. The user is told about the new end date.';
$string['timestart'] = 'Starts';
$string['timestartstarted'] = '{$a} (already started)';
$string['updated'] = 'Timed role updated.';
$string['viewlogs'] = 'View the audit trail in the course logs';
