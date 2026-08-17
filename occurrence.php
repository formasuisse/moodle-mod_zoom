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
 * add: extend the scaffold rule by one and move the appended occurrence onto
 *      the requested date (Zoom has no add-occurrence API; both steps are
 *      measured-safe — see README, 'Pooled hosts mode').
 * move: PATCH the occurrence onto a new date/duration.
 * cancel: DELETE the occurrence (permanent tombstone on Zoom).
 *
 * Every mutation is conflict-checked against the meeting's (fixed) pool host
 * first and followed by an immediate readback that refreshes the zoom
 * record, the occurrence store and the calendar events.
 *
 * @package    mod_zoom
 * @copyright  2026 FormaSuisse SA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once($CFG->libdir . '/formslib.php');

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

$occurrence = null;
if ($action === 'move' || $action === 'cancel') {
    $occurrence = $DB->get_record('zoom_occurrences', [
        'zoomid' => $zoom->id,
        'occurrenceid' => $occurrenceid,
        'status' => 'available',
    ], '*', MUST_EXIST);
    if ($occurrence->starttime < time()) {
        // Past occurrences are history — nothing to schedule.
        redirect($viewurl, get_string('occ_err_past', 'mod_zoom'), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$PAGE->set_url('/mod/zoom/occurrence.php', ['id' => $cm->id, 'action' => $action, 'occurrence' => $occurrenceid]);
$PAGE->set_title(format_string($zoom->name));
$PAGE->set_heading(format_string($course->fullname));

/**
 * Date/duration form shared by the add and move actions.
 *
 * @package mod_zoom
 * @copyright 2026 FormaSuisse SA
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_zoom_occurrence_form extends moodleform {
    /**
     * Defines the form: date/time + duration + routing hiddens.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', $this->_customdata['action']);
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement('hidden', 'occurrence', $this->_customdata['occurrence']);
        $mform->setType('occurrence', PARAM_ALPHANUMEXT);

        $mform->addElement('date_time_selector', 'newdate', get_string('occ_date', 'mod_zoom'), ['step' => 5]);
        $mform->addElement('duration', 'newduration', get_string('duration', 'zoom'), ['optional' => false]);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * The slot must be in the future.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ($data['newdate'] < time()) {
            $errors['newdate'] = get_string('err_start_time_past', 'zoom');
        }

        if ($data['newduration'] <= 0) {
            $errors['newduration'] = get_string('err_duration_nonpositive', 'zoom');
        }

        return $errors;
    }
}

if ($action === 'cancel') {
    if (optional_param('confirm', 0, PARAM_INT) && confirm_sesskey()) {
        try {
            zoom_pooled_occurrence_cancel($zoom, $occurrenceid);
            redirect($viewurl, get_string('occ_cancelled_notify', 'mod_zoom'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        } catch (moodle_exception $error) {
            redirect($viewurl, $error->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
        }
    }

    echo $OUTPUT->header();
    $confirmurl = new moodle_url('/mod/zoom/occurrence.php', [
        'id' => $cm->id, 'action' => 'cancel', 'occurrence' => $occurrenceid, 'confirm' => 1, 'sesskey' => sesskey(),
    ]);
    echo $OUTPUT->confirm(
        get_string('occ_cancel_confirm', 'mod_zoom', userdate($occurrence->starttime)),
        $confirmurl,
        $viewurl
    );
    echo $OUTPUT->footer();
    die();
}

if ($action !== 'add' && $action !== 'move') {
    redirect($viewurl);
}

$defaults = new stdClass();
if ($action === 'move') {
    $defaults->newdate = $occurrence->starttime;
    $defaults->newduration = (int) ($occurrence->duration ?: $zoom->duration);
} else {
    // Default a new occurrence to one week after the series' last session.
    $last = $DB->get_records('zoom_occurrences', ['zoomid' => $zoom->id, 'status' => 'available'],
        'starttime DESC', 'starttime, duration', 0, 1);
    $lastrow = reset($last);
    $defaults->newdate = $lastrow ? $lastrow->starttime + WEEKSECS : time() + DAYSECS;
    $defaults->newduration = (int) ($lastrow->duration ?? 0) ?: (int) $zoom->duration;
}

$mform = new mod_zoom_occurrence_form(null, ['id' => $cm->id, 'action' => $action, 'occurrence' => $occurrenceid]);
$mform->set_data($defaults);

if ($mform->is_cancelled()) {
    redirect($viewurl);
}

if ($data = $mform->get_data()) {
    try {
        if ($action === 'add') {
            zoom_pooled_occurrence_add($zoom, (int) $data->newdate, (int) $data->newduration);
            redirect($viewurl, get_string('occ_added_notify', 'mod_zoom'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        } else {
            zoom_pooled_occurrence_move($zoom, $occurrenceid, (int) $data->newdate, (int) $data->newduration);
            redirect($viewurl, get_string('occ_moved_notify', 'mod_zoom'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        }
    } catch (moodle_exception $error) {
        // Slot busy on the pool host (or the series is at the cap): back to
        // the form with the reason — the user picks another time.
        \core\notification::error($error->getMessage());
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($zoom->name));
$heading = $action === 'add'
    ? get_string('occ_add', 'mod_zoom')
    : get_string('occ_move_heading', 'mod_zoom', userdate($occurrence->starttime));
echo $OUTPUT->heading($heading, 3);
$mform->display();
echo $OUTPUT->footer();
