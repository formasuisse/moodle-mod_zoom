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
 * A pooled session runs past its scheduled end while the host has a next booking.
 *
 * Fired by the end-of-session task AHEAD of the conflict: the running session
 * exceeded its scheduled end and the same pool host has an upcoming booking
 * within the buffer window — warn ops while there is still time to intervene.
 * Contrast with collision_imminent, which fires AT the moment of conflict:
 * a teacher is clicking Start while the host is still in another live meeting
 * (proceeding will end that meeting — measured, T12).
 *
 * Pooled-hosts feature (see README.md, 'Pooled hosts mode').
 * Deployment-specific alert routing (e.g. Slack) subscribes to this event
 * from a local plugin — the fork carries no routing logic.
 *
 * @package   mod_zoom
 * @copyright 2026 FormaSuisse SA
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoom\event;

/**
 * Overrun detected event class.
 */
class overrun_detected extends \core\event\base {
    /**
     * Initializes the event.
     */
    protected function init() {
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['crud'] = 'r';
        $this->data['objecttable'] = 'zoom';
    }

    /**
     * Returns the name of the event.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_overrun_detected', 'mod_zoom');
    }

    /**
     * Returns a short description for the event.
     *
     * @return string
     */
    public function get_description() {
        return 'Meeting ' . ($this->other['meetingid'] ?? 0) . ' on pool host ' . ($this->other['hostid'] ?? '') . ' runs past its scheduled end and the host has an upcoming booking.';
    }
}
