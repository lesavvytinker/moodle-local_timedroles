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
 * Tests for timed role notifications and log events.
 *
 * @package    local_timedroles
 * @copyright  2026 Daniel Maloney
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_timedroles\manager
 * @covers     \local_timedroles\notifier
 */
final class notifications_events_test extends \advanced_testcase {
    /**
     * Set up a course with a student and a teacher who sets up the timed role.
     *
     * @return array [course, student, assigner, roleid]
     */
    protected function make_course(): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assigner = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        return [$course, $student, $assigner, $roleid];
    }

    /**
     * Created and started events fire, and the user is told the role has started.
     */
    public function test_create_and_start(): void {
        $this->resetAfterTest();
        [$course, $student, $assigner, $roleid] = $this->make_course();
        $this->setUser($assigner);

        $events = $this->redirectEvents();
        $messages = $this->redirectMessages();
        $now = time();
        manager::create($course->id, $student->id, $roleid, $now - 10, $now + 3600);

        $classes = array_map(fn($e) => get_class($e), $events->get_events());
        $this->assertContains(event\timed_role_created::class, $classes);
        $this->assertContains(event\timed_role_started::class, $classes);

        $sent = $messages->get_messages();
        $this->assertCount(1, $sent);
        $this->assertEquals($student->id, $sent[0]->useridto);
        $this->assertEquals('rolestarted', $sent[0]->eventtype);
    }

    /**
     * On expiry the user and the person who set it up are both told.
     */
    public function test_expiry_notifies_user_and_assigner(): void {
        $this->resetAfterTest();
        [$course, $student, $assigner, $roleid] = $this->make_course();
        $this->setUser($assigner);
        $now = time();
        manager::create($course->id, $student->id, $roleid, $now - 10, $now + 3600);

        $events = $this->redirectEvents();
        $messages = $this->redirectMessages();
        manager::process_due($now + 3601);

        $classes = array_map(fn($e) => get_class($e), $events->get_events());
        $this->assertContains(event\timed_role_expired::class, $classes);

        $recipients = [];
        foreach ($messages->get_messages() as $m) {
            $recipients[$m->eventtype] = (int) $m->useridto;
        }
        $this->assertEquals($student->id, $recipients['roleended']);
        $this->assertEquals($assigner->id, $recipients['assignerexpired']);
    }

    /**
     * Ending early tells the user, logs a revoked event, and doesn't message the person who ended it.
     */
    public function test_revoke_notifies_user_only(): void {
        $this->resetAfterTest();
        [$course, $student, $assigner, $roleid] = $this->make_course();
        $this->setUser($assigner);
        $id = manager::create($course->id, $student->id, $roleid, time() - 10, time() + 3600);

        $events = $this->redirectEvents();
        $messages = $this->redirectMessages();
        manager::revoke($id, \context_course::instance($course->id)->id);

        $classes = array_map(fn($e) => get_class($e), $events->get_events());
        $this->assertContains(event\timed_role_revoked::class, $classes);
        $sent = $messages->get_messages();
        $this->assertCount(1, $sent);
        $this->assertEquals($student->id, $sent[0]->useridto);
    }

    /**
     * A scheduled role that is ended before it starts sends no "ended" message.
     */
    public function test_no_end_message_if_never_started(): void {
        $this->resetAfterTest();
        [$course, $student, $assigner, $roleid] = $this->make_course();
        $this->setUser($assigner);
        $id = manager::create($course->id, $student->id, $roleid, time() + 600, time() + 3600);

        $messages = $this->redirectMessages();
        manager::revoke($id, \context_course::instance($course->id)->id);
        $this->assertCount(0, $messages->get_messages());
    }

    /**
     * Admin settings can switch notifications off.
     */
    public function test_settings_disable_notifications(): void {
        $this->resetAfterTest();
        set_config('notifystart', 0, 'local_timedroles');
        [$course, $student, $assigner, $roleid] = $this->make_course();
        $this->setUser($assigner);

        $messages = $this->redirectMessages();
        manager::create($course->id, $student->id, $roleid, time() - 10, time() + 3600);
        $this->assertCount(0, $messages->get_messages());
    }
}
