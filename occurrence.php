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
 * cancel:  strike the occurrence on Zoom — stays listed, struck through
 *          (it was planned; students should see the change). Confirm page.
 * discard: strike on Zoom AND hide from the table (it was never really
 *          planned — scaffold surplus). Confirm page.
 * hide:    hide an already-cancelled occurrence (artifact cleanup;
 *          Moodle-only, the Zoom tombstone is untouchable either way).
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

// A series Zoom has purged still accepts ONE action: adding a date, which
// revives it onto a fresh meeting. Everything else needs a live meeting to
// act on.
$purged = ($zoom->exists_on_zoom != ZOOM_MEETING_EXISTS);
if (zoom_pooled_group() === null || !empty($zoom->webinar) || empty($zoom->recurring)
        || ($purged && $action !== 'add')) {
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

    $rawdate = required_param('newdate', PARAM_RAW_TRIMMED);
    $rawtime = required_param('newtime', PARAM_RAW_TRIMMED);
    $minutes = required_param('newduration', PARAM_INT);

    // Date input + 24h time select carry site-local wall clock — the same
    // timezone every Zoom write uses (see zoom_pooled_local_start()).
    $start = zoom_pooled_parse_local($rawdate . ' ' . $rawtime);
    if ($start <= 0) {
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

/**
 * Load the occurrence an action operates on, enforcing its required state.
 *
 * @param stdClass $zoom zoom record.
 * @param string $occurrenceid Zoom occurrence id from the request.
 * @param string $needstatus Stored status the action expects: 'available'
 *        (schedulable — must also lie in the future; past sessions are
 *        history) or 'deleted' (a cancelled row).
 * @param moodle_url $viewurl Where to bounce when the state does not match.
 * @return stdClass zoom_occurrences row.
 */
function mod_zoom_occurrence_load($zoom, $occurrenceid, $needstatus, $viewurl) {
    global $DB;

    $occurrence = $DB->get_record('zoom_occurrences', [
        'zoomid' => $zoom->id,
        'occurrenceid' => $occurrenceid,
    ], '*', MUST_EXIST);
    if ($occurrence->status !== $needstatus) {
        redirect($viewurl);
    }

    if ($needstatus === 'available' && $occurrence->starttime < time()) {
        redirect($viewurl, get_string('occ_err_past', 'mod_zoom'), null, \core\output\notification::NOTIFY_ERROR);
    }

    return $occurrence;
}

/**
 * Require the user's confirmation for a destructive occurrence action.
 *
 * Passes through when the request already carries the confirm flag (with a
 * valid sesskey); otherwise renders the are-you-sure page and ends the
 * request.
 *
 * @param stdClass $cm Course module.
 * @param moodle_url $viewurl Back target.
 * @param string $action 'cancel' or 'discard' (round-trips in the confirm URL).
 * @param stdClass $occurrence The occurrence at stake (date shown in the prompt).
 * @param string $confirmstring mod_zoom string id of the prompt.
 * @param string $buttonstring mod_zoom string id of the confirm button label.
 * @return void
 */
function mod_zoom_occurrence_require_confirm($cm, $viewurl, $action, $occurrence, $confirmstring, $buttonstring) {
    global $OUTPUT;

    if (optional_param('confirm', 0, PARAM_INT) && confirm_sesskey()) {
        return;
    }

    echo $OUTPUT->header();
    $confirmurl = new moodle_url('/mod/zoom/occurrence.php', [
        'id' => $cm->id, 'action' => $action, 'occurrence' => $occurrence->occurrenceid,
        'confirm' => 1, 'sesskey' => sesskey(),
    ]);
    // Explicit button labels: the generic Annuler/Continuer pair is
    // hopeless under a message that itself starts with "Annuler …?".
    $confirmbutton = new single_button(
        $confirmurl,
        get_string($buttonstring, 'mod_zoom'),
        'post',
        single_button::BUTTON_DANGER
    );
    $backbutton = new single_button($viewurl, get_string('back'), 'get');
    echo $OUTPUT->confirm(
        get_string($confirmstring, 'mod_zoom', userdate($occurrence->starttime)),
        $confirmbutton,
        $backbutton
    );
    echo $OUTPUT->footer();
    die();
}

/**
 * Require confirmation before continuing a series Zoom has purged.
 *
 * Reviving mints a new Zoom meeting (Zoom has no undelete), so the join
 * link changes — students holding the old one are left with a dead link.
 * That is worth one deliberate click, unlike an ordinary add.
 *
 * @param stdClass $cm Course module.
 * @param moodle_url $viewurl Back target.
 * @param int $start Requested slot start.
 * @param int $duration Requested slot duration in seconds.
 * @return void
 */
function mod_zoom_revive_require_confirm($cm, $viewurl, $start, $duration) {
    global $OUTPUT;

    if (optional_param('confirm', 0, PARAM_INT) && confirm_sesskey()) {
        return;
    }

    [$local] = zoom_pooled_local_start($start);
    echo $OUTPUT->header();
    $confirmurl = new moodle_url('/mod/zoom/occurrence.php', [
        'id' => $cm->id, 'action' => 'add', 'confirm' => 1, 'sesskey' => sesskey(),
        'newdate' => substr($local, 0, 10),
        'newtime' => substr($local, 11, 5),
        'newduration' => (int) round($duration / MINSECS),
    ]);
    $confirmbutton = new single_button(
        $confirmurl,
        get_string('occ_revive_confirm_btn', 'mod_zoom'),
        'post'
    );
    $backbutton = new single_button($viewurl, get_string('back'), 'get');
    echo $OUTPUT->confirm(
        get_string('occ_revive_confirm', 'mod_zoom', userdate($start)),
        $confirmbutton,
        $backbutton
    );
    echo $OUTPUT->footer();
    die();
}

$PAGE->set_url('/mod/zoom/occurrence.php', ['id' => $cm->id, 'action' => $action, 'occurrence' => $occurrenceid]);
$PAGE->set_title(format_string($zoom->name));
$PAGE->set_heading(format_string($course->fullname));

try {
    switch ($action) {
        case 'add':
            require_sesskey();
            [$start, $duration] = mod_zoom_occurrence_slot_params($viewurl);
            if ($purged) {
                // The series has no Zoom meeting left to extend: continue it
                // on a fresh one, under this same activity.
                mod_zoom_revive_require_confirm($cm, $viewurl, $start, $duration);
                zoom_pooled_occurrence_revive($zoom, $cm, $start, $duration);
                redirect($viewurl, get_string('occ_revived_notify', 'mod_zoom'), null,
                    \core\output\notification::NOTIFY_SUCCESS);
            }

            zoom_pooled_occurrence_add($zoom, $start, $duration);
            redirect($viewurl, get_string('occ_added_notify', 'mod_zoom'), null,
                \core\output\notification::NOTIFY_SUCCESS);

        case 'move':
            require_sesskey();
            mod_zoom_occurrence_load($zoom, $occurrenceid, 'available', $viewurl);
            [$start, $duration] = mod_zoom_occurrence_slot_params($viewurl);
            zoom_pooled_occurrence_move($zoom, $occurrenceid, $start, $duration);
            redirect($viewurl, get_string('occ_moved_notify', 'mod_zoom'), null,
                \core\output\notification::NOTIFY_SUCCESS);

        case 'hide':
            require_sesskey();
            mod_zoom_occurrence_load($zoom, $occurrenceid, 'deleted', $viewurl);
            zoom_pooled_occurrence_hide($zoom, $occurrenceid);
            redirect($viewurl, get_string('occ_hidden_notify', 'mod_zoom'), null,
                \core\output\notification::NOTIFY_SUCCESS);

        case 'recshow':
            // Per-recording visibility toggle (Moodle-only flag), from the
            // occurrences table — lands back on the table, unlike the
            // recordings-page toggle.
            require_sesskey();
            $recordingid = required_param('recording', PARAM_INT);
            $show = required_param('show', PARAM_INT) ? 1 : 0;
            $rec = $DB->get_record('zoom_meeting_recordings',
                ['id' => $recordingid, 'zoomid' => $zoom->id], 'id, meetinguuid', MUST_EXIST);
            $DB->set_field('zoom_meeting_recordings', 'showrecording', $show, ['id' => $recordingid]);
            if (!zoom_recording_sharing_sync($rec->meetinguuid)) {
                redirect($viewurl, get_string('recordingsharingfailed', 'mod_zoom'), null,
                    \core\output\notification::NOTIFY_WARNING);
            }

            redirect($viewurl);

        case 'cancel':
            // Cancel: the occurrence is struck on Zoom but stays listed.
            $occurrence = mod_zoom_occurrence_load($zoom, $occurrenceid, 'available', $viewurl);
            mod_zoom_occurrence_require_confirm($cm, $viewurl, 'cancel', $occurrence,
                'occ_cancel_confirm', 'occ_cancel_confirm_btn');
            zoom_pooled_occurrence_cancel($zoom, $occurrenceid, false);
            redirect($viewurl, get_string('occ_cancelled_notify', 'mod_zoom'), null,
                \core\output\notification::NOTIFY_SUCCESS);

        case 'discard':
            // Discard: struck on Zoom AND hidden from the list right away.
            $occurrence = mod_zoom_occurrence_load($zoom, $occurrenceid, 'available', $viewurl);
            mod_zoom_occurrence_require_confirm($cm, $viewurl, 'discard', $occurrence,
                'occ_discard_confirm', 'occ_discard_confirm_btn');
            zoom_pooled_occurrence_cancel($zoom, $occurrenceid, true);
            redirect($viewurl, get_string('occ_discarded_notify', 'mod_zoom'), null,
                \core\output\notification::NOTIFY_SUCCESS);

        default:
            redirect($viewurl);
    }
} catch (moodle_exception $error) {
    // Slot busy on the pool host, series at the cap, last occurrence, ... —
    // back to the table with the reason; the user picks another time.
    redirect($viewurl, $error->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
}
