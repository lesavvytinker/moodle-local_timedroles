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
 * Tests for notification pauses.
 *
 * @package    local_timedroles
 * @copyright  2026 Daniel Maloney
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_timedroles\pause_manager
 * @covers     \local_timedroles\notifier::pause_scheduled
 * @covers     \local_timedroles\notifier::pause_ended
 */
final class pause_test extends \advanced_testcase {
    /**
     * The user's "Disable notifications" setting.
     *
     * @param int $userid
     * @return int
     */
    protected function emailstop(int $userid): int {
        global $DB;
        return (int) $DB->get_field('user', 'emailstop', ['id' => $userid]);
    }

    /**
     * A pause switches notifications off at the start and back on at the end.
     */
    public function test_pause_and_resume(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $now = time();

        $id = pause_manager::create($user->id, $now + 600, $now + 3600);
        $this->assertSame(0, $this->emailstop($user->id));

        [$started] = pause_manager::process_due($now + 601);
        $this->assertEquals(1, $started);
        $this->assertSame(1, $this->emailstop($user->id));
        $this->assertEquals($now + 3600, get_user_preferences(pause_manager::PREF, null, $user->id));

        [, $ended] = pause_manager::process_due($now + 3601);
        $this->assertEquals(1, $ended);
        $this->assertSame(0, $this->emailstop($user->id));
        $this->assertNull(get_user_preferences(pause_manager::PREF, null, $user->id));
        $this->assertEquals(pause_manager::STATUS_ENDED, $this->get_status($id));
    }

    /**
     * Someone who had already disabled notifications themselves keeps them disabled after the pause.
     */
    public function test_respects_users_own_setting(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'emailstop', 1, ['id' => $user->id]);
        $this->setUser($user);

        $id = pause_manager::create($user->id, time() - 10, time() + 3600);
        pause_manager::end_now($id, $user->id);
        $this->assertSame(1, $this->emailstop($user->id));
    }

    /**
     * While paused, notifications to the user are not emailed. An unpaused user gets the same email.
     */
    public function test_notifications_held_back_while_paused(): void {
        global $DB;
        $this->resetAfterTest();
        // Messages are only sent when the database transaction commits, so emails need this to be caught.
        $this->preventResetByRollback();
        $course = $this->getDataGenerator()->create_course();
        $paused = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $normal = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacherrole = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        $this->setAdminUser();
        pause_manager::create($paused->id, time() - 10, time() + 3600);

        // Giving each a timed role sends them a "role started" notification, which goes by email by default.
        $sink = $this->redirectEmails();
        manager::create($course->id, $normal->id, $teacherrole, time() - 10, time() + 3600);
        manager::create($course->id, $paused->id, $teacherrole, time() - 10, time() + 3600);

        $to = array_map(fn($m) => $m->to, $sink->get_messages());
        $this->assertContains($normal->email, $to);
        $this->assertNotContains($paused->email, $to);
    }

    /**
     * Someone else scheduling a pause tells the user, and the end of the pause tells them too.
     */
    public function test_notifications_about_the_pause(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $admin = get_admin();
        $this->setUser($admin);
        $now = time();

        $messages = $this->redirectMessages();
        $id = pause_manager::create($user->id, $now + 600, $now + 3600, 'Annual leave');
        $sent = $messages->get_messages();
        $this->assertCount(1, $sent);
        $this->assertEquals('pausescheduled', $sent[0]->eventtype);
        $this->assertEquals($user->id, $sent[0]->useridto);

        $messages->clear();
        pause_manager::process_due($now + 601);
        $this->assertCount(0, $messages->get_messages());
        pause_manager::process_due($now + 3601);
        $sent = $messages->get_messages();
        $this->assertCount(1, $sent);
        $this->assertEquals('pauseended', $sent[0]->eventtype);
        $this->assertEquals(pause_manager::STATUS_ENDED, $this->get_status($id));
    }

    /**
     * Pausing yourself doesn't send you a "someone paused you" message.
     */
    public function test_no_message_when_pausing_yourself(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $messages = $this->redirectMessages();
        pause_manager::create($user->id, time() + 600, time() + 3600);
        $this->assertCount(0, $messages->get_messages());
    }

    /**
     * Overlapping pauses are refused; back-to-back ones are fine.
     */
    public function test_overlap(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $now = time();
        pause_manager::create($user->id, $now + 600, $now + 3600);

        $this->assertTrue(pause_manager::overlaps($user->id, $now + 3000, $now + 7200));
        $this->assertFalse(pause_manager::overlaps($user->id, $now + 3600, $now + 7200));

        $this->expectException(\moodle_exception::class);
        pause_manager::create($user->id, $now + 3000, $now + 7200);
    }

    /**
     * Extending an active pause keeps notifications off past the old end time.
     */
    public function test_extend_active_pause(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $now = time();
        $id = pause_manager::create($user->id, $now - 10, $now + 3600);

        pause_manager::update($id, $user->id, $now - 10, $now + 7200, 'Extra week');
        $this->assertEquals($now + 7200, get_user_preferences(pause_manager::PREF, null, $user->id));

        pause_manager::process_due($now + 3601);
        $this->assertSame(1, $this->emailstop($user->id));
        pause_manager::process_due($now + 7201);
        $this->assertSame(0, $this->emailstop($user->id));
    }

    /**
     * Cancelling a scheduled pause never touches the user's setting.
     */
    public function test_cancel_scheduled(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $messages = $this->redirectMessages();
        $id = pause_manager::create($user->id, time() + 600, time() + 3600);

        pause_manager::end_now($id, $user->id);
        $this->assertEquals(pause_manager::STATUS_CANCELLED, $this->get_status($id));
        $this->assertSame(0, $this->emailstop($user->id));
        $this->assertCount(0, $messages->get_messages());
    }

    /**
     * Another user's pause can't be changed by passing a different user id.
     */
    public function test_wrong_user_rejected(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $id = pause_manager::create($user->id, time() + 600, time() + 3600);

        $this->expectException(\dml_missing_record_exception::class);
        pause_manager::end_now($id, $other->id);
    }

    /**
     * Status of a pause.
     *
     * @param int $id
     * @return int
     */
    protected function get_status(int $id): int {
        global $DB;
        return (int) $DB->get_field('local_timedroles_pause', 'status', ['id' => $id]);
    }
}
