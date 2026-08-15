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
 * Adding, updating, and deleting zoom meeting recordings.
 *
 * @package    mod_zoom
 * @copyright  2020 UC Regents
 * @author     2021 Jwalit Shah <jwalitshah@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/locallib.php');

[$course, $cm, $zoom] = zoom_get_instance_setup();

require_login($course, true, $cm);

if (!get_config('zoom', 'viewrecordings')) {
    throw new moodle_exception('recordingnotvisible', 'mod_zoom');
}

$context = context_module::instance($cm->id);
// Set up the page.
$params = ['id' => $cm->id];
$url = new moodle_url('/mod/zoom/recordings.php', $params);
$PAGE->set_url($url);

$strname = $zoom->name;
$PAGE->set_title("$course->shortname: $strname");
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading($strname);

$iszoommanager = has_capability('mod/zoom:addinstance', $context);

// Set up html table.
$table = new html_table();
$table->attributes['class'] = 'generaltable mod_view';
// Pooled-hosts feature: availability gets its own column (retention window).
if ($iszoommanager) {
    $table->align = ['left', 'left', 'left', 'left', 'left'];
    $table->head = [
        get_string('recordingdate', 'mod_zoom'),
        get_string('recordinglink', 'mod_zoom'),
        get_string('recordingpasscode', 'mod_zoom'),
        get_string('recording_availability', 'mod_zoom'),
        get_string('recordingshowtoggle', 'mod_zoom'),
    ];
} else {
    $table->align = ['left', 'left', 'left', 'left'];
    $table->head = [
        get_string('recordingdate', 'mod_zoom'),
        get_string('recordinglink', 'mod_zoom'),
        get_string('recordingpasscode', 'mod_zoom'),
        get_string('recording_availability', 'mod_zoom'),
    ];
}

// Find all entries for this meeting in the database.
$recordings = zoom_get_meeting_recordings_grouped($zoom->id);
if (empty($recordings)) {
    $cell = new html_table_cell();
    $cell->colspan = count($table->head);
    $cell->text = get_string('norecordings', 'mod_zoom');
    $cell->style = 'text-align: center';
    $row = new html_table_row([$cell]);
    $table->data = [$row];
} else {
    foreach ($recordings as $grouping) {
        // Output the related recordings into the same row.
        $recordingdate = '';
        $recordinghtml = '';
        $recordingpasscode = '';
        $recordingshowhtml = '';
        $anyactive = false;
        $anypurged = false;
        $availabilitycell = '';
        foreach ($grouping as $recording) {
            // If zoom admin -> show all recordings.
            // Or if visible to students.
            if ($iszoommanager || intval($recording->showrecording) === 1) {
                if (empty($recordingdate)) {
                    // Upstream hardcoded a US-English format with a literal
                    // "PT" suffix regardless of the actual timezone; render
                    // in the viewing user's timezone and locale instead.
                    $recordingdate = userdate($recording->recordingstart);
                }

                // Pooled-hosts feature: rows with a play token embed the
                // passcode in the link — nothing for the viewer to type, so
                // show the passcode only for legacy rows without one.
                // (Passcode is nullable — coalesce for htmlspecialchars on
                // PHP 8.1+.)
                if (empty($recordingpasscode) && empty($recording->playpasscode)) {
                    $recordingpasscode = $recording->passcode ?? '';
                }

                if ($iszoommanager && empty($recordingshowhtml)) {
                    $isrecordinghidden = intval($recording->showrecording) === 0;
                    $urlparams = [
                        'id' => $cm->id,
                        'meetinguuid' => $recording->meetinguuid,
                        'recordingstart' => $recording->recordingstart,
                        'showrecording' => ($isrecordinghidden) ? 1 : 0,
                        'sesskey' => sesskey(),
                    ];
                    // If the user is a zoom admin, show the button to toggle whether students can see the recording or not.
                    $recordingshowurl = new moodle_url('/mod/zoom/showrecording.php', $urlparams);
                    $recordingshowtext = get_string('recordinghide', 'mod_zoom');
                    if ($isrecordinghidden) {
                        $recordingshowtext = get_string('recordingshow', 'mod_zoom');
                    }

                    $btnclass = 'btn btn-';
                    $btnclass .= $isrecordinghidden ? 'dark' : 'primary';
                    $recordingshowbutton = html_writer::div($recordingshowtext, $btnclass);
                    $recordingshowbuttonhtml = html_writer::link($recordingshowurl, $recordingshowbutton);
                    $recordingshowhtml = html_writer::div($recordingshowbuttonhtml);
                }

                $recordingname = trim($recording->name) . ' (' . zoom_get_recording_type_string($recording->recordingtype) . ')';

                // Pooled-hosts feature: purged recordings stay listed —
                // "it was there yesterday" must never become an empty
                // list — but render as plain text, no link; the
                // availability column carries the explanation.
                if (!empty($recording->timepurged)) {
                    $anypurged = true;
                    $recordinghtml .= html_writer::div(html_writer::span($recordingname, 'recording-expired text-muted'), 'recording', ['style' => 'margin-bottom:.5rem']);
                    continue;
                }

                $anyactive = true;

                $params = ['id' => $cm->id, 'recordingid' => $recording->id];
                $recordingurl = new moodle_url('/mod/zoom/loadrecording.php', $params);
                $recordinglink = html_writer::link($recordingurl, $recordingname);
                $recordinglinkhtml = html_writer::span($recordinglink, 'recording-link', ['style' => 'margin-right:1rem']);
                $recordinghtml .= html_writer::div($recordinglinkhtml, 'recording', ['style' => 'margin-bottom:.5rem']);

                // Availability column: until recordingstart + the effective
                // retention — the activity override when set, else
                // zoom/recordingretentiondays (all files of a session share
                // the same clock). Override 0 = kept indefinitely: no date.
                $retentiondays = isset($zoom->recordingretention)
                    ? (int) $zoom->recordingretention
                    : (int) get_config('zoom', 'recordingretentiondays');
                if ($retentiondays > 0 && empty($availabilitycell)) {
                    $until = userdate($recording->recordingstart + ($retentiondays * DAYSECS), get_string('strftimedate', 'core_langconfig'));
                    $availabilitycell = get_string('recording_available_until', 'mod_zoom', $until);
                }
            }
        }

        if (!$anyactive && $anypurged) {
            $availabilitycell = get_string('recording_expired', 'mod_zoom');
        }

        // Output only one row per grouping. The show/hide toggle column
        // exists only in the manager layout (upstream appended the cell for
        // everyone, leaving students with one more cell than headers).
        $row = [$recordingdate, $recordinghtml, htmlspecialchars($recordingpasscode), $availabilitycell];
        if ($iszoommanager) {
            $row[] = $recordingshowhtml;
        }

        $table->data[] = $row;
    }

    // Pooled-hosts feature: when no shown row needs a passcode (play tokens
    // embed it in the link), drop the whole passcode column instead of
    // rendering an empty one.
    $haspasscode = false;
    foreach ($table->data as $row) {
        if (!empty($row[2])) {
            $haspasscode = true;
            break;
        }
    }

    if (!$haspasscode) {
        unset($table->head[2]);
        unset($table->align[2]);
        $table->head = array_values($table->head);
        $table->align = array_values($table->align);
        foreach ($table->data as $key => $row) {
            unset($row[2]);
            $table->data[$key] = array_values($row);
        }
    }
}

/**
 * Get the display name for a Zoom recording type.
 *
 * @package mod_zoom
 * @param string $recordingtype Zoom recording type.
 * @return string
 */
function zoom_get_recording_type_string($recordingtype) {
    $recordingtypestringmap = [
        'active_speaker' => 'recordingtype_active_speaker',
        'audio_interpretation' => 'recordingtype_audio_interpretation',
        'audio_only' => 'recordingtype_audio_only',
        'audio_transcript' => 'recordingtype_audio_transcript',
        'chat_file' => 'recordingtype_chat',
        'closed_caption' => 'recordingtype_closed_caption',
        'gallery_view' => 'recordingtype_gallery',
        'poll' => 'recordingtype_poll',
        'production_studio' => 'recordingtype_production_studio',
        'shared_screen' => 'recordingtype_shared',
        'shared_screen_with_gallery_view' => 'recordingtype_shared_gallery',
        'shared_screen_with_speaker_view' => 'recordingtype_shared_speaker',
        'shared_screen_with_speaker_view(CC)' => 'recordingtype_shared_speaker_cc',
        'sign_interpretation' => 'recordingtype_sign',
        'speaker_view' => 'recordingtype_speaker',
        'summary' => 'recordingtype_summary',
        'summary_next_steps' => 'recordingtype_summary_next_steps',
        'summary_smart_chapters' => 'recordingtype_summary_smart_chapters',
        'timeline' => 'recordingtype_timeline',
    ];

    // Return some default string in case new recordingtype values are added in the future.
    if (empty($recordingtypestringmap[$recordingtype])) {
        return $recordingtype;
    }

    return get_string($recordingtypestringmap[$recordingtype], 'mod_zoom');
}

echo html_writer::table($table);

echo $OUTPUT->footer();
