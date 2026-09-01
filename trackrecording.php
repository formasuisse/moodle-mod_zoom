<?php
// This file is part of Moodle - http://moodle.org/
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
 * Log-only endpoint: record that a student opened a recording in the embedded
 * player. Interim embedded player (infra #1233). Unlike loadrecording.php this
 * emits no Zoom URL and does not redirect — the player embeds the share URL
 * directly. It fires a recording_viewed event (activity journal / logs) and marks
 * the per-user view, then returns 204.
 *
 * @package    mod_zoom
 * @copyright  2026 FormaSuisse
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$recordingid = required_param('recordingid', PARAM_INT);

if (!get_config('zoom', 'viewrecordings')) {
    throw new moodle_exception('recordingnotvisible', 'mod_zoom');
}

[$course, $cm, $zoom] = zoom_get_instance_setup();
require_login($course, true, $cm);
require_sesskey();

$context = context_module::instance($cm->id);
require_capability('mod/zoom:view', $context);

$rec = $DB->get_record('zoom_meeting_recordings',
    ['id' => $recordingid, 'zoomid' => $zoom->id], '*', MUST_EXIST);
if (!zoom_recording_visible_to_user($rec->id, $context)) {
    throw new moodle_exception('recordingnotfound', 'mod_zoom');
}

$now = time();
$viewparams = ['recordingsid' => $rec->id, 'userid' => $USER->id];
$view = $DB->get_record('zoom_meeting_recordings_view', $viewparams);
if (!empty($view)) {
    if (empty($view->viewed)) {
        $view->viewed = 1;
        $view->timemodified = $now;
        $DB->update_record('zoom_meeting_recordings_view', $view);
    }
} else {
    $view = (object) [
        'recordingsid' => $rec->id,
        'userid' => $USER->id,
        'viewed' => 1,
        'timemodified' => $now,
    ];
    $view->id = $DB->insert_record('zoom_meeting_recordings_view', $view);
}

\mod_zoom\event\recording_viewed::create([
    'context' => $context,
    'objectid' => $rec->id,
    'other' => ['cmid' => (int) $cm->id],
])->trigger();

// No body, no redirect: the caller only needs the 204.
header('HTTP/1.1 204 No Content');
exit;
