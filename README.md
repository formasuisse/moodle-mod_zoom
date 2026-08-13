# Intro

[Zoom](https://zoom.us) is a web- and app-based video conferencing service. This
plugin offers tight integration with Moodle, supporting meeting creation,
synchronization, grading and backup/restore.

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

## FormaSuisse patch (vendored fork)

This branch (`formasuisse`) is FormaSuisse's vendored fork, based on upstream
release tag **v5.5.0** (`2f1e5a8`). It is consumed by the
`formasuisse/formasuisse_infra` Moodle image at a pinned commit SHA (the pin is
bumped manually; Renovate cannot track fork branches). Design rationale and the
measured pilot evidence (test labels T0–T10 referenced in code comments) are
recorded in the infra repo, issue formasuisse_infra#783.

### Patch inventory

1. **`with_seat` guard** (`classes/webservice.php`, `db/upgrade.php`) — every
   license-gated Zoom write (meeting create, meeting update, meeting start,
   registrant-add) runs through `with_seat()`: verify/move the host's seat
   under a cluster-wide lock, protect fresh grants with a DB lease
   (`zoom_seat_lease`), never demote a host with a live meeting, refuse+alert
   when nothing is safely movable (reason `pool` or `quota`). Replaces the
   unsafe `get_least_recently_active_paid_user_id()` victim selection
   (upstream issue #162): missing `last_login_time` now means *oldest
   candidate*, not exempt, and a per-candidate
   `GET /users/{id}/meetings?type=live` check is the only trusted liveness
   signal (`last_login_time` is blind to ZAK starts).
2. **Symmetric licensing** — `update_meeting()` guards licensing like
   `create_meeting()` (upstream never re-licenses on update; any-field update
   under a Basic host silently strips registration — measured, T3).
3. **Read-back verification** — after every registration-bearing create/update,
   `GET /meetings/{id}` and fail the Moodle save loudly if Zoom silently
   dropped `approval_type`/`registration_url` or converted the meeting to PMI.
4. **Registrant path + browser join** — registrant creation is seat-guarded
   (measured, T6: license-gated), and the activity page offers a direct
   web-client join button that preserves the personal `?tk=` token (the `/w/`
   launcher's own browser fallback drops it — measured, T9).
5. **Alert log lines** — single greppable prefix
   `mod_zoom_seat_alert reason=<...> host=<...> meeting=<...>` feeding the
   infra log pipeline.
6. **Schedule-for identity mapping** (`mod_form.php`) — the "Schedule for"
   host selector matches candidates through the configured `apiidentifier`
   (the `zoomid` profile field) instead of the raw Moodle email, so an admin
   can create activities on behalf of a trainer whose Zoom identity is a
   shadow alias. Requires the shadow user to have granted the admin's Zoom
   user scheduling privilege on the Zoom side.

### Rebase policy

Manual. On a new upstream release: rebase this branch onto the release tag,
re-run the patch inventory review, update the base tag above, bump the pin in
the infra Dockerfile. Patches 1–3 are upstream-PR candidates for upstream
issue #162.
