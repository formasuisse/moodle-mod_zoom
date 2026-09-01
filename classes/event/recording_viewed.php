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
 * A student viewed a Zoom cloud recording.
 *
 * @package    mod_zoom
 * @copyright  2026 FormaSuisse
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoom\event;

use coding_exception;
use moodle_url;

/**
 * Fired when a student opens a recording in the embedded player, so the view lands
 * in the standard Moodle log (activity journal / reports), not only the private
 * zoom_meeting_recordings_view table. Interim embedded player, infra #1233.
 */
class recording_viewed extends \core\event\base {
    /**
     * Init method.
     */
    protected function init() {
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['crud'] = 'r';
        $this->data['objecttable'] = 'zoom_meeting_recordings';
    }

    /**
     * Validate the custom data.
     *
     * @throws coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        if (empty($this->objectid)) {
            throw new coding_exception('The objectid (recording id) must be set.');
        }
        if (!isset($this->other['cmid']) || !is_int($this->other['cmid'])) {
            throw new coding_exception('The cmid value must be set in other as an integer.');
        }
    }

    /**
     * Return the event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_recording_viewed', 'mod_zoom');
    }

    /**
     * Return the event description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' viewed the recording with id " .
            "'$this->objectid' in the zoom activity with course module id " .
            "'{$this->other['cmid']}'.";
    }

    /**
     * Return the URL of the activity.
     *
     * @return moodle_url
     */
    public function get_url() {
        return new moodle_url('/mod/zoom/view.php', ['id' => $this->other['cmid']]);
    }
}
