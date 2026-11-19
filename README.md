# Timed roles (local_timedroles)

Give an enrolled user an extra role in a course until a set date and time — e.g. a student acting as a
non-editing teacher for a peer-marking week, or a staff member covering a course while a trainer is on
leave. When the period ends, the extra role is removed automatically and the user keeps their usual roles.

## How it works

- **Course > More > Timed roles**: pick a user, a role, start and end times, and an optional reason.
  When you pick a user, their current roles (including inherited and existing timed roles) show under the
  picker, so you can see what they will go back to. Roles also show in the picker list and are searchable.
- Roles are assigned with the component `local_timedroles` and their own item ID. Each timed role is a
  separate role assignment, so removing it never touches the user's normal roles, even if it's the same role.
  These assignments show as locked on the Participants page, so they can't be removed by accident.
- A scheduled task (every 5 minutes) starts future-dated roles and removes expired ones.
- Only roles the current user is allowed to assign (Site admin > Users > Define roles > Allow role
  assignments) are offered.
- "Edit" changes the end date of an active or scheduled role (e.g. to extend it), or the start date of one
  that hasn't started yet. The user is told about the new end date.
- "End now" removes a timed role early.
- Implements the Privacy API.

## Notifications (new in 0.2.0)

- The user is told when the timed role starts (including when it ends) and when it ends.
- The person who set it up is told when it expires on schedule.
- Messages go out in each recipient's own language and timezone, through Moodle's normal notification system,
  so users can manage them in their notification preferences.
- Site admins can switch each one off under Site administration > Plugins > Local plugins > Timed roles.

## Audit trail (new in 0.2.0)

Every change is logged as a standard Moodle event, so it shows in the course logs and any log store or
report that reads them:

| Event | When |
|---|---|
| Timed role set up | Someone creates a timed role (logs who, for whom, which role, until when) |
| Timed role changed | Someone edits the dates or reason (logs old and new dates) |
| Timed role started | The role is actually assigned (straight away, or by the scheduled task) |
| Timed role expired | The role is removed at its end time |
| Timed role ended early | Someone clicks "End now" (logs who) |

The Timed roles page links straight to the course logs.

See [CHANGES.md](CHANGES.md).

## Requirements

Moodle 4.5 – 5.1.

## Installation

Copy to `local/timedroles` (on Moodle 5.1 with the new layout: `public/local/timedroles`), then visit
Site administration > Notifications.

## Capability

`local/timedroles:manage` (default: editing teacher, manager), and also requires `moodle/role:assign`.

## License

Copyright 2026 Daniel Maloney. GPL v3 or later.
