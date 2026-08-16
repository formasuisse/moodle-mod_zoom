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
 * Unit tests for the pooled occurrence store and plan helpers.
 *
 * @package    mod_zoom
 * @copyright  2026 FormaSuisse SA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoom;

use advanced_testcase;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/zoom/locallib.php');

/**
 * Tests zoom_pooled_sync_occurrences(), zoom_pooled_collect_plan() and
 * zoom_pooled_occurrence_slots() — the DB/pure halves of occurrence-first
 * scheduling (the Zoom API halves are covered by measured E2E).
 *
 * @covers ::zoom_pooled_sync_occurrences
 * @covers ::zoom_pooled_collect_plan
 * @covers ::zoom_pooled_occurrence_slots
 */
final class pooled_occurrence_store_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * A raw-API-shaped occurrence object.
     *
     * @param string $id occurrence_id.
     * @param string $isostart ISO8601 UTC start.
     * @param int $minutes Duration in minutes.
     * @param string $status available|deleted.
     * @return stdClass
     */
    private function rawocc(string $id, string $isostart, int $minutes = 60, string $status = 'available'): stdClass {
        return (object) [
            'occurrence_id' => $id,
            'start_time' => $isostart,
            'duration' => $minutes,
            'status' => $status,
        ];
    }

    public function test_sync_inserts_updates_and_removes(): void {
        global $DB;

        $zoomid = 4242;
        zoom_pooled_sync_occurrences($zoomid, [
            $this->rawocc('1000000000000', '2027-06-07T07:00:00Z'),
            $this->rawocc('2000000000000', '2027-06-14T07:00:00Z'),
        ]);
        $this->assertSame(2, $DB->count_records('zoom_occurrences', ['zoomid' => $zoomid]));
        $row = $DB->get_record('zoom_occurrences', ['zoomid' => $zoomid, 'occurrenceid' => '1000000000000']);
        $this->assertSame(strtotime('2027-06-07T07:00:00Z'), (int) $row->starttime);
        $this->assertSame(3600, (int) $row->duration);
        $this->assertSame('available', $row->status);

        // Move one, tombstone the other, add a third — one sync call.
        zoom_pooled_sync_occurrences($zoomid, [
            $this->rawocc('1000000000000', '2027-06-08T07:00:00Z'),
            $this->rawocc('2000000000000', '2027-06-14T07:00:00Z', 60, 'deleted'),
            $this->rawocc('3000000000000', '2027-06-21T07:00:00Z', 90),
        ]);
        $this->assertSame(3, $DB->count_records('zoom_occurrences', ['zoomid' => $zoomid]));
        $this->assertSame(
            strtotime('2027-06-08T07:00:00Z'),
            (int) $DB->get_field('zoom_occurrences', 'starttime', ['zoomid' => $zoomid, 'occurrenceid' => '1000000000000'])
        );
        $this->assertSame(
            'deleted',
            $DB->get_field('zoom_occurrences', 'status', ['zoomid' => $zoomid, 'occurrenceid' => '2000000000000'])
        );
        $this->assertSame(
            90 * 60,
            (int) $DB->get_field('zoom_occurrences', 'duration', ['zoomid' => $zoomid, 'occurrenceid' => '3000000000000'])
        );

        // Rows absent from the list are removed.
        zoom_pooled_sync_occurrences($zoomid, [
            $this->rawocc('3000000000000', '2027-06-21T07:00:00Z', 90),
        ]);
        $this->assertSame(
            ['3000000000000'],
            array_keys($DB->get_records('zoom_occurrences', ['zoomid' => $zoomid], '', 'occurrenceid'))
        );
    }

    public function test_sync_accepts_normalised_occurrences(): void {
        global $DB;

        // populate_zoom_from_response() shape: epoch seconds / duration seconds.
        zoom_pooled_sync_occurrences(7, [
            (object) ['occurrence_id' => '9000000000000', 'start_time' => 1780000000, 'duration' => 5400, 'status' => 'available'],
        ]);
        $row = $DB->get_record('zoom_occurrences', ['zoomid' => 7]);
        $this->assertSame(1780000000, (int) $row->starttime);
        $this->assertSame(5400, (int) $row->duration);
    }

    public function test_collect_plan_dedupes_and_sorts(): void {
        $data = (object) [
            'start_time' => 500,
            'plandates' => [300, 0, 500, 400, 0],
        ];
        $this->assertSame([300, 400, 500], zoom_pooled_collect_plan($data));
    }

    public function test_collect_plan_first_session_only(): void {
        $this->assertSame([1234], zoom_pooled_collect_plan((object) ['start_time' => 1234]));
    }

    public function test_occurrence_slots_skips_tombstones_and_converts_units(): void {
        $zoom = (object) ['duration' => 7200];
        $slots = zoom_pooled_occurrence_slots($zoom, [
            $this->rawocc('1', '2027-06-07T07:00:00Z', 90),
            $this->rawocc('2', '2027-06-14T07:00:00Z', 60, 'deleted'),
            (object) ['occurrence_id' => '3', 'start_time' => 1780000000, 'duration' => 3600, 'status' => 'available'],
            (object) ['occurrence_id' => '4', 'start_time' => 1780600000, 'duration' => 0, 'status' => 'available'],
        ]);
        $this->assertSame([
            [strtotime('2027-06-07T07:00:00Z'), 5400],
            [1780000000, 3600],
            [1780600000, 7200], // Zero duration falls back to the series duration.
        ], $slots);
    }
}
