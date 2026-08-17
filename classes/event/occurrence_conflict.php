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
 * A future occurrence of a pooled meeting collides with another booking on
 * its host.
 *
 * Fired by the daily update_meetings sync: every Moodle-side scheduling
 * action is conflict-checked at action time, but Zoom-side (portal) edits
 * are not — Zoom itself never conflict-checks. The sync re-reads each
 * meeting's occurrences and re-tests them against the host's calendar, so a
 * portal-made double-booking surfaces here instead of on session day.
 * Payload carries cmid/courseid/meetingid/hostid and the colliding start.
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
 * Occurrence conflict event class.
 */
class occurrence_conflict extends \core\event\base {
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
        return get_string('event_occurrence_conflict', 'mod_zoom');
    }

    /**
     * Returns a short description for the event.
     *
     * @return string
     */
    public function get_description() {
        return 'Meeting ' . ($this->other['meetingid'] ?? 0) . ' has an occurrence at '
            . ($this->other['start'] ?? 0) . ' colliding with another booking on pool host '
            . ($this->other['hostid'] ?? '') . '.';
    }
}
