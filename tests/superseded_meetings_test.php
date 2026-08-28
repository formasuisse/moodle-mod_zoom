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
 * Unit tests for the record of superseded Zoom meetings.
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
 * Tests the memory of the Zoom meetings an activity used to live on, which
 * is what keeps a late-processed recording attached to the right activity
 * after a recreate swapped the meeting id.
 *
 * @covers ::zoom_record_superseded_meeting
 * @covers ::zoom_get_superseded_meeting_records
 * @covers ::zoom_get_all_meeting_records
 */
final class superseded_meetings_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_a_superseded_meeting_is_recorded_with_its_own_host(): void {
        global $DB;

        // The old host is deliberately NOT the activity's new host: a pooled
        // recreate can land on a different pool member, and the recording
        // listing is per host.
        zoom_record_superseded_meeting(42, 66222662106, 'OLDHOSTaaaaaaaaaaaaaaa');

        $rows = zoom_get_superseded_meeting_records();
        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertSame(42, (int) $row->zoomid);
        $this->assertSame(66222662106, (int) $row->meeting_id);
        $this->assertSame('OLDHOSTaaaaaaaaaaaaaaa', $row->host_id);
        $this->assertNotEmpty($row->timecreated);
        $this->assertSame(1, $DB->count_records('zoom_superseded_meetings'));
    }

    public function test_recording_the_same_meeting_twice_is_a_noop(): void {
        global $DB;

        zoom_record_superseded_meeting(42, 66222662106, 'HOSTA');
        zoom_record_superseded_meeting(42, 66222662106, 'HOSTA');

        $this->assertSame(1, $DB->count_records('zoom_superseded_meetings'));
    }

    public function test_an_activity_accumulates_several_superseded_meetings(): void {
        zoom_record_superseded_meeting(42, 11111111111, 'HOSTA');
        zoom_record_superseded_meeting(42, 22222222222, 'HOSTB');

        $meetingids = array_map(function ($row) {
            return (int) $row->meeting_id;
        }, array_values(zoom_get_superseded_meeting_records()));

        $this->assertSame([11111111111, 22222222222], $meetingids);
    }

    public function test_nothing_is_recorded_without_a_real_meeting(): void {
        global $DB;

        zoom_record_superseded_meeting(42, 0, 'HOSTA');
        zoom_record_superseded_meeting(42, -1, 'HOSTA');   // Sentinel for "no meeting yet".
        zoom_record_superseded_meeting(42, 66222662106, '');

        $this->assertSame(0, $DB->count_records('zoom_superseded_meetings'));
    }

    public function test_recording_discovery_still_covers_activities_gone_from_zoom(): void {
        global $DB;

        // The regression this guards: an activity marked expired used to drop
        // out of zoom_get_all_meeting_records() entirely, so the recording of
        // its final session — the reason it is expired — was never synced.
        $live = $this->zoomrecord(ZOOM_MEETING_EXISTS);
        $expired = $this->zoomrecord(ZOOM_MEETING_EXPIRED);

        $ids = array_map(function ($record) {
            return (int) $record->id;
        }, zoom_get_all_meeting_records());

        $this->assertContains($live, $ids);
        $this->assertContains($expired, $ids);
        $this->assertSame(2, $DB->count_records('zoom'));
    }

    /**
     * Insert a minimal zoom activity row.
     *
     * @param int $existsonzoom ZOOM_MEETING_EXISTS or ZOOM_MEETING_EXPIRED.
     * @return int The new zoom table id.
     */
    private function zoomrecord(int $existsonzoom): int {
        global $DB;

        return (int) $DB->insert_record('zoom', (object) [
            'course' => 1,
            'meeting_id' => random_int(10000000000, 99999999999),
            'host_id' => 'HOST' . $existsonzoom,
            'name' => 'Test ' . $existsonzoom,
            'exists_on_zoom' => $existsonzoom,
            'start_time' => time() - DAYSECS,
            'duration' => HOURSECS,
            'recurring' => 0,
            'timemodified' => time(),
        ]);
    }
}
