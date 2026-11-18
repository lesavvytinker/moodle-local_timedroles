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

namespace local_timedroles\event;

/**
 * Shared behaviour for timed role events.
 *
 * other: roleid, timestart, timeend.
 *
 * @package    local_timedroles
 * @copyright  2026 Daniel Maloney
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class timed_role_base extends \core\event\base {
    /**
     * Build an event from a local_timedroles record.
     *
     * @param \stdClass $record
     * @return static
     */
    public static function create_from_record(\stdClass $record): self {
        $event = static::create([
            'objectid' => $record->id,
            'context' => \context::instance_by_id($record->contextid),
            'courseid' => $record->courseid,
            'relateduserid' => $record->userid,
            'other' => [
                'roleid' => (int) $record->roleid,
                'timestart' => (int) $record->timestart,
                'timeend' => (int) $record->timeend,
            ],
        ]);
        $event->add_record_snapshot('local_timedroles', $record);
        return $event;
    }

    /**
     * Init.
     */
    protected function init() {
        $this->data['objecttable'] = 'local_timedroles';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Link to the Timed roles page for the course.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/timedroles/index.php', ['id' => $this->courseid]);
    }

    /**
     * Validate.
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('relateduserid must be set');
        }
        foreach (['roleid', 'timestart', 'timeend'] as $key) {
            if (!isset($this->other[$key])) {
                throw new \coding_exception("other['{$key}'] must be set");
            }
        }
    }

    /**
     * Backup/restore mapping for the object id.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'local_timedroles', 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * Backup/restore mapping for the other data.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return [
            'roleid' => ['db' => 'role', 'restore' => 'role'],
            'timestart' => \core\event\base::NOT_MAPPED,
            'timeend' => \core\event\base::NOT_MAPPED,
        ];
    }
}
