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

namespace local_timedroles;

/**
 * Sends notifications about timed roles, in each recipient's own language and timezone.
 *
 * @package    local_timedroles
 * @copyright  2026 Daniel Maloney
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifier {
    /**
     * Whether a notification setting is on. Unset counts as on, so it works before an admin saves settings.
     *
     * @param string $name
     * @return bool
     */
    protected static function enabled(string $name): bool {
        $value = get_config('local_timedroles', $name);
        return $value === false ? true : (bool) $value;
    }

    /**
     * The role has just been assigned.
     *
     * @param \stdClass $record
     */
    public static function role_started(\stdClass $record): void {
        if (self::enabled('notifystart')) {
            self::send('rolestarted', (int) $record->userid, $record, 'msgstarted');
        }
    }

    /**
     * The role has just been removed.
     *
     * @param \stdClass $record
     * @param bool $revoked true if ended early
     */
    public static function role_ended(\stdClass $record, bool $revoked): void {
        if (self::enabled('notifyend')) {
            self::send('roleended', (int) $record->userid, $record, $revoked ? 'msgrevoked' : 'msgexpired');
        }
        // Whoever ends a role early already knows, so the assigner is only told about automatic expiry.
        if (!$revoked && self::enabled('notifyassigner') && $record->assignedby && $record->assignedby != $record->userid) {
            self::send('assignerexpired', (int) $record->assignedby, $record, 'msgassignerexpired');
        }
    }

    /**
     * The end date of an active role has been changed.
     *
     * @param \stdClass $record the record after the change
     * @param \stdClass $old the record before the change
     */
    public static function role_changed(\stdClass $record, \stdClass $old): void {
        if (self::enabled('notifychange')) {
            self::send('rolechanged', (int) $record->userid, $record, 'msgchanged', ['oldtimeend' => (int) $old->timeend]);
        }
    }

    /**
     * Build and send one notification.
     *
     * @param string $provider message provider name
     * @param int $touserid
     * @param \stdClass $record
     * @param string $stringkey prefix for the subject/body strings
     * @param int[] $extratimes extra timestamps to format for the message, keyed by placeholder name
     */
    protected static function send(
        string $provider,
        int $touserid,
        \stdClass $record,
        string $stringkey,
        array $extratimes = []
    ): void {
        global $DB;

        $to = \core_user::get_user($touserid);
        $holder = \core_user::get_user($record->userid);
        $course = $DB->get_record('course', ['id' => $record->courseid]);
        $role = $DB->get_record('role', ['id' => $record->roleid]);
        if (!$to || $to->deleted || $to->suspended || !$holder || !$course || !$role) {
            return;
        }

        $context = \context_course::instance($course->id);
        $tz = \core_date::get_user_timezone($to);
        $sm = get_string_manager();
        $lang = $to->lang ?: null;
        $fmt = $sm->get_string('strftimedatetime', 'langconfig', null, $lang);
        $url = new \moodle_url('/course/view.php', ['id' => $course->id]);

        $a = (object) [
            'role' => role_get_name($role, $context),
            'course' => format_string($course->fullname, true, ['context' => $context]),
            'user' => fullname($holder),
            'timestart' => userdate($record->timestart, $fmt, $tz),
            'timeend' => userdate($record->timeend, $fmt, $tz),
            'url' => $url->out(false),
        ];
        foreach ($extratimes as $key => $time) {
            $a->$key = userdate($time, $fmt, $tz);
        }

        $subject = $sm->get_string($stringkey . 'subject', 'local_timedroles', $a, $lang);
        $body = $sm->get_string($stringkey . 'body', 'local_timedroles', $a, $lang);

        $message = new \core\message\message();
        $message->component = 'local_timedroles';
        $message->name = $provider;
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $to;
        $message->subject = $subject;
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = nl2br(s($body));
        $message->smallmessage = $subject;
        $message->notification = 1;
        $message->contexturl = $a->url;
        $message->contexturlname = $a->course;
        $message->courseid = $course->id;
        message_send($message);
    }
}
