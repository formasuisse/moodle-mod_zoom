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
 * Task enforcing the recording retention window (pooled-hosts feature).
 *
 * When zoom/recordingretentiondays is set, cloud recordings are moved to the
 * Zoom trash (recoverable for 30 days in the portal) that many days after
 * the session. The Moodle rows stay — the list keeps showing the session as
 * "no longer available" instead of silently losing entries — but all link
 * material is cleared. Every minted link alias dies with the recording.
 *
 * @package    mod_zoom
 * @copyright  2026 FormaSuisse
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoom\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/zoom/locallib.php');

use core\task\scheduled_task;
use moodle_exception;

/**
 * Scheduled task to purge expired recordings on Zoom and in Moodle.
 */
class purge_recordings extends scheduled_task {
    /**
     * Returns name of task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('purgerecordings', 'mod_zoom');
    }

    /**
     * Purge recordings older than the retention window.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $days = get_config('zoom', 'recordingretentiondays');
        if (empty($days)) {
            mtrace('Recording retention disabled (recordingretentiondays is 0/unset)...skipping');
            return;
        }

        try {
            $service = zoom_webservice();
        } catch (moodle_exception $exception) {
            mtrace('Skipping task - ', $exception->getMessage());
            return;
        }

        $cutoff = time() - ((int) $days * DAYSECS);
        $expired = $DB->get_records_select(
            'zoom_meeting_recordings',
            'recordingstart < :cutoff AND (timepurged IS NULL OR timepurged = 0)',
            ['cutoff' => $cutoff]
        );
        if (empty($expired)) {
            mtrace('No recordings past the ' . $days . '-day retention window.');
            return;
        }

        // Group by meeting: Zoom deletion is per meeting-recording-set.
        $bymeeting = [];
        foreach ($expired as $recording) {
            $bymeeting[$recording->meetinguuid][] = $recording;
        }

        foreach ($bymeeting as $meetinguuid => $recordings) {
            try {
                $service->delete_meeting_recordings($meetinguuid);
            } catch (moodle_exception $error) {
                // Fail loudly per meeting and keep the Moodle rows: the task
                // retries daily, and rows must never outlive-or-predecease
                // their Zoom recording silently.
                mtrace('Unable to trash Zoom recordings for meeting ' . $meetinguuid . ': ' . $error->getMessage());
                continue;
            }

            foreach ($recordings as $recording) {
                // Keep the row (the list keeps showing the session with a
                // "no longer available" note) and the view history; drop the
                // dead link material.
                $DB->update_record('zoom_meeting_recordings', (object) [
                    'id' => $recording->id,
                    'externalurl' => '',
                    'passcode' => '',
                    'playpasscode' => '',
                    'timepurged' => time(),
                    'timemodified' => time(),
                ]);
                mtrace('Purged recording id: ' . $recording->zoomrecordingid . ' (' . $recording->recordingtype . ')');
            }
        }
    }
}
