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
 * Schedules pauses of a user's notifications.
 *
 * A pause switches on the user's own "Disable notifications" setting (user.emailstop) at the start
 * time and puts it back how it was at the end time. Moodle's message system already checks that
 * setting, so nothing about how notifications are sent is changed. The user keeps full access.
 *
 * @package    local_timedroles
 * @copyright  2026 Daniel Maloney
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pause_manager {
    /** @var int Waiting for its start time. */
    public const STATUS_SCHEDULED = 0;
    /** @var int Notifications currently paused. */
    public const STATUS_ACTIVE = 1;
    /** @var int Pause has finished (on time or early). */
    public const STATUS_ENDED = 2;
    /** @var int Cancelled before it started. */
    public const STATUS_CANCELLED = 3;

    /** @var string User preference holding the end time while a pause is active (used for the page banner). */
    public const PREF = 'local_timedroles_pauseduntil';

    /**
     * Schedule a pause. Starts straight away if the start time has passed.
     *
     * @param int $userid
     * @param int $timestart
     * @param int $timeend
     * @param string $note
     * @return int new record id
     */
    public static function create(int $userid, int $timestart, int $timeend, string $note = ''): int {
        global $DB, $USER;

        if ($timeend <= $timestart) {
            throw new \coding_exception('timeend must be after timestart');
        }
        if (self::overlaps($userid, $timestart, $timeend)) {
            throw new \moodle_exception('erroroverlap', 'local_timedroles');
        }

        $now = time();
        $record = (object) [
            'userid' => $userid,
            'timestart' => $timestart,
            'timeend' => $timeend,
            'status' => self::STATUS_SCHEDULED,
            'prevemailstop' => 0,
            'note' => \core_text::substr($note, 0, 255),
            'createdby' => $USER->id ?? 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record('local_timedroles_pause', $record);
        event\pause_created::create_from_record($record)->trigger();

        // Tell the user if someone else set this up. Sent before the pause starts, so it gets through.
        if ($record->createdby && $record->createdby != $userid) {
            self::notify(fn() => notifier::pause_scheduled($record));
        }

        if ($timestart <= $now && $timeend > $now) {
            self::activate($record);
        }
        return $record->id;
    }

    /**
     * Whether another scheduled or active pause for the user overlaps these times.
     *
     * @param int $userid
     * @param int $timestart
     * @param int $timeend
     * @param int $excludeid a pause to ignore (the one being edited)
     * @return bool
     */
    public static function overlaps(int $userid, int $timestart, int $timeend, int $excludeid = 0): bool {
        global $DB;
        $select = 'userid = :userid AND id <> :excludeid AND status IN (:s1, :s2)
                   AND timestart < :timeend AND timeend > :timestart';
        return $DB->record_exists_select('local_timedroles_pause', $select, [
            'userid' => $userid,
            'excludeid' => $excludeid,
            's1' => self::STATUS_SCHEDULED,
            's2' => self::STATUS_ACTIVE,
            'timeend' => $timeend,
            'timestart' => $timestart,
        ]);
    }

    /**
     * Start a pause: remember the user's own setting, then switch notifications off.
     *
     * @param \stdClass $record
     */
    public static function activate(\stdClass $record): void {
        global $DB;
        $current = (int) $DB->get_field('user', 'emailstop', ['id' => $record->userid], MUST_EXIST);
        if (!$current) {
            $DB->set_field('user', 'emailstop', 1, ['id' => $record->userid]);
        }
        set_user_preference(self::PREF, $record->timeend, $record->userid);

        $record->prevemailstop = $current;
        $record->status = self::STATUS_ACTIVE;
        $record->timemodified = time();
        $DB->update_record('local_timedroles_pause', (object) [
            'id' => $record->id,
            'prevemailstop' => $record->prevemailstop,
            'status' => $record->status,
            'timemodified' => $record->timemodified,
        ]);
        event\pause_started::create_from_record($record)->trigger();
    }

    /**
     * Finish a pause. If it was active, notifications are switched back on, unless the user had
     * already switched them off themselves before the pause started.
     *
     * @param \stdClass $record
     * @param bool $early true if ended or cancelled by someone before its end time
     */
    public static function finish(\stdClass $record, bool $early): void {
        global $DB;
        $wasactive = (int) $record->status === self::STATUS_ACTIVE;
        if ($wasactive) {
            if (!(int) $record->prevemailstop) {
                $DB->set_field('user', 'emailstop', 0, ['id' => $record->userid]);
            }
            unset_user_preference(self::PREF, $record->userid);
        }

        $record->status = $wasactive ? self::STATUS_ENDED : self::STATUS_CANCELLED;
        $record->timemodified = time();
        $DB->update_record('local_timedroles_pause', (object) [
            'id' => $record->id,
            'status' => $record->status,
            'timemodified' => $record->timemodified,
        ]);
        event\pause_ended::create_from_record($record, ['early' => (int) $early])->trigger();

        // Notifications are back on now, so this one gets through.
        if ($wasactive) {
            self::notify(fn() => notifier::pause_ended($record));
        }
    }

    /**
     * Change the dates or reason of a scheduled or active pause.
     *
     * @param int $id
     * @param int $userid the user the pause must belong to
     * @param int $timestart ignored once the pause has started
     * @param int $timeend
     * @param string $note
     */
    public static function update(int $id, int $userid, int $timestart, int $timeend, string $note = ''): void {
        global $DB, $USER;

        $record = $DB->get_record('local_timedroles_pause', ['id' => $id, 'userid' => $userid], '*', MUST_EXIST);
        $status = (int) $record->status;
        if (!in_array($status, [self::STATUS_SCHEDULED, self::STATUS_ACTIVE])) {
            throw new \moodle_exception('errorpausenotchangeable', 'local_timedroles');
        }
        if ($status === self::STATUS_ACTIVE) {
            $timestart = (int) $record->timestart;
        }
        if ($timeend <= $timestart) {
            throw new \coding_exception('timeend must be after timestart');
        }
        if (self::overlaps($userid, $timestart, $timeend, $id)) {
            throw new \moodle_exception('erroroverlap', 'local_timedroles');
        }

        $old = clone $record;
        $record->timestart = $timestart;
        $record->timeend = $timeend;
        $record->note = \core_text::substr($note, 0, 255);
        $record->timemodified = time();
        $DB->update_record('local_timedroles_pause', $record);
        event\pause_updated::create_from_record($record, [
            'oldtimestart' => (int) $old->timestart,
            'oldtimeend' => (int) $old->timeend,
        ])->trigger();

        if ($status === self::STATUS_ACTIVE) {
            set_user_preference(self::PREF, $timeend, $userid);
        } else if ($USER->id != $userid) {
            self::notify(fn() => notifier::pause_scheduled($record));
        }

        $now = time();
        if ($timeend <= $now) {
            self::finish($record, true);
        } else if ($status === self::STATUS_SCHEDULED && $timestart <= $now) {
            self::activate($record);
        }
    }

    /**
     * End an active pause now, or cancel a scheduled one.
     *
     * @param int $id
     * @param int $userid the user the pause must belong to
     */
    public static function end_now(int $id, int $userid): void {
        global $DB;
        $record = $DB->get_record('local_timedroles_pause', ['id' => $id, 'userid' => $userid], '*', MUST_EXIST);
        if (in_array((int) $record->status, [self::STATUS_SCHEDULED, self::STATUS_ACTIVE])) {
            self::finish($record, true);
        }
    }

    /**
     * Scheduled task work: end pauses that are due, then start ones that are due.
     *
     * @param int|null $now
     * @return array [started count, ended count]
     */
    public static function process_due(?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $started = 0;
        $ended = 0;

        $select = 'status IN (:s1, :s2) AND timeend <= :now';
        $params = ['s1' => self::STATUS_SCHEDULED, 's2' => self::STATUS_ACTIVE, 'now' => $now];
        foreach ($DB->get_records_select('local_timedroles_pause', $select, $params, 'timeend ASC') as $record) {
            self::finish($record, false);
            $ended++;
        }

        $select = 'status = :s AND timestart <= :now AND timeend > :now2';
        $params = ['s' => self::STATUS_SCHEDULED, 'now' => $now, 'now2' => $now];
        foreach ($DB->get_records_select('local_timedroles_pause', $select, $params, 'timestart ASC') as $record) {
            self::activate($record);
            $started++;
        }

        return [$started, $ended];
    }

    /**
     * Delete pauses, switching notifications back on first for any that are active.
     *
     * @param string $select SQL fragment
     * @param array $params
     */
    public static function delete_records_select(string $select, array $params): void {
        global $DB;
        foreach ($DB->get_records_select('local_timedroles_pause', $select, $params) as $record) {
            if ((int) $record->status === self::STATUS_ACTIVE) {
                if (!(int) $record->prevemailstop) {
                    $DB->set_field('user', 'emailstop', 0, ['id' => $record->userid]);
                }
                unset_user_preference(self::PREF, $record->userid);
            }
        }
        $DB->delete_records_select('local_timedroles_pause', $select, $params);
    }

    /**
     * Status label.
     *
     * @param int $status
     * @return string
     */
    public static function status_name(int $status): string {
        $map = [
            self::STATUS_SCHEDULED => 'statusscheduled',
            self::STATUS_ACTIVE => 'statuspaused',
            self::STATUS_ENDED => 'statusended',
            self::STATUS_CANCELLED => 'statuscancelled',
        ];
        return get_string($map[$status] ?? 'statusended', 'local_timedroles');
    }

    /**
     * Run a notification without letting a mail problem stop processing.
     *
     * @param callable $callback
     */
    protected static function notify(callable $callback): void {
        try {
            $callback();
        } catch (\Throwable $e) {
            debugging('local_timedroles notification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
