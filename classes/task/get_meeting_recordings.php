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
 * The task for getting recordings from Zoom to Moodle.
 *
 * @package    mod_zoom
 * @author     Jwalit Shah <jwalitshah@catalyst-au.net>
 * @copyright  2021 Jwalit Shah <jwalitshah@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoom\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/zoom/locallib.php');

use core\task\scheduled_task;
use moodle_exception;
use stdClass;

/**
 * Scheduled task to get the meeting recordings.
 */
class get_meeting_recordings extends scheduled_task {
    /**
     * Returns name of task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('getmeetingrecordings', 'mod_zoom');
    }

    /**
     * Get any new recordings that have been added on zoom.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        try {
            $service = zoom_webservice();
        } catch (moodle_exception $exception) {
            mtrace('Skipping task - ', $exception->getMessage());
            return;
        }

        $config = get_config('zoom');
        if (empty($config->viewrecordings)) {
            mtrace('Skipping task - ', get_string('zoomerr_viewrecordings_off', 'zoom'));
            return;
        }

        // Required scopes for meeting recordings.
        $requiredscopes = [
            'classic' => [
                'recording:read:admin',
            ],
            'granular' => [
                'cloud_recording:read:list_user_recordings:admin',
                'cloud_recording:read:list_recording_files:admin',
            ],
        ];

        // Checking for missing scopes.
        $missingscopes = $service->check_scopes($requiredscopes);
        if (!empty($missingscopes)) {
            foreach ($missingscopes as $missingscope) {
                mtrace('Missing scope: ' . $missingscope);
            }
            return;
        }

        // See if we cannot make anymore API calls.
        $retryafter = get_config('zoom', 'retry-after');
        if (!empty($retryafter) && time() < $retryafter) {
            mtrace('Out of API calls, retry after ' . userdate($retryafter, get_string('strftimedaydatetime', 'core_langconfig')));
            return;
        }

        mtrace('Finding meeting recordings for this account...');

        $localmeetings = zoom_get_all_meeting_records();

        $now = time();
        $from = gmdate('Y-m-d', strtotime('-1 day', $now));
        $to = gmdate('Y-m-d', strtotime('+1 day', $now));

        $hostmeetings = [];

        $byzoomid = [];
        foreach ($localmeetings as $zoom) {
            $byzoomid[$zoom->id] = $zoom;
            // Only get recordings for this meeting if its recurring or already finished.
            if ($zoom->recurring || $now > (intval($zoom->start_time) + intval($zoom->duration))) {
                $hostmeetings[$zoom->host_id][$zoom->meeting_id] = $zoom;
            }
        }

        // An activity can have lived on several Zoom meetings in succession
        // (a recreate mints a new id — Zoom has no undelete). Their
        // recordings still belong to this activity, and Zoom takes minutes
        // to hours to finish processing one, so a meeting superseded today
        // may only surface its last recording tomorrow. Index the old ids
        // under the host that owned them, alongside the current meeting.
        foreach (zoom_get_superseded_meeting_records() as $superseded) {
            if (!isset($byzoomid[$superseded->zoomid])) {
                continue;
            }

            if (isset($hostmeetings[$superseded->host_id][$superseded->meeting_id])) {
                continue;
            }

            $hostmeetings[$superseded->host_id][$superseded->meeting_id] = $byzoomid[$superseded->zoomid];
        }

        if (empty($hostmeetings)) {
            mtrace('No meetings need to be processed.');
            return;
        }

        $meetingdata = [];
        $touchedsets = [];
        $localrecordings = zoom_get_meeting_recordings_grouped();

        foreach ($hostmeetings as $hostid => $meetings) {
            // Fetch all recordings for this user.
            $zoomrecordings = $service->get_user_recordings($hostid, $from, $to);

            foreach ($zoomrecordings as $recordingid => $recording) {
                if (isset($localrecordings[$recording->meetinguuid][$recordingid])) {
                    mtrace('Recording id: ' . $recordingid . ' exists...skipping');
                    $localrecording = $localrecordings[$recording->meetinguuid][$recordingid];

                    if ($localrecording->recordingtype !== $recording->recordingtype) {
                        $updatemeeting = (object) [
                            'id' => $localrecording->id,
                            'recordingtype' => $recording->recordingtype,
                        ];
                        $DB->update_record('zoom_meeting_recordings', $updatemeeting);
                    }
                    continue;
                }

                if (empty($meetings[$recording->meetingid])) {
                    // Skip meetings that are not in Moodle.
                    mtrace('Meeting id: ' . $recording->meetingid . ' does not exist...skipping');
                    continue;
                }

                // Pooled-hosts feature: fetch the meeting's own recordings
                // response once per meeting. It carries the passcode, the
                // URL-safe play token and per-file URLs — token and URL must
                // come from the SAME response (Zoom re-mints all link fields
                // on every call).
                if (empty($meetingdata[$recording->meetinguuid])) {
                    try {
                        $data = $service->get_meeting_recording_data($recording->meetinguuid);
                        $files = [];
                        foreach ($data->recording_files ?? [] as $file) {
                            $files[$file->id] = $file;
                        }

                        $meetingdata[$recording->meetinguuid] = (object) [
                            'password' => $data->password ?? '',
                            'playpasscode' => $data->recording_play_passcode ?? '',
                            'files' => $files,
                        ];
                    } catch (moodle_exception $error) {
                        continue;
                    }
                }

                $zoom = $meetings[$recording->meetingid];
                $recordingtype = $recording->recordingtype;

                $record = new stdClass();
                $record->zoomid = $zoom->id;
                $record->meetinguuid = $recording->meetinguuid;
                $record->zoomrecordingid = $recordingid;
                $record->name = $zoom->name;
                $meetinginfo = $meetingdata[$recording->meetinguuid];
                $samecallfile = $meetinginfo->files[$recordingid] ?? null;
                $record->externalurl = $samecallfile->play_url ?? $recording->url;
                $record->passcode = $meetinginfo->password;
                $record->playpasscode = $meetinginfo->playpasscode;
                $record->recordingtype = $recordingtype;
                $record->recordingstart = $recording->recordingstart;
                $record->showrecording = $zoom->recordings_visible_default;
                $record->timecreated = $now;
                $record->timemodified = $now;

                $record->id = $DB->insert_record('zoom_meeting_recordings', $record);
                $touchedsets[$recording->meetinguuid] = true;
                mtrace('Recording id: ' . $recordingid . ' (' . $recordingtype . ') added to the database');
            }
        }

        // New rows land at recordings_visible_default, which defaults to
        // visible — and a recording set Zoom created stays unshared until it
        // is told otherwise, so the common path (nobody touches the toggle)
        // needs this to be watchable at all.
        foreach (array_keys($touchedsets) as $meetinguuid) {
            if (!zoom_recording_sharing_sync($meetinguuid, $service)) {
                mtrace('Could not update Zoom sharing for recording set: ' . $meetinguuid);
            }
        }
    }
}
