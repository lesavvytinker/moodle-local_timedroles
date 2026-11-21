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

namespace local_timedroles\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_timedroles\manager;
use local_timedroles\pause_manager;

/**
 * Privacy provider.
 *
 * @package    local_timedroles
 * @copyright  2026 Daniel Maloney
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\user_preference_provider {
    /**
     * Describe the personal data this plugin stores.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_timedroles', [
            'userid' => 'privacy:metadata:local_timedroles:userid',
            'roleid' => 'privacy:metadata:local_timedroles:roleid',
            'timestart' => 'privacy:metadata:local_timedroles:timestart',
            'timeend' => 'privacy:metadata:local_timedroles:timeend',
            'note' => 'privacy:metadata:local_timedroles:note',
            'assignedby' => 'privacy:metadata:local_timedroles:assignedby',
        ], 'privacy:metadata:local_timedroles');
        $collection->add_database_table('local_timedroles_pause', [
            'userid' => 'privacy:metadata:local_timedroles_pause:userid',
            'timestart' => 'privacy:metadata:local_timedroles_pause:timestart',
            'timeend' => 'privacy:metadata:local_timedroles_pause:timeend',
            'prevemailstop' => 'privacy:metadata:local_timedroles_pause:prevemailstop',
            'note' => 'privacy:metadata:local_timedroles_pause:note',
            'createdby' => 'privacy:metadata:local_timedroles_pause:createdby',
        ], 'privacy:metadata:local_timedroles_pause');
        $collection->add_user_preference(pause_manager::PREF, 'privacy:metadata:preference:pauseduntil');
        return $collection;
    }

    /**
     * Contexts holding data about the user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT contextid FROM {local_timedroles} WHERE userid = :userid OR assignedby = :assignedby";
        $contextlist->add_from_sql($sql, ['userid' => $userid, 'assignedby' => $userid]);

        // Pauses live in the paused user's own context.
        $sql = "SELECT ctx.id
                  FROM {local_timedroles_pause} p
                  JOIN {context} ctx ON ctx.instanceid = p.userid AND ctx.contextlevel = :ctxuser
                 WHERE p.userid = :userid OR p.createdby = :createdby";
        $contextlist->add_from_sql($sql, ['ctxuser' => CONTEXT_USER, 'userid' => $userid, 'createdby' => $userid]);
        return $contextlist;
    }

    /**
     * Users with data in the given context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel == CONTEXT_USER) {
            $params = ['userid' => $context->instanceid];
            $userlist->add_from_sql('userid', "SELECT userid FROM {local_timedroles_pause} WHERE userid = :userid", $params);
            $userlist->add_from_sql(
                'createdby',
                "SELECT createdby FROM {local_timedroles_pause} WHERE userid = :userid AND createdby > 0",
                $params
            );
            return;
        }
        $params = ['contextid' => $userlist->get_context()->id];
        $userlist->add_from_sql('userid', "SELECT userid FROM {local_timedroles} WHERE contextid = :contextid", $params);
        $userlist->add_from_sql(
            'assignedby',
            "SELECT assignedby FROM {local_timedroles} WHERE contextid = :contextid AND assignedby > 0",
            $params
        );
    }

    /**
     * Export the user's timed role data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_USER) {
                self::export_pauses($context, $userid);
                continue;
            }
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }
            $select = 'contextid = :contextid AND (userid = :userid OR assignedby = :assignedby)';
            $records = $DB->get_records_select(
                'local_timedroles',
                $select,
                ['contextid' => $context->id, 'userid' => $userid, 'assignedby' => $userid]
            );
            if (!$records) {
                continue;
            }
            $data = [];
            foreach ($records as $r) {
                $data[] = (object) [
                    'role' => role_get_name($DB->get_record('role', ['id' => $r->roleid]), $context),
                    'givenrole' => transform::yesno($r->userid == $userid),
                    'assignedrole' => transform::yesno($r->assignedby == $userid),
                    'timestart' => transform::datetime($r->timestart),
                    'timeend' => transform::datetime($r->timeend),
                    'status' => manager::status_name((int) $r->status),
                    'note' => $r->note,
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_timedroles')],
                (object) ['timedroles' => $data]
            );
        }
    }

    /**
     * Export notification pauses in a user's context that involve the given user.
     *
     * @param \context_user $context
     * @param int $userid the user whose data is being exported
     */
    protected static function export_pauses(\context $context, int $userid): void {
        global $DB;
        $records = $DB->get_records_select(
            'local_timedroles_pause',
            'userid = :owner AND (userid = :userid OR createdby = :createdby)',
            ['owner' => $context->instanceid, 'userid' => $userid, 'createdby' => $userid],
            'timestart ASC'
        );
        if (!$records) {
            return;
        }
        $data = [];
        foreach ($records as $r) {
            $data[] = (object) [
                'pausedfor' => transform::yesno($r->userid == $userid),
                'setupby' => transform::yesno($r->createdby == $userid),
                'timestart' => transform::datetime($r->timestart),
                'timeend' => transform::datetime($r->timeend),
                'status' => pause_manager::status_name((int) $r->status),
                'note' => $r->note,
            ];
        }
        writer::with_context($context)->export_data(
            [get_string('pausenotifications', 'local_timedroles')],
            (object) ['pauses' => $data]
        );
    }

    /**
     * Export the user's preferences from this plugin.
     *
     * @param int $userid
     */
    public static function export_user_preferences(int $userid): void {
        $until = get_user_preferences(pause_manager::PREF, null, $userid);
        if ($until) {
            writer::export_user_preference(
                'local_timedroles',
                pause_manager::PREF,
                transform::datetime((int) $until),
                get_string('privacy:metadata:preference:pauseduntil', 'local_timedroles')
            );
        }
    }

    /**
     * Delete all timed role data in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context->contextlevel == CONTEXT_USER) {
            pause_manager::delete_records_select('userid = :userid', ['userid' => $context->instanceid]);
            return;
        }
        manager::delete_records_select('contextid = :contextid', ['contextid' => $context->id]);
    }

    /**
     * Delete a user's timed role data in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_USER) {
                if ((int) $context->instanceid === (int) $userid) {
                    pause_manager::delete_records_select('userid = :userid', ['userid' => $userid]);
                }
                $DB->set_field(
                    'local_timedroles_pause',
                    'createdby',
                    0,
                    ['userid' => $context->instanceid, 'createdby' => $userid]
                );
                continue;
            }
            manager::delete_records_select(
                'contextid = :contextid AND userid = :userid',
                ['contextid' => $context->id, 'userid' => $userid]
            );
            $DB->set_field('local_timedroles', 'assignedby', 0, ['contextid' => $context->id, 'assignedby' => $userid]);
        }
    }

    /**
     * Delete timed role data for several users in one context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        $context = $userlist->get_context();
        $contextid = $context->id;
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        if ($context->contextlevel == CONTEXT_USER) {
            if (in_array((int) $context->instanceid, array_map('intval', $userids), true)) {
                pause_manager::delete_records_select('userid = :userid', ['userid' => $context->instanceid]);
            }
            $DB->set_field_select(
                'local_timedroles_pause',
                'createdby',
                0,
                "userid = :owner AND createdby $insql",
                ['owner' => $context->instanceid] + $inparams
            );
            return;
        }
        $params = ['contextid' => $contextid] + $inparams;
        manager::delete_records_select("contextid = :contextid AND userid $insql", $params);
        $DB->set_field_select('local_timedroles', 'assignedby', 0, "contextid = :contextid AND assignedby $insql", $params);
    }
}
