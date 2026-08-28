# Intro

[Zoom](https://zoom.us) is a web- and app-based video conferencing service. This
plugin offers tight integration with Moodle, supporting meeting creation,
synchronization, grading and backup/restore.


## Try in Moodle Playground

Click the badge below to open this plugin instantly in
[Moodle Playground](https://ateeducacion.github.io/moodle-playground/) — a full Moodle site
running in the browser, with no local install. The demo includes a course
with a stub Zoom activity so you can preview the activity view, the
mod_form, and the plugin's admin settings.

**Note:** This demo uses placeholder Zoom credentials, so real Zoom meetings
cannot be created or joined. The Join / Start buttons will not work —
this preview is intended for UI and admin-settings inspection only.

<a href="https://ateeducacion.github.io/moodle-playground/?blueprint-url=https://raw.githubusercontent.com/jrchamp/moodle-mod_zoom/refs/heads/main/blueprint.json" target="_blank" rel="noopener"><img src="https://raw.githubusercontent.com/ateeducacion/action-moodle-playground-pr-preview/refs/heads/main/assets/playground-preview-button.svg" alt="Preview in Moodle Playground" width="200"></a>

## Prerequisites

This plugin is designed for Educational or Business Zoom accounts.

To connect to the Zoom APIs, this plugin requires an account-level app to be
created.

### Server-to-Server OAuth
To [create an account-level Server-to-Server OAuth app](https://developers.zoom.us/docs/internal-apps/create/), the `Server-to-server OAuth app`
permission is required. You should create a separate Server-to-Server OAuth app for each Moodle install.

The Server-to-Server OAuth app will generate a client ID, client secret and account ID.

#### Granular scopes
At a minimum, the following scopes are required:

- meeting:read:meeting:admin (Get meeting)
- meeting:read:invitation:admin (Get meeting invitation)
- meeting:delete:meeting:admin (Delete meeting)
- meeting:update:meeting:admin (Update meeting)
- meeting:write:meeting:admin (Create meeting)
- user:read:list_schedulers:admin (List schedulers)
- user:read:settings:admin (Get user settings)
- user:read:user:admin (Get user)

Optional functionality can be enabled by granting additional scopes:

- Meeting registrations
    - meeting:read:list_registrants:admin (Get registrants)
- Reports for meetings / webinars (Licensed accounts and higher)
    - report:read:list_meeting_participants:admin
    - report:read:list_webinar_participants:admin
    - report:read:list_users:admin
    - report:read:user:admin
- Faster reports for meetings / webinars (Business accounts and higher)
    - dashboard:read:list_meeting_participants:admin
    - dashboard:read:list_meetings:admin
    - dashboard:read:list_webinar_participants:admin
    - dashboard:read:list_webinars:admin
- Allow recordings to be viewed (zoom | viewrecordings)
    - cloud_recording:read:list_recording_files:admin
    - cloud_recording:read:list_user_recordings:admin
    - cloud_recording:read:recording_settings:admin
    - cloud_recording:update:recording_settings:admin
- Tracking fields (zoom | defaulttrackingfields)
    - tracking_field:read:list_tracking_fields:admin
- Recycle licenses (zoom | utmost), (zoom | recycleonjoin), (zoom | protectedgroups)
    - group:read:list_groups:admin
    - user:read:list_users:admin
    - user:update:user:admin
- Webinars (zoom | showwebinars), (zoom | webinardefault)
    - webinar:read:list_registrants:admin
    - webinar:read:webinar:admin
    - webinar:delete:webinar:admin
    - webinar:update:webinar:admin
    - webinar:write:webinar:admin

#### Classic scopes
At a minimum, the following scopes are required:

- meeting:read:admin (Read meeting details)
- meeting:write:admin (Create/Update meetings)
- user:read:admin (Read user details)

Optional functionality can be enabled by granting additional scopes:

- Reports for meetings / webinars
    - dashboard_meetings:read:admin (Business accounts and higher)
    - dashboard_webinars:read:admin  (Business accounts and higher)
    - report:read:admin (Pro accounts and higher)
- Allow recordings to be viewed (zoom | viewrecordings)
    - recording:read:admin
- Tracking fields (zoom | defaulttrackingfields)
    - tracking_fields:read:admin
- Recycle licenses (zoom | utmost), (zoom | recycleonjoin), (zoom | protectedgroups)
    - group:read:admin
    - user:write:admin
- Webinars (zoom | showwebinars), (zoom | webinardefault)
    - webinar:read:admin
    - webinar:write:admin

## Installation

1. [Install plugin](https://docs.moodle.org/en/Installing_plugins#Installing_a_plugin) to the /mod/zoom folder in Moodle.
2. After installing the plugin, the following settings need to be configured to use the plugin:

- Zoom account ID (mod_zoom | accountid)
- Zoom client ID (mod_zoom | clientid)
- Zoom client secret (mod_zoom | clientsecret)

If you get "Access token is expired" errors, make sure the date/time on your
server is properly synchronized with the time servers.

## Pooled hosts mode

An opt-in operating mode for institutions whose teachers are external
freelancers without organisational Zoom identities: the Zoom host becomes
infrastructure — a pool of generic, permanently-Licensed users defined by a
Zoom group — while the teacher is plain activity data. Licenses never move
between users (Zoom caps license reassignments at roughly 4 moves per license
per month, so seat-shuffling designs throttle), and no per-teacher Zoom
onboarding exists at all.

Enable by setting `zoom/pooledhostsgroup`; leave empty for stock behavior.

| Setting | Meaning |
|---|---|
| `zoom/pooledhostsgroup` | Zoom group defining the host pool. Non-empty = pooled mode on; empty = stock upstream behavior. A missing/unreadable group, or one with no usable members after filtering, fails loudly (`pool_misconfigured`) — never a silent empty pool. |
| `zoom/pooledrequirelicense` | Default on: only Licensed pool members may host (a Basic host's registration-bearing writes are silently stripped by Zoom). |
| `zoom/slotbuffer` | Minutes of required gap between bookings on one pool host; default 15. |
| `zoom/hostdisplaynametemplate` | Placeholders `%first`/`%last` from the teacher's Moodle name (e.g. `%first %last`). Non-empty = at start the rendered template is patched onto the pool host's `display_name` — the field Zoom actually shows in meetings; first/last name are never touched (Zoom resets `display_name` to "first last" on any first/last patch, so writing them would clobber this). Restored after the session (compare-and-swap: an out-of-band rename is never overwritten). Empty = no rename. |
| `zoom/pooledteacherroles` | Comma-separated role archetypes whose holders appear in the Teacher selector; default `editingteacher,teacher`. Empty = every enrolled user who can add Zoom activities. |
| `zoom/registrantconfirmationemail` | Default off: Zoom's own registration confirmation email is suppressed — the LMS hands out the personal join link itself. |

How it works:

- **Scheduling (occurrence-first)**: the activity form gains a required
  Teacher selector (role archetypes per `pooledteacherroles`) and replaces
  the recurrence-rule UI entirely: a meeting is planned **date by date**
  (first session + a planned-dates repeater), every session sharing one
  meeting id — one join link, one recordings archive, one registration set.
  On save, the plugin picks a pool member whose Zoom calendar (including
  meetings scheduled outside Moodle; the listing is per-occurrence and
  occurrence-edit-aware — measured) is free for **every** planned slot ±
  buffer (batch placement — a later host change would mean a new meeting id,
  so it must never be needed); the scan starts at a position hashed from the
  teacher so the same teacher tends to stay on the same pool host. No free
  host = the save fails (`pool_exhausted`) — that is the capacity signal.
  Under the hood the meeting is a type-8 recurring series on a hidden
  scaffold rule (weekly, `end_times` = session count — a container format,
  never user-visible or user-editable), and each grid occurrence is moved
  onto its planned date after create. Duration is mandatory in pooled mode.
- **Managing the schedule**: after creation the settings form carries no
  schedule fields — the **occurrence table** on the activity page (setting
  `zoom/occurrencetable`, default on; replaces the Schedule box) is the
  single scheduling surface: one row per session with status and inline
  video recordings (audio-only files are not listed; the existing
  per-recording visibility toggle applies), plus manager actions. Add =
  `end_times`+1 (grid-compatible, preserves every occurrence edit —
  measured) followed by a move onto the target date (hard cap 60 sessions:
  above that Zoom silently collapses the series). Move = per-occurrence
  PATCH. Cancel = per-occurrence DELETE (a permanent tombstone on Zoom —
  measured — that frees the host's slot; the last active session cannot be
  cancelled). Every action is conflict-checked against the meeting's host
  first and followed by an immediate readback that refreshes the record, the
  `zoom_occurrences` store (key: Zoom's `occurrence_id` = grid-slot epoch,
  stable across moves) and the calendar events.
- **Out-of-band edits**: occurrence-level portal edits (cancel/move) are
  tolerated — the daily `update_meetings` sync imports them into the store
  and calendar, and now also **retroactively conflict-checks** future
  occurrences against the host's calendar (`occurrence_conflict` event on
  collision — Zoom itself never conflict-checks, so the sync is the only
  detector for portal-made double-bookings). Structural portal edits
  (converting a meeting to recurring, changing the rule) are clobbered by
  the next Moodle save — don't.
- **Starting**: only the selected teacher sees Start. The click live-checks
  the pool host (`collision_imminent` if it is still in another meeting —
  Zoom allows only one active meeting per host and ends the first when a
  second is started), applies the name templates, queues the end-of-session
  adhoc task, and redirects through a fresh ZAK start URL.
- **After the session**: the adhoc task (scheduled_end + buffer, re-queuing
  while the meeting is still live, `overrun_detected` when a next booking
  approaches) restores the pool host's original profile name so manually
  scheduled meetings on the same account never run under a teacher's name.
- **Server-side registration, two modes**: registrants are always created by
  the plugin from the user's Moodle identity (name and email) — nobody ever
  sees Zoom's registration form, and the enforced display name is the Moodle
  name by construction. In *automatic* mode this happens invisibly on the
  first Join click; in *manual* (RSVP) mode the button reads Register and the
  explicit click is the participant's attendance confirmation — registering
  ahead of the session shows a confirmation, and the Join button appears when
  the session window opens. Zoom's own form remains only as an API-failure
  fallback.
- **Attendance**: the participant row matching the pool host is force-mapped
  to the activity's teacher; students match via their registration email as
  usual.
- **Alerting**: the mode fires plain Moodle events (`pool_exhausted`,
  `pool_misconfigured`, `collision_imminent`, `overrun_detected`,
  `registration_dropped`) which land in the standard log store; routing
  (Slack, email, …) is left to observer plugins.

Independent of pooled mode, this fork also always verifies meeting writes by
reading them back (Zoom answers 2xx and silently drops entitlement-gated
settings).

## Fork status

Branch `pooled`, based on upstream release tag **v5.5.0** (`2f1e5a8`),
maintained by FormaSuisse (`formasuisse/formasuisse_infra` consumes it at a
pinned commit SHA). Rebase policy: manual, onto upstream release tags. Design
notes and the measured evidence behind the mode (test labels T0–T14 in code
comments) live in the infra repo, issue formasuisse_infra#783. The earlier
shadow-user / license-stealing variant is preserved on the `formasuisse`
branch.
