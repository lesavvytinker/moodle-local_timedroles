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
    \core_privacy\local\request\plugin\provider {
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
        return $contextlist;
    }

    /**
     * Users with data in the given context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
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
     * Delete all timed role data in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
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
        $contextid = $userlist->get_context()->id;
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = ['contextid' => $contextid] + $inparams;
        manager::delete_records_select("contextid = :contextid AND userid $insql", $params);
        $DB->set_field_select('local_timedroles', 'assignedby', 0, "contextid = :contextid AND assignedby $insql", $params);
    }
}
