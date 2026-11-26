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
 * Tests that timed roles can only be managed for roles and users the current user is allowed.
 *
 * @package    local_timedroles
 * @copyright  2026 Daniel Maloney
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_timedroles\manager::can_manage
 * @covers     \local_timedroles\manager::update
 * @covers     \local_timedroles\manager::revoke
 * @covers     \local_timedroles\manager::create
 */
final class permissions_test extends \advanced_testcase {
    /**
     * A course with a student who has a Manager timed role set up by an administrator,
     * and an editing teacher (who can't assign Manager).
     *
     * @return array [course, context, student, teacher, record id]
     */
    protected function manager_role_setup(): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $managerrole = (int) $DB->get_field('role', 'id', ['shortname' => 'manager']);

        $this->setAdminUser();
        $id = manager::create($course->id, $student->id, $managerrole, time() - 10, time() + 3600);
        return [$course, $context, $student, $teacher, $id];
    }

    /**
     * The reviewer's case: a teacher can't extend a Manager timed role set up by an admin.
     */
    public function test_teacher_cannot_edit_manager_role(): void {
        global $DB;
        $this->resetAfterTest();
        [, $context, , $teacher, $id] = $this->manager_role_setup();
        $before = $DB->get_field('local_timedroles', 'timeend', ['id' => $id]);

        $this->setUser($teacher);
        try {
            manager::update($id, $context->id, time() - 10, time() + YEARSECS);
            $this->fail('Expected a permission error');
        } catch (\required_capability_exception $e) {
            $this->assertEquals($before, $DB->get_field('local_timedroles', 'timeend', ['id' => $id]));
        }
    }

    /**
     * A teacher can't end a Manager timed role early either.
     */
    public function test_teacher_cannot_revoke_manager_role(): void {
        global $DB;
        $this->resetAfterTest();
        [, $context, $student, $teacher, $id] = $this->manager_role_setup();
        $managerrole = (int) $DB->get_field('role', 'id', ['shortname' => 'manager']);

        $this->setUser($teacher);
        try {
            manager::revoke($id, $context->id);
            $this->fail('Expected a permission error');
        } catch (\required_capability_exception $e) {
            $this->assertTrue(user_has_role_assignment($student->id, $managerrole, $context->id));
            $this->assertEquals(manager::STATUS_ACTIVE, $DB->get_field('local_timedroles', 'status', ['id' => $id]));
        }
    }

    /**
     * can_manage() decides whether the Edit and End now links show: no for Manager, yes for a role the teacher can assign.
     */
    public function test_can_manage_matches_assignable_roles(): void {
        global $DB;
        $this->resetAfterTest();
        [, $context, $student, $teacher] = $this->manager_role_setup();
        $managerrole = (int) $DB->get_field('role', 'id', ['shortname' => 'manager']);
        $noneditingrole = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher']);

        $this->setUser($teacher);
        $this->assertFalse(manager::can_manage($student->id, $managerrole, $context));
        $this->assertTrue(manager::can_manage($student->id, $noneditingrole, $context));
    }

    /**
     * A teacher can't create a timed role they aren't allowed to assign, even by calling the API directly.
     */
    public function test_teacher_cannot_create_manager_role(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, , $student, $teacher] = $this->manager_role_setup();
        $managerrole = (int) $DB->get_field('role', 'id', ['shortname' => 'manager']);

        $this->setUser($teacher);
        $this->expectException(\required_capability_exception::class);
        manager::create($course->id, $student->id, $managerrole, time(), time() + 3600);
    }

    /**
     * In separate groups mode, a teacher without access to all groups only sees and manages their own groups.
     */
    public function test_separate_groups(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $context = \context_course::instance($course->id);
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $mine = $gen->create_and_enrol($course, 'student');
        $theirs = $gen->create_and_enrol($course, 'student');
        $groupa = $gen->create_group(['courseid' => $course->id]);
        $groupb = $gen->create_group(['courseid' => $course->id]);
        $gen->create_group_member(['groupid' => $groupa->id, 'userid' => $teacher->id]);
        $gen->create_group_member(['groupid' => $groupa->id, 'userid' => $mine->id]);
        $gen->create_group_member(['groupid' => $groupb->id, 'userid' => $theirs->id]);

        // Editing teachers can access all groups by default; take that away for this course.
        $editingrole = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $editingrole, $context->id, true);
        $noneditingrole = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher']);

        $this->setUser($teacher);
        $this->assertEquals([(int) $groupa->id], manager::visible_groupids($course, $context));
        $this->assertTrue(manager::can_see_user($mine->id, $course, $context));
        $this->assertFalse(manager::can_see_user($theirs->id, $course, $context));
        $this->assertTrue(manager::can_manage($mine->id, $noneditingrole, $context));
        $this->assertFalse(manager::can_manage($theirs->id, $noneditingrole, $context));

        $this->expectException(\required_capability_exception::class);
        manager::create($course->id, $theirs->id, $noneditingrole, time(), time() + 3600);
    }
}
