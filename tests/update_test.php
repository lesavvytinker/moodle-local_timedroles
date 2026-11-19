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
 * Tests for changing timed roles.
 *
 * @package    local_timedroles
 * @copyright  2026 Daniel Maloney
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_timedroles\manager::update
 * @covers     \local_timedroles\notifier::role_changed
 */
final class update_test extends \advanced_testcase {
    /**
     * Set up a course with a student and a teacher who manages the timed role.
     *
     * @return array [course, context, student, roleid]
     */
    protected function make_course(): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assigner = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($assigner);
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        return [$course, \context_course::instance($course->id), $student, $roleid];
    }

    /**
     * Extending an active role keeps it past the old end time, tells the user, and logs the change.
     */
    public function test_extend_active_role(): void {
        $this->resetAfterTest();
        [$course, $context, $student, $roleid] = $this->make_course();
        $now = time();
        $id = manager::create($course->id, $student->id, $roleid, $now - 10, $now + 3600);

        $events = $this->redirectEvents();
        $messages = $this->redirectMessages();
        manager::update($id, $context->id, $now - 10, $now + 7200, 'Extra leave');

        $updated = array_values(array_filter(
            $events->get_events(),
            fn($e) => $e instanceof event\timed_role_updated
        ));
        $this->assertCount(1, $updated);
        $this->assertEquals($now + 3600, $updated[0]->other['oldtimeend']);
        $this->assertEquals($now + 7200, $updated[0]->other['timeend']);

        $sent = $messages->get_messages();
        $this->assertCount(1, $sent);
        $this->assertEquals('rolechanged', $sent[0]->eventtype);
        $this->assertEquals($student->id, $sent[0]->useridto);

        // Still in place after the old end time, gone after the new one.
        manager::process_due($now + 3601);
        $this->assertTrue(user_has_role_assignment($student->id, $roleid, $context->id));
        manager::process_due($now + 7201);
        $this->assertFalse(user_has_role_assignment($student->id, $roleid, $context->id));
    }

    /**
     * The start time of a role that has already started can't be moved.
     */
    public function test_active_start_is_fixed(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $context, $student, $roleid] = $this->make_course();
        $start = time() - 10;
        $id = manager::create($course->id, $student->id, $roleid, $start, $start + 3600);

        manager::update($id, $context->id, $start + 1800, $start + 7200);
        $this->assertEquals($start, $DB->get_field('local_timedroles', 'timestart', ['id' => $id]));
    }

    /**
     * Moving a scheduled role's start time to now starts it straight away.
     */
    public function test_scheduled_start_brought_forward(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $context, $student, $roleid] = $this->make_course();
        $now = time();
        $id = manager::create($course->id, $student->id, $roleid, $now + 600, $now + 3600);
        $this->assertFalse(user_has_role_assignment($student->id, $roleid, $context->id));

        manager::update($id, $context->id, $now - 10, $now + 3600);
        $this->assertTrue(user_has_role_assignment($student->id, $roleid, $context->id));
        $this->assertEquals(manager::STATUS_ACTIVE, $DB->get_field('local_timedroles', 'status', ['id' => $id]));
    }

    /**
     * Ended roles can't be changed.
     */
    public function test_cannot_change_ended_role(): void {
        $this->resetAfterTest();
        [$course, $context, $student, $roleid] = $this->make_course();
        $id = manager::create($course->id, $student->id, $roleid, time() - 10, time() + 3600);
        manager::revoke($id, $context->id);

        $this->expectException(\moodle_exception::class);
        manager::update($id, $context->id, time() - 10, time() + 7200);
    }

    /**
     * A record from another course can't be changed through this course.
     */
    public function test_wrong_course_rejected(): void {
        $this->resetAfterTest();
        [$course, , $student, $roleid] = $this->make_course();
        $other = \context_course::instance($this->getDataGenerator()->create_course()->id);
        $id = manager::create($course->id, $student->id, $roleid, time() - 10, time() + 3600);

        $this->expectException(\dml_missing_record_exception::class);
        manager::update($id, $other->id, time() - 10, time() + 7200);
    }
}
