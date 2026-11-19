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
 * Creates, activates and ends timed role assignments.
 *
 * Roles are assigned with component 'local_timedroles' and itemid = record id, so each timed
 * assignment is its own role_assignments row. Removing it never touches the user's normal
 * (manual or enrolment-owned) role assignments, even for the same role.
 *
 * @package    local_timedroles
 * @copyright  2026 Daniel Maloney
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var int Waiting for its start time. */
    public const STATUS_SCHEDULED = 0;
    /** @var int Role currently assigned. */
    public const STATUS_ACTIVE = 1;
    /** @var int Ended automatically at timeend. */
    public const STATUS_EXPIRED = 2;
    /** @var int Ended early by a teacher/manager. */
    public const STATUS_REVOKED = 3;

    /** @var string Component used for role assignments. */
    public const COMPONENT = 'local_timedroles';

    /**
     * Create a timed role assignment. Activates immediately if the start time has passed.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $roleid
     * @param int $timestart
     * @param int $timeend
     * @param string $note
     * @return int new record id
     */
    public static function create(
        int $courseid,
        int $userid,
        int $roleid,
        int $timestart,
        int $timeend,
        string $note = ''
    ): int {
        global $DB, $USER;

        if ($timeend <= $timestart) {
            throw new \coding_exception('timeend must be after timestart');
        }
        $context = \context_course::instance($courseid);
        $now = time();

        $record = (object) [
            'courseid' => $courseid,
            'contextid' => $context->id,
            'userid' => $userid,
            'roleid' => $roleid,
            'timestart' => $timestart,
            'timeend' => $timeend,
            'status' => self::STATUS_SCHEDULED,
            'note' => \core_text::substr($note, 0, 255),
            'assignedby' => $USER->id ?? 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record('local_timedroles', $record);
        event\timed_role_created::create_from_record($record)->trigger();

        if ($timestart <= $now && $timeend > $now) {
            self::activate($record);
        }
        return $record->id;
    }

    /**
     * Assign the role for a scheduled record.
     *
     * @param \stdClass $record
     */
    public static function activate(\stdClass $record): void {
        global $DB;
        role_assign($record->roleid, $record->userid, $record->contextid, self::COMPONENT, $record->id);
        $record->status = self::STATUS_ACTIVE;
        $record->timemodified = time();
        $DB->update_record('local_timedroles', (object) [
            'id' => $record->id,
            'status' => $record->status,
            'timemodified' => $record->timemodified,
        ]);
        event\timed_role_started::create_from_record($record)->trigger();
        self::notify(fn() => notifier::role_started($record));
    }

    /**
     * Run a notification without letting a mail problem stop role processing.
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

    /**
     * Remove the role for a record and mark it expired or revoked.
     *
     * @param \stdClass $record
     * @param int $status STATUS_EXPIRED or STATUS_REVOKED
     */
    public static function finish(\stdClass $record, int $status): void {
        global $DB;
        // Only removes the row this plugin created; other assignments of the same role are untouched.
        $wasactive = (int) $record->status === self::STATUS_ACTIVE;
        role_unassign($record->roleid, $record->userid, $record->contextid, self::COMPONENT, $record->id);
        $record->status = $status;
        $record->timemodified = time();
        $DB->update_record('local_timedroles', (object) [
            'id' => $record->id,
            'status' => $record->status,
            'timemodified' => $record->timemodified,
        ]);

        $revoked = $status === self::STATUS_REVOKED;
        if ($revoked) {
            event\timed_role_revoked::create_from_record($record)->trigger();
        } else {
            event\timed_role_expired::create_from_record($record)->trigger();
        }
        // Only tell people a role has ended if it was ever actually in place.
        if ($wasactive) {
            self::notify(fn() => notifier::role_ended($record, $revoked));
        }
    }

    /**
     * End a timed role early. Checks the record belongs to the given context.
     *
     * @param int $id
     * @param int $contextid
     */
    public static function revoke(int $id, int $contextid): void {
        global $DB;
        $record = $DB->get_record('local_timedroles', ['id' => $id, 'contextid' => $contextid], '*', MUST_EXIST);
        if (in_array((int) $record->status, [self::STATUS_SCHEDULED, self::STATUS_ACTIVE])) {
            self::finish($record, self::STATUS_REVOKED);
        }
    }

    /**
     * Change the dates or reason of a scheduled or active timed role.
     *
     * The start time can only change while the role is still scheduled. Moving the start time
     * into the past starts the role straight away; moving the end time into the past ends it.
     *
     * @param int $id
     * @param int $contextid the course context the record must belong to
     * @param int $timestart ignored once the role has started
     * @param int $timeend
     * @param string $note
     */
    public static function update(int $id, int $contextid, int $timestart, int $timeend, string $note = ''): void {
        global $DB;

        $record = $DB->get_record('local_timedroles', ['id' => $id, 'contextid' => $contextid], '*', MUST_EXIST);
        $status = (int) $record->status;
        if (!in_array($status, [self::STATUS_SCHEDULED, self::STATUS_ACTIVE])) {
            throw new \moodle_exception('errornotchangeable', 'local_timedroles');
        }
        if ($status === self::STATUS_ACTIVE) {
            $timestart = (int) $record->timestart;
        }
        if ($timeend <= $timestart) {
            throw new \coding_exception('timeend must be after timestart');
        }

        $old = clone $record;
        $record->timestart = $timestart;
        $record->timeend = $timeend;
        $record->note = \core_text::substr($note, 0, 255);
        $record->timemodified = time();
        $DB->update_record('local_timedroles', $record);

        event\timed_role_updated::create_from_record($record, [
            'oldtimestart' => (int) $old->timestart,
            'oldtimeend' => (int) $old->timeend,
        ])->trigger();

        $now = time();
        if ($timeend <= $now) {
            self::finish($record, self::STATUS_EXPIRED);
        } else if ($status === self::STATUS_SCHEDULED && $timestart <= $now) {
            self::activate($record);
        } else if ($status === self::STATUS_ACTIVE && (int) $old->timeend !== $timeend) {
            self::notify(fn() => notifier::role_changed($record, $old));
        }
    }

    /**
     * Scheduled task work: start due assignments, end expired ones.
     *
     * @param int|null $now
     * @return array [activated count, expired count]
     */
    public static function process_due(?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $activated = 0;
        $expired = 0;

        // End anything past its end time (active, or scheduled that never started).
        $select = 'status IN (:s1, :s2) AND timeend <= :now';
        $params = ['s1' => self::STATUS_SCHEDULED, 's2' => self::STATUS_ACTIVE, 'now' => $now];
        foreach ($DB->get_records_select('local_timedroles', $select, $params) as $record) {
            self::finish($record, self::STATUS_EXPIRED);
            $expired++;
        }

        // Start scheduled assignments whose start time has arrived.
        $select = 'status = :s AND timestart <= :now AND timeend > :now2';
        $params = ['s' => self::STATUS_SCHEDULED, 'now' => $now, 'now2' => $now];
        foreach ($DB->get_records_select('local_timedroles', $select, $params) as $record) {
            self::activate($record);
            $activated++;
        }

        return [$activated, $expired];
    }

    /**
     * Delete records, removing any role assignments still in place first.
     *
     * @param string $select SQL fragment
     * @param array $params
     */
    public static function delete_records_select(string $select, array $params): void {
        global $DB;
        foreach ($DB->get_records_select('local_timedroles', $select, $params) as $record) {
            if ((int) $record->status === self::STATUS_ACTIVE) {
                role_unassign($record->roleid, $record->userid, $record->contextid, self::COMPONENT, $record->id);
            }
        }
        $DB->delete_records_select('local_timedroles', $select, $params);
    }

    /**
     * The roles each user currently has in a course, as plain-text labels.
     *
     * Timed roles from this plugin are labelled with their end time; roles inherited from a
     * category or the site are labelled with where they come from.
     *
     * @param \context_course $context
     * @param int[] $userids
     * @return array userid => string[] role labels (users with no roles are left out)
     */
    public static function get_current_roles(\context_course $context, array $userids): array {
        global $DB;
        if (!$userids) {
            return [];
        }

        // Course-level roles plus any inherited from the category or site (e.g. a category manager).
        [$ctxsql, $ctxparams] = $DB->get_in_or_equal($context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'ctx');
        [$usql, $uparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        $sql = "SELECT ra.id, ra.userid, ra.roleid, ra.contextid, ra.component, tr.timeend
                  FROM {role_assignments} ra
             LEFT JOIN {local_timedroles} tr ON ra.component = :component AND tr.id = ra.itemid
                 WHERE ra.contextid $ctxsql AND ra.userid $usql
              ORDER BY ra.userid, ra.roleid";
        $params = ['component' => self::COMPONENT] + $ctxparams + $uparams;

        $rolenames = role_get_names($context, ROLENAME_ALIAS, true);
        $fmt = get_string('strftimedatetimeshort', 'langconfig');
        $plain = fn(string $s): string => html_entity_decode(strip_tags($s), ENT_QUOTES, 'UTF-8');
        $map = [];

        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $ra) {
            $name = $plain($rolenames[$ra->roleid] ?? '?');
            if ($ra->component === self::COMPONENT && $ra->timeend) {
                $name = get_string(
                    'rolelabeltimed',
                    'local_timedroles',
                    (object) ['role' => $name, 'timeend' => userdate($ra->timeend, $fmt)]
                );
            } else if ((int) $ra->contextid !== (int) $context->id) {
                $from = \context::instance_by_id($ra->contextid, IGNORE_MISSING);
                $name = get_string(
                    'rolelabelinherited',
                    'local_timedroles',
                    (object) ['role' => $name, 'context' => $from ? $plain($from->get_context_name()) : '?']
                );
            }
            $map[(int) $ra->userid][] = $name;
        }
        $rs->close();

        return array_map(fn($names) => array_values(array_unique($names)), $map);
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
            self::STATUS_ACTIVE => 'statusactive',
            self::STATUS_EXPIRED => 'statusexpired',
            self::STATUS_REVOKED => 'statusrevoked',
        ];
        return get_string($map[$status] ?? 'statusexpired', 'local_timedroles');
    }
}
