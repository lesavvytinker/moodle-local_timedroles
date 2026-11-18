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
 * Tests for the timed role manager.
 *
 * @package    local_timedroles
 * @copyright  2026 Daniel Maloney
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_timedroles\manager
 */
final class manager_test extends \advanced_testcase {
    /**
     * The key promise: when the timed role ends, the user's normal roles are untouched,
     * even if the timed role is the same role they already had.
     */
    public function test_expiry_keeps_primary_roles(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $user = $this->getDataGenerator()->create_user();
        $student = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $teacher = $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $now = time();
        $id = manager::create($course->id, $user->id, $teacher, $now - 10, $now + 3600);
        $this->assertTrue(user_has_role_assignment($user->id, $teacher, $context->id));

        // Same role as their primary one: should not be removed on expiry.
        $id2 = manager::create($course->id, $user->id, $student, $now - 10, $now + 3600);

        [, $expired] = manager::process_due($now + 3601);
        $this->assertEquals(2, $expired);
        $this->assertFalse(user_has_role_assignment($user->id, $teacher, $context->id));
        $this->assertTrue(user_has_role_assignment($user->id, $student, $context->id));
        $this->assertEquals(manager::STATUS_EXPIRED, $DB->get_field('local_timedroles', 'status', ['id' => $id]));
        $this->assertEquals(manager::STATUS_EXPIRED, $DB->get_field('local_timedroles', 'status', ['id' => $id2]));
    }

    /**
     * Future-dated roles start when the task runs after the start time.
     */
    public function test_scheduled_start(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $DB->get_field('role', 'id', ['shortname' => 'teacher']);

        $now = time();
        manager::create($course->id, $user->id, $teacher, $now + 600, $now + 3600);
        $this->assertFalse(user_has_role_assignment($user->id, $teacher, $context->id));

        [$activated] = manager::process_due($now + 601);
        $this->assertEquals(1, $activated);
        $this->assertTrue(user_has_role_assignment($user->id, $teacher, $context->id));
    }

    /**
     * Revoking ends the role early.
     */
    public function test_revoke(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $DB->get_field('role', 'id', ['shortname' => 'teacher']);

        $id = manager::create($course->id, $user->id, $teacher, time() - 10, time() + 3600);
        manager::revoke($id, $context->id);
        $this->assertFalse(user_has_role_assignment($user->id, $teacher, $context->id));
        $this->assertEquals(manager::STATUS_REVOKED, $DB->get_field('local_timedroles', 'status', ['id' => $id]));
    }

    /**
     * Current roles list normal, timed and inherited roles, and leaves out users with none.
     */
    public function test_get_current_roles(): void {
        global $DB;
        $this->resetAfterTest();

        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $context = \context_course::instance($course->id);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $norole = $this->getDataGenerator()->create_and_enrol($course, 'student');
        role_unassign_all(['userid' => $norole->id, 'contextid' => $context->id]);

        $teacher = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        $manager = (int) $DB->get_field('role', 'id', ['shortname' => 'manager']);
        manager::create($course->id, $student->id, $teacher, time() - 10, time() + 3600);
        role_assign($manager, $student->id, \context_coursecat::instance($category->id)->id);

        $roles = manager::get_current_roles($context, [$student->id, $norole->id]);
        $this->assertArrayNotHasKey($norole->id, $roles);
        $this->assertCount(3, $roles[$student->id]);

        $labels = implode(' | ', $roles[$student->id]);
        $this->assertStringContainsString('Student', $labels);
        $this->assertStringContainsString('timed, until', $labels);
        $this->assertStringContainsString('from Category:', $labels);
    }
}
