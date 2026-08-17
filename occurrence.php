<?php
// This file is part of the Zoom plugin for Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Occurrence actions for pooled meetings (occurrence-first scheduling).
 *
 * Driven by the inline forms of the sessions table on the activity page:
 *
 * add:    extend the scaffold rule by one and move the appended occurrence
 *         onto the requested slot (Zoom has no add-occurrence API; both
 *         steps are measured-safe — see README, 'Pooled hosts mode').
 * move:   PATCH the occurrence onto a new date/duration.
 * cancel: DELETE the occurrence — stays listed, struck through (it was
 *         planned; students should see the change). Confirm page.
 * delete: DELETE the occurrence AND hide it from the table (it was never
 *         really planned — scaffold surplus). Confirm page.
 * remove: hide an already-cancelled occurrence (artifact cleanup;
 *         Moodle-only, the Zoom tombstone is untouchable either way).
 *
 * Every Zoom mutation is conflict-checked against the meeting's (fixed)
 * pool host first and followed by an immediate readback that refreshes the
 * zoom record, the occurrence store and the calendar events.
 *
 * @package    mod_zoom
 * @copyright  2026 FormaSuisse SA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');
require_once(dirname(__FILE__) . '/locallib.php');

$id = required_param('id', PARAM_INT); // Course module ID.
$action = required_param('action', PARAM_ALPHA);
$occurrenceid = optional_param('occurrence', '', PARAM_ALPHANUMEXT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'zoom');
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/zoom:addinstance', $context);

$zoom = $DB->get_record('zoom', ['id' => $cm->instance], '*', MUST_EXIST);
$viewurl = new moodle_url('/mod/zoom/view.php', ['id' => $cm->id]);

if (zoom_pooled_group() === null || !empty($zoom->webinar) || empty($zoom->recurring)
        || $zoom->exists_on_zoom != ZOOM_MEETING_EXISTS) {
    redirect($viewurl);
}

/**
 * Parse the datetime-local + minutes inputs of the inline forms.
 *
 * @param moodle_url $viewurl Where to bounce on invalid input.
 * @return int[] [start (Unix timestamp), duration (seconds)]
 */
function mod_zoom_occurrence_slot_params($viewurl) {
    global $CFG;

    $rawdatetime = required_param('newdatetime', PARAM_RAW_TRIMMED);
    $minutes = required_param('newduration', PARAM_INT);

    // The datetime-local input carries site-local wall clock — the same
    // timezone every Zoom write uses (see zoom_pooled_local_start()).
    $tzname = !empty($CFG->timezone) ? $CFG->timezone : date_default_timezone_get();
    try {
        $start = (new DateTimeImmutable($rawdatetime, new DateTimeZone($tzname)))->getTimestamp();
    } catch (Exception $e) {
        redirect($viewurl, get_string('occ_err_baddate', 'mod_zoom'), null, \core\output\notification::NOTIFY_ERROR);
    }

    if ($start < time()) {
        redirect($viewurl, get_string('err_start_time_past', 'zoom'), null, \core\output\notification::NOTIFY_ERROR);
    }

    if ($minutes < 1 || $minutes > 1440) {
        redirect($viewurl, get_string('err_duration_nonpositive', 'zoom'), null, \core\output\notification::NOTIFY_ERROR);
    }

    return [$start, $minutes * 60];
}

$occurrence = null;
if ($action !== 'add') {
    $occurrence = $DB->get_record('zoom_occurrences', [
        'zoomid' => $zoom->id,
        'occurrenceid' => $occurrenceid,
    ], '*', MUST_EXIST);
    $needstatus = ($action === 'remove') ? 'deleted' : 'available';
    if ($occurrence->status !== $needstatus) {
        redirect($viewurl);
    }

    if ($action !== 'remove' && $occurrence->starttime < time()) {
        // Past sessions are history — nothing to schedule.
        redirect($viewurl, get_string('occ_err_past', 'mod_zoom'), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$PAGE->set_url('/mod/zoom/occurrence.php', ['id' => $cm->id, 'action' => $action, 'occurrence' => $occurrenceid]);
$PAGE->set_title(format_string($zoom->name));
$PAGE->set_heading(format_string($course->fullname));

try {
    switch ($action) {
        case 'add':
            require_sesskey();
            [$start, $duration] = mod_zoom_occurrence_slot_params($viewurl);
            zoom_pooled_occurrence_add($zoom, $start, $duration);
            redirect($viewurl, get_string('occ_added_notify', 'mod_zoom'), null,
                \core\output\notification::NOTIFY_SUCCESS);

        case 'move':
            require_sesskey();
            [$start, $duration] = mod_zoom_occurrence_slot_params($viewurl);
            zoom_pooled_occurrence_move($zoom, $occurrenceid, $start, $duration);
            redirect($viewurl, get_string('occ_moved_notify', 'mod_zoom'), null,
                \core\output\notification::NOTIFY_SUCCESS);

        case 'remove':
            require_sesskey();
            zoom_pooled_occurrence_remove($zoom, $occurrenceid);
            redirect($viewurl, get_string('occ_removed_notify', 'mod_zoom'), null,
                \core\output\notification::NOTIFY_SUCCESS);

        case 'cancel':
        case 'delete':
            if (optional_param('confirm', 0, PARAM_INT) && confirm_sesskey()) {
                zoom_pooled_occurrence_cancel($zoom, $occurrenceid, $action === 'delete');
                redirect(
                    $viewurl,
                    get_string($action === 'delete' ? 'occ_deleted_notify' : 'occ_cancelled_notify', 'mod_zoom'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            }

            echo $OUTPUT->header();
            $confirmurl = new moodle_url('/mod/zoom/occurrence.php', [
                'id' => $cm->id, 'action' => $action, 'occurrence' => $occurrenceid,
                'confirm' => 1, 'sesskey' => sesskey(),
            ]);
            $confirmstring = $action === 'delete' ? 'occ_delete_confirm' : 'occ_cancel_confirm';
            echo $OUTPUT->confirm(
                get_string($confirmstring, 'mod_zoom', userdate($occurrence->starttime)),
                $confirmurl,
                $viewurl
            );
            echo $OUTPUT->footer();
            die();

        default:
            redirect($viewurl);
    }
} catch (moodle_exception $error) {
    // Slot busy on the pool host, series at the cap, last occurrence, ... —
    // back to the table with the reason; the user picks another time.
    redirect($viewurl, $error->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
}
