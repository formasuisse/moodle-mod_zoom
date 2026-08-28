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
 * Unit tests for how the occurrence table treats a purged series.
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
 * A series whose Zoom meeting has been purged (GET returns 3001) is not a
 * dead activity: its sessions and recordings stay listed and it can be
 * continued by adding a date. Only the per-row actions, which need a live
 * meeting to act on, go away.
 *
 * @covers ::zoom_pooled_occurrence_table_context
 */
final class pooled_purged_series_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('pooledhostsgroup', 'Test pool', 'zoom');
    }

    /**
     * A recurring pooled activity with one past and one future session.
     *
     * @param int $existsonzoom ZOOM_MEETING_EXISTS or ZOOM_MEETING_EXPIRED.
     * @return stdClass The zoom record.
     */
    private function series(int $existsonzoom): stdClass {
        global $DB;

        $id = $DB->insert_record('zoom', (object) [
            'course' => 1,
            'meeting_id' => 66222662106,
            'host_id' => 'POOLHOST',
            'name' => 'Test',
            'exists_on_zoom' => $existsonzoom,
            'start_time' => time() - 7 * DAYSECS,
            'duration' => HOURSECS,
            'recurring' => 1,
            'recurrence_type' => ZOOM_RECURRINGTYPE_WEEKLY,
            'timemodified' => time(),
        ]);

        foreach ([time() - 7 * DAYSECS, time() + 7 * DAYSECS] as $i => $start) {
            $DB->insert_record('zoom_occurrences', (object) [
                'zoomid' => $id,
                'occurrenceid' => (string) ($start * 1000),
                'starttime' => $start,
                'duration' => HOURSECS,
                'status' => 'available',
                'timemodified' => time(),
            ]);
        }

        return $DB->get_record('zoom', ['id' => $id], '*', MUST_EXIST);
    }

    public function test_a_purged_series_still_offers_the_add_form(): void {
        $context = zoom_pooled_occurrence_table_context(
            $this->series(ZOOM_MEETING_EXPIRED),
            (object) ['id' => 42],
            true
        );

        // The whole point: the manager can still continue the series.
        $this->assertTrue($context['canedit']);
        $this->assertArrayHasKey('addform', $context);
    }

    public function test_a_purged_series_keeps_listing_its_sessions(): void {
        $context = zoom_pooled_occurrence_table_context(
            $this->series(ZOOM_MEETING_EXPIRED),
            (object) ['id' => 42],
            true
        );

        $this->assertCount(2, $context['occurrences']);
        $this->assertTrue($context['occurrences'][0]['past']);
    }

    public function test_a_purged_series_offers_no_per_row_actions(): void {
        // There is no Zoom meeting left to move or cancel an occurrence on,
        // so those must not be offered even for a future row.
        $context = zoom_pooled_occurrence_table_context(
            $this->series(ZOOM_MEETING_EXPIRED),
            (object) ['id' => 42],
            true
        );

        foreach ($context['occurrences'] as $occurrence) {
            $this->assertFalse($occurrence['editable']);
            $this->assertNull($occurrence['cancelurl']);
            $this->assertNull($occurrence['discardurl']);
        }
    }

    public function test_a_live_series_keeps_its_per_row_actions(): void {
        // Guards the split: relaxing canedit must not have cost the live case.
        $context = zoom_pooled_occurrence_table_context(
            $this->series(ZOOM_MEETING_EXISTS),
            (object) ['id' => 42],
            true
        );

        $future = $context['occurrences'][1];
        $this->assertTrue($future['editable']);
        $this->assertNotNull($future['cancelurl']);
    }

    public function test_a_student_gets_no_actions_on_a_purged_series(): void {
        $context = zoom_pooled_occurrence_table_context(
            $this->series(ZOOM_MEETING_EXPIRED),
            (object) ['id' => 42],
            false
        );

        $this->assertFalse($context['canedit']);
        $this->assertArrayNotHasKey('addform', $context);
    }
}
