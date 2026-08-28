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
 * Unit tests for the pending-slot derivation behind pooled host picking.
 *
 * @package    mod_zoom
 * @copyright  2026 FormaSuisse SA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoom;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/zoom/locallib.php');

/**
 * Tests zoom_pooled_pending_slots() — which sessions of a series still book
 * a pool host, and therefore what a recreate has to be conflict-checked
 * against.
 *
 * @covers ::zoom_pooled_pending_slots
 */
final class pooled_pending_slots_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Insert an occurrence row.
     *
     * @param int $zoomid zoom table id.
     * @param int $starttime Start (Unix timestamp).
     * @param int $duration Duration in seconds (0 = none stored).
     * @param string $status available|deleted|removed.
     * @return void
     */
    private function occurrence(int $zoomid, int $starttime, int $duration, string $status): void {
        global $DB;

        $DB->insert_record('zoom_occurrences', (object) [
            'zoomid' => $zoomid,
            'occurrenceid' => (string) ($starttime * 1000),
            'starttime' => $starttime,
            'duration' => $duration,
            'status' => $status,
            'timemodified' => time(),
        ]);
    }

    public function test_only_future_available_rows_book_a_host(): void {
        $zoomid = 7777;
        $now = time();

        $this->occurrence($zoomid, $now - 7 * DAYSECS, 3600, 'available');   // Past: history.
        $this->occurrence($zoomid, $now + 7 * DAYSECS, 1800, 'available');   // Books.
        $this->occurrence($zoomid, $now + 14 * DAYSECS, 0, 'available');     // Books, no duration stored.
        $this->occurrence($zoomid, $now + 21 * DAYSECS, 3600, 'deleted');    // Cancelled on Zoom.
        $this->occurrence($zoomid, $now + 28 * DAYSECS, 3600, 'removed');    // Discarded.

        $slots = zoom_pooled_pending_slots($zoomid, 2700);

        $this->assertSame([
            [$now + 7 * DAYSECS, 1800],
            [$now + 14 * DAYSECS, 2700],
        ], $slots);
    }

    public function test_a_fully_past_series_books_nothing(): void {
        // The state that makes a purged series rehostable anywhere: every
        // date is spent, so no pool member is held by it.
        $zoomid = 8888;
        $now = time();

        $this->occurrence($zoomid, $now - 30 * DAYSECS, 3600, 'available');
        $this->occurrence($zoomid, $now - 14 * DAYSECS, 3600, 'available');

        $this->assertSame([], zoom_pooled_pending_slots($zoomid, 3600));
    }

    public function test_rows_of_other_activities_are_ignored(): void {
        $now = time();
        $this->occurrence(9001, $now + 7 * DAYSECS, 3600, 'available');

        $this->assertSame([], zoom_pooled_pending_slots(9002, 3600));
    }

    public function test_duration_falls_back_to_an_hour_without_a_series_duration(): void {
        $zoomid = 9100;
        $now = time();
        $this->occurrence($zoomid, $now + 7 * DAYSECS, 0, 'available');

        $this->assertSame([[$now + 7 * DAYSECS, HOURSECS]], zoom_pooled_pending_slots($zoomid, 0));
    }
}
