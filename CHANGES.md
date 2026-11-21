# Changelog

## 0.5.0 (2026-11-21)
- New: notification pauses. Pause someone's Moodle notifications between two
  dates (e.g. while they're on leave) so they don't come back to a full inbox.
  They keep full access to their courses, and notifications switch back on
  automatically. Uses Moodle's own "Disable notifications" setting, and puts
  it back exactly as it was afterwards.
- Users can pause their own notifications from Preferences > Pause
  notifications. Managers can pause anyone's, and see every current and
  upcoming pause under Site administration > Users > Notification pauses.
- Pauses can be edited (extend or shorten), ended early or cancelled.
- The user gets a reminder while paused, and a message when someone else
  pauses their notifications and when a pause ends. Every change is logged.

## 0.4.0 (2026-11-19)
- Edit link on each active or scheduled timed role. You can extend or shorten
  the end date (e.g. for extra leave), change the start date of a role that
  hasn't started yet, and update the reason.
- The user is told when the end date of their active role changes (can be
  switched off in the admin settings), and each change is logged with the old
  and new dates.

## 0.3.0 (2026-11-18)
- When you pick a user, their current roles show under the picker (including
  roles inherited from the category or site, and any timed roles already in
  place), so you can see what they will go back to.
- Each user's roles also show in the picker list, so you can search by role.

## 0.2.0 (2026-11-18)
- Notifications when a timed role starts and ends, and to the person who set
  it up when it expires. Each can be switched off in the admin settings.
- Log events for every change (set up, started, expired, ended early), with a
  link to the course logs from the Timed roles page.

## 0.1.0 (2026-11-18)
- First release: give an enrolled user a role in a course for a set period,
  after which it is removed and they keep their usual roles.
