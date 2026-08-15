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
 * End-of-session adhoc task for pooled hosts.
 *
 * Pooled-hosts feature (see README.md, 'Pooled hosts mode'). Queued
 * at the start click, nextruntime = scheduled_end + slotbuffer. While the
 * meeting is still live it re-queues itself (late starts naturally loop) and
 * fires overrun_detected when the host has an upcoming booking. Once the
 * meeting is over it restores the pool host's original profile name
 * compare-and-swap style: only when the current name still equals what we set
 * — an out-of-band change means hands off.
 *
 * @package   mod_zoom
 * @copyright 2026 FormaSuisse SA
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoom\task;

/**
 * End-of-session task class.
 */
class end_of_session extends \core\task\adhoc_task {
    /** @var int Re-check interval while the meeting is still live (seconds). */
    const RECHECK_SECONDS = 300;

    /**
     * Queue (or re-queue) the task for a zoom activity.
     *
     * @param \stdClass $zoom The zoom activity record.
     * @param ?int $nextruntime Explicit next run time; defaults to scheduled end + slotbuffer.
     * @param bool $checkforexisting Dedupe against an already-queued task. MUST be false when
     *                               re-queuing from inside execute(): the running task's own row
     *                               still exists at that point and would swallow the re-queue.
     * @return void
     */
    public static function queue_for($zoom, $nextruntime = null, $checkforexisting = true) {
        $bufferseconds = ((int) get_config('zoom', 'slotbuffer') ?: 15) * MINSECS;
        if ($nextruntime === null) {
            // Earliest plausible end of THIS start: for on-time starts that is
            // the scheduled end; for early starts (incl. testing a recurring
            // series ahead of its first occurrence) it is now + duration —
            // never wait for a scheduled end that is days away; for recurring
            // occurrences past the stored series start, the scheduled end lies
            // in the past and now + duration carries the estimate; without a
            // duration (recurring no fixed time) fall back to polling.
            $candidates = [];
            $scheduledend = (int) $zoom->start_time + (int) ($zoom->duration ?? 0);
            if ($scheduledend > time()) {
                $candidates[] = $scheduledend;
            }

            if (!empty($zoom->duration)) {
                $candidates[] = time() + (int) $zoom->duration;
            }

            $end = empty($candidates) ? time() : min($candidates);
            $nextruntime = max(time() + MINSECS, $end + $bufferseconds);
        }

        $task = new self();
        $task->set_custom_data(['zoomid' => $zoom->id]);
        $task->set_next_run_time($nextruntime);
        // Checkforexisting (start clicks): a teacher re-clicking Start must not stack tasks.
        \core\task\manager::queue_adhoc_task($task, $checkforexisting);
    }

    /**
     * Run the end-of-session check.
     */
    public function execute() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/zoom/locallib.php');

        $data = $this->get_custom_data();
        $zoom = $DB->get_record('zoom', ['id' => $data->zoomid]);
        if (!$zoom) {
            return;
        }

        $service = zoom_webservice();

        // Still live? Re-queue, and warn when the host has an upcoming booking.
        try {
            foreach ($service->get_user_live_meetings($zoom->host_id) as $livemeeting) {
                if ((string) $livemeeting->id === (string) $zoom->meeting_id) {
                    foreach ($service->get_user_upcoming_meetings($zoom->host_id) as $upcoming) {
                        if ((string) $upcoming->id === (string) $zoom->meeting_id || empty($upcoming->start_time)) {
                            continue;
                        }

                        $bufferseconds = ((int) get_config('zoom', 'slotbuffer') ?: 15) * MINSECS;
                        if (strtotime($upcoming->start_time) <= time() + $bufferseconds) {
                            $cm = get_coursemodule_from_instance('zoom', $zoom->id, $zoom->course);
                            \mod_zoom\event\overrun_detected::create([
                                'context' => $cm ? \context_module::instance($cm->id) : \context_system::instance(),
                                'objectid' => $zoom->id,
                                'other' => [
                                    'meetingid' => (int) $zoom->meeting_id,
                                    'hostid' => $zoom->host_id,
                                    'cmid' => $cm ? (int) $cm->id : 0,
                                    'courseid' => (int) $zoom->course,
                                ],
                            ])->trigger();
                            break;
                        }
                    }

                    self::queue_for($zoom, time() + self::RECHECK_SECONDS, false);
                    return;
                }
            }
        } catch (\moodle_exception $error) {
            // API hiccup: try again later rather than losing the restore.
            self::queue_for($zoom, time() + self::RECHECK_SECONDS, false);
            return;
        }

        // Meeting over: compare-and-swap restore of the host's original name.
        if (empty($zoom->poolrename)) {
            return;
        }

        $stash = json_decode($zoom->poolrename);
        if ($stash) {
            try {
                $hostuser = zoom_get_user($zoom->host_id);
                if ($hostuser && isset($stash->setdisplay)) {
                    if (($hostuser->display_name ?? '') === $stash->setdisplay) {
                        $service->update_user_display_name($zoom->host_id, $stash->prevdisplay);
                    }
                } else if (
                    // Legacy (pre-pooled.6) stash: first/last were patched.
                    $hostuser
                    && isset($stash->setfirst)
                    && ($hostuser->first_name ?? '') === $stash->setfirst
                    && ($hostuser->last_name ?? '') === $stash->setlast
                ) {
                    $service->update_user_name($zoom->host_id, $stash->prevfirst, $stash->prevlast);
                }
            } catch (\moodle_exception $error) {
                debugging('mod_zoom pooled name restore failed: ' . $error->getMessage(), DEBUG_DEVELOPER);
            }
        }

        $DB->set_field('zoom', 'poolrename', null, ['id' => $zoom->id]);
    }
}
