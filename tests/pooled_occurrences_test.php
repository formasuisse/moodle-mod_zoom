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
 * Unit tests for the pooled-hosts recurrence expansion.
 *
 * @package    mod_zoom
 * @copyright  2026 FormaSuisse SA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoom;

use advanced_testcase;
use DateTimeImmutable;
use DateTimeZone;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/zoom/locallib.php');

/**
 * Tests zoom_pooled_expand_occurrences() against wall-clock expectations.
 *
 * The expansion feeds the pool picker (issue: only the first occurrence of a
 * recurring series used to be conflict-checked), so these tests pin down the
 * slot list for every recurrence shape the mod_form can produce. All
 * expectations are built independently with DateTimeImmutable in the site
 * timezone — PHP's timezone database is the oracle for DST behavior.
 *
 * @covers ::zoom_pooled_expand_occurrences
 * @covers ::zoom_pooled_nth_weekday_of_month
 */
final class pooled_occurrences_test extends advanced_testcase {
    /** @var string Site timezone used throughout (has a DST switch). */
    private const TZ = 'Europe/Zurich';

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $CFG->timezone = self::TZ;
    }

    /**
     * Build a meeting object like mod_form/lib.php hand to the pool picker.
     *
     * @param array $props Field overrides.
     * @return stdClass
     */
    private function make_zoom(array $props): stdClass {
        $zoom = (object) array_merge([
            'recurring' => 1,
            'recurrence_type' => ZOOM_RECURRINGTYPE_WEEKLY,
            'repeat_interval' => 1,
            'end_date_option' => ZOOM_END_DATE_OPTION_AFTER,
            'end_times' => 1,
            'duration' => 2 * HOURSECS,
        ], $props);
        return $zoom;
    }

    /**
     * Epoch of a local wall-clock datetime in the site timezone.
     *
     * @param string $local e.g. '2027-03-15 09:00'.
     * @return int
     */
    private function ts(string $local): int {
        return (new DateTimeImmutable($local, new DateTimeZone(self::TZ)))->getTimestamp();
    }

    /**
     * Start timestamps of the expanded slots.
     *
     * @param stdClass $zoom
     * @return int[]
     */
    private function starts(stdClass $zoom): array {
        return array_column(zoom_pooled_expand_occurrences($zoom), 0);
    }

    public function test_non_recurring_yields_single_slot(): void {
        $zoom = $this->make_zoom(['recurring' => 0, 'start_time' => $this->ts('2027-03-15 09:00')]);
        $this->assertSame([[$this->ts('2027-03-15 09:00'), 2 * HOURSECS]], zoom_pooled_expand_occurrences($zoom));
    }

    public function test_recurring_no_fixed_time_yields_nothing(): void {
        $zoom = $this->make_zoom(['recurrence_type' => ZOOM_RECURRINGTYPE_NOTIME]);
        $this->assertSame([], zoom_pooled_expand_occurrences($zoom));
    }

    public function test_missing_start_time_yields_nothing(): void {
        $zoom = $this->make_zoom(['end_times' => 6]);
        $this->assertSame([], zoom_pooled_expand_occurrences($zoom));
    }

    public function test_weekly_six_mondays_keep_wall_clock_across_dst(): void {
        // 2027-03-15 is a Monday; the EU DST switch is Sunday 2027-03-28.
        $zoom = $this->make_zoom([
            'start_time' => $this->ts('2027-03-15 09:00'),
            'weekly_days' => '2', // Zoom numbering: 2 = Monday.
            'end_times' => 6,
        ]);
        $expected = ['2027-03-15', '2027-03-22', '2027-03-29', '2027-04-05', '2027-04-12', '2027-04-19'];
        $this->assertSame(
            array_map(fn($d) => $this->ts($d . ' 09:00'), $expected),
            $this->starts($zoom)
        );
        // Wall clock preserved means the DST-spanning step is an hour short in epoch terms.
        $starts = $this->starts($zoom);
        $this->assertSame(7 * DAYSECS, $starts[1] - $starts[0]);
        $this->assertSame(7 * DAYSECS - HOURSECS, $starts[2] - $starts[1]);
    }

    public function test_weekly_two_days_alternate(): void {
        // 2027-06-01 is a Tuesday; Zoom days 3 = Tuesday, 5 = Thursday.
        $zoom = $this->make_zoom([
            'start_time' => $this->ts('2027-06-01 14:00'),
            'weekly_days' => '3,5',
            'end_times' => 5,
        ]);
        $expected = ['2027-06-01', '2027-06-03', '2027-06-08', '2027-06-10', '2027-06-15'];
        $this->assertSame(array_map(fn($d) => $this->ts($d . ' 14:00'), $expected), $this->starts($zoom));
    }

    public function test_weekly_start_day_not_in_days_starts_at_first_match(): void {
        // Start on a Tuesday but recur on Mondays: first slot is the next Monday.
        $zoom = $this->make_zoom([
            'start_time' => $this->ts('2027-06-01 09:00'),
            'weekly_days' => '2',
            'end_times' => 3,
        ]);
        $expected = ['2027-06-07', '2027-06-14', '2027-06-21'];
        $this->assertSame(array_map(fn($d) => $this->ts($d . ' 09:00'), $expected), $this->starts($zoom));
    }

    public function test_weekly_earlier_weekday_of_start_week_is_skipped(): void {
        // Start Thursday with Tue+Thu: the Tuesday of the start week is in the past.
        $zoom = $this->make_zoom([
            'start_time' => $this->ts('2027-06-03 09:00'),
            'weekly_days' => '3,5',
            'end_times' => 4,
        ]);
        $expected = ['2027-06-03', '2027-06-08', '2027-06-10', '2027-06-15'];
        $this->assertSame(array_map(fn($d) => $this->ts($d . ' 09:00'), $expected), $this->starts($zoom));
    }

    public function test_weekly_interval_two_skips_weeks(): void {
        $zoom = $this->make_zoom([
            'start_time' => $this->ts('2027-06-07 09:00'), // Monday.
            'weekly_days' => '2',
            'repeat_interval' => 2,
            'end_times' => 3,
        ]);
        $expected = ['2027-06-07', '2027-06-21', '2027-07-05'];
        $this->assertSame(array_map(fn($d) => $this->ts($d . ' 09:00'), $expected), $this->starts($zoom));
    }

    public function test_daily_interval(): void {
        $zoom = $this->make_zoom([
            'recurrence_type' => ZOOM_RECURRINGTYPE_DAILY,
            'start_time' => $this->ts('2027-06-01 08:30'),
            'repeat_interval' => 3,
            'end_times' => 4,
        ]);
        $expected = ['2027-06-01', '2027-06-04', '2027-06-07', '2027-06-10'];
        $this->assertSame(array_map(fn($d) => $this->ts($d . ' 08:30'), $expected), $this->starts($zoom));
    }

    public function test_monthly_by_day(): void {
        $zoom = $this->make_zoom([
            'recurrence_type' => ZOOM_RECURRINGTYPE_MONTHLY,
            'monthly_repeat_option' => ZOOM_MONTHLY_REPEAT_OPTION_DAY,
            'monthly_day' => 15,
            'start_time' => $this->ts('2027-06-15 10:00'),
            'end_times' => 3,
        ]);
        $expected = ['2027-06-15', '2027-07-15', '2027-08-15'];
        $this->assertSame(array_map(fn($d) => $this->ts($d . ' 10:00'), $expected), $this->starts($zoom));
    }

    public function test_monthly_day_31_skips_short_months(): void {
        $zoom = $this->make_zoom([
            'recurrence_type' => ZOOM_RECURRINGTYPE_MONTHLY,
            'monthly_repeat_option' => ZOOM_MONTHLY_REPEAT_OPTION_DAY,
            'monthly_day' => 31,
            'start_time' => $this->ts('2027-01-31 10:00'),
            'end_times' => 3,
        ]);
        $expected = ['2027-01-31', '2027-03-31', '2027-05-31'];
        $this->assertSame(array_map(fn($d) => $this->ts($d . ' 10:00'), $expected), $this->starts($zoom));
    }

    public function test_monthly_last_friday(): void {
        $zoom = $this->make_zoom([
            'recurrence_type' => ZOOM_RECURRINGTYPE_MONTHLY,
            'monthly_repeat_option' => ZOOM_MONTHLY_REPEAT_OPTION_WEEK,
            'monthly_week' => -1,
            'monthly_week_day' => 6, // Zoom numbering: 6 = Friday.
            'start_time' => $this->ts('2027-06-25 16:00'), // Last Friday of June 2027.
            'end_times' => 3,
        ]);
        $expected = ['2027-06-25', '2027-07-30', '2027-08-27'];
        $this->assertSame(array_map(fn($d) => $this->ts($d . ' 16:00'), $expected), $this->starts($zoom));
    }

    public function test_end_date_is_inclusive(): void {
        $zoom = $this->make_zoom([
            'start_time' => $this->ts('2027-06-07 09:00'), // Monday.
            'weekly_days' => '2',
            'end_date_option' => ZOOM_END_DATE_OPTION_BY,
            'end_date_time' => $this->ts('2027-06-28 00:00'), // The 4th Monday, date-only.
        ]);
        $expected = ['2027-06-07', '2027-06-14', '2027-06-21', '2027-06-28'];
        $this->assertSame(array_map(fn($d) => $this->ts($d . ' 09:00'), $expected), $this->starts($zoom));
    }

    public function test_occurrence_count_is_capped(): void {
        $zoom = $this->make_zoom([
            'start_time' => $this->ts('2027-06-07 09:00'),
            'weekly_days' => '2',
            'end_times' => 500,
        ]);
        $this->assertCount(100, $this->starts($zoom));
    }

    public function test_duration_carried_on_every_slot(): void {
        $zoom = $this->make_zoom([
            'start_time' => $this->ts('2027-06-07 09:00'),
            'weekly_days' => '2',
            'end_times' => 3,
            'duration' => 90 * MINSECS,
        ]);
        $this->assertSame(
            [90 * MINSECS, 90 * MINSECS, 90 * MINSECS],
            array_column(zoom_pooled_expand_occurrences($zoom), 1)
        );
    }
}
