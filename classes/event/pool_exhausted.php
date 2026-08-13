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
 * No pool host free for a requested slot — the buy-a-seat signal.
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
 * Pool exhausted event class.
 */
class pool_exhausted extends \core\event\base {
    /**
     * Initializes the event.
     */
    protected function init() {
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['crud'] = 'r';
    }

    /**
     * Returns the name of the event.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_pool_exhausted', 'mod_zoom');
    }

    /**
     * Returns a short description for the event.
     *
     * @return string
     */
    public function get_description() {
        return 'No pooled Zoom host was free for the requested slot (start ' . ($this->other['start'] ?? 0) . ').';
    }
}
