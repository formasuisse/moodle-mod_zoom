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
 * Unit tests for recording sharing reconciliation.
 *
 * @package    mod_zoom
 * @copyright  2026 FormaSuisse
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoom;

use advanced_testcase;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/zoom/locallib.php');

/**
 * Tests zoom_recording_sharing_sync(): Moodle's per-row visibility drives
 * Zoom's per-set share_recording flag.
 *
 * @covers ::zoom_recording_sharing_sync
 */
final class recording_sharing_test extends advanced_testcase {
    /** @var array Calls captured from the mocked webservice. */
    private $calls = [];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->calls = [];
    }

    /**
     * Insert a recording row.
     *
     * @param string $meetinguuid Recording set the row belongs to.
     * @param int $show showrecording flag.
     * @param string $recordingid Zoom's per-file recording id.
     * @param int|null $timepurged Purge timestamp, null when still on Zoom.
     * @return void
     */
    private function recording($meetinguuid, $show, $recordingid, $timepurged = null) {
        global $DB;

        $DB->insert_record('zoom_meeting_recordings', (object) [
            'zoomid' => 1,
            'meetinguuid' => $meetinguuid,
            'zoomrecordingid' => $recordingid,
            'name' => 'Session',
            'externalurl' => 'https://example.org/rec/' . $recordingid,
            'passcode' => 'passcode',
            'playpasscode' => 'playtoken',
            'timepurged' => $timepurged,
            'recordingtype' => 'active_speaker',
            'recordingstart' => 1000,
            'showrecording' => $show,
            'timecreated' => 1000,
            'timemodified' => 1000,
        ]);
    }

    /**
     * A webservice whose set_recording_sharing() records its arguments.
     *
     * @param bool $throws Whether the call should fail like a Zoom API error.
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function mockservice($throws = false) {
        $mock = $this->getMockBuilder(webservice::class)
            ->onlyMethods(['set_recording_sharing'])
            ->disableOriginalConstructor()
            ->getMock();

        if ($throws) {
            $mock->method('set_recording_sharing')
                ->willThrowException(new moodle_exception('errorwebservice', 'mod_zoom', '', 'boom'));
        } else {
            $mock->method('set_recording_sharing')
                ->willReturnCallback(function ($meetinguuid, $shared) {
                    $this->calls[] = [$meetinguuid, $shared];
                });
        }

        return $mock;
    }

    public function test_visible_recording_is_shared(): void {
        $this->recording('uuid-a', 1, 'rec-1');

        $this->assertTrue(zoom_recording_sharing_sync('uuid-a', $this->mockservice()));
        $this->assertSame([['uuid-a', true]], $this->calls);
    }

    public function test_hidden_recording_is_unshared(): void {
        $this->recording('uuid-a', 0, 'rec-1');

        $this->assertTrue(zoom_recording_sharing_sync('uuid-a', $this->mockservice()));
        $this->assertSame([['uuid-a', false]], $this->calls);
    }

    public function test_one_visible_row_shares_the_whole_set(): void {
        // Zoom shares per set, Moodle hides per row: the video row is visible
        // while the audio row is not, and the set must still be shared.
        $this->recording('uuid-a', 0, 'rec-audio');
        $this->recording('uuid-a', 1, 'rec-video');

        zoom_recording_sharing_sync('uuid-a', $this->mockservice());
        $this->assertSame([['uuid-a', true]], $this->calls);
    }

    public function test_other_sets_do_not_leak_in(): void {
        // A visible row on a different meeting must not keep this set shared.
        $this->recording('uuid-a', 0, 'rec-1');
        $this->recording('uuid-b', 1, 'rec-2');

        zoom_recording_sharing_sync('uuid-a', $this->mockservice());
        $this->assertSame([['uuid-a', false]], $this->calls);
    }

    public function test_purged_rows_never_count_as_visible(): void {
        // Retention moved this one to the Zoom trash; a stale showrecording
        // flag must not ask Zoom to share a recording that is gone.
        $this->recording('uuid-a', 1, 'rec-purged', 1234);
        $this->recording('uuid-a', 0, 'rec-live');

        zoom_recording_sharing_sync('uuid-a', $this->mockservice());
        $this->assertSame([['uuid-a', false]], $this->calls);
    }

    public function test_fully_purged_set_is_not_called(): void {
        $this->recording('uuid-a', 1, 'rec-purged', 1234);

        $this->assertTrue(zoom_recording_sharing_sync('uuid-a', $this->mockservice()));
        $this->assertSame([], $this->calls);
    }

    public function test_unknown_set_is_not_called(): void {
        $this->assertTrue(zoom_recording_sharing_sync('uuid-missing', $this->mockservice()));
        $this->assertSame([], $this->calls);
    }

    public function test_api_failure_is_reported(): void {
        $this->recording('uuid-a', 1, 'rec-1');

        $this->assertFalse(zoom_recording_sharing_sync('uuid-a', $this->mockservice(true)));
        $this->assertDebuggingCalled();
    }
}
