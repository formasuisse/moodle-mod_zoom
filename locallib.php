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
 * Internal library of functions for module zoom
 *
 * All the zoom specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package    mod_zoom
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/zoom/lib.php');
require_once($CFG->dirroot . '/mod/zoom/classes/webservice_exception.php');
require_once($CFG->dirroot . '/mod/zoom/classes/api_limit_exception.php');
require_once($CFG->dirroot . '/mod/zoom/classes/bad_request_exception.php');
require_once($CFG->dirroot . '/mod/zoom/classes/not_found_exception.php');
require_once($CFG->dirroot . '/mod/zoom/classes/retry_failed_exception.php');
require_once($CFG->dirroot . '/mod/zoom/classes/webservice.php');

// Constants.
// Audio options.
define('ZOOM_AUDIO_TELEPHONY', 'telephony');
define('ZOOM_AUDIO_VOIP', 'voip');
define('ZOOM_AUDIO_BOTH', 'both');
// Meeting types.
define('ZOOM_INSTANT_MEETING', 1);
define('ZOOM_SCHEDULED_MEETING', 2);
define('ZOOM_RECURRING_MEETING', 3);
define('ZOOM_SCHEDULED_WEBINAR', 5);
define('ZOOM_RECURRING_WEBINAR', 6);
define('ZOOM_RECURRING_FIXED_MEETING', 8);
define('ZOOM_RECURRING_FIXED_WEBINAR', 9);
// Meeting status.
define('ZOOM_MEETING_EXPIRED', 0);
define('ZOOM_MEETING_EXISTS', 1);

// Number of meetings per page from zoom's get user report.
define('ZOOM_DEFAULT_RECORDS_PER_CALL', 30);
define('ZOOM_MAX_RECORDS_PER_CALL', 300);
// User types. Numerical values from Zoom API.
define('ZOOM_USER_TYPE_BASIC', 1);
define('ZOOM_USER_TYPE_PRO', 2);
define('ZOOM_USER_TYPE_CORP', 3);
define('ZOOM_MEETING_NOT_FOUND_ERROR_CODE', 3001);
define('ZOOM_USER_NOT_FOUND_ERROR_CODE', 1001);
define('ZOOM_INVALID_USER_ERROR_CODE', 1120);
// Webinar options.
define('ZOOM_WEBINAR_DISABLE', 0);
define('ZOOM_WEBINAR_SHOWONLYIFLICENSE', 1);
define('ZOOM_WEBINAR_ALWAYSSHOW', 2);
// Encryption type options.
define('ZOOM_ENCRYPTION_DISABLE', 0);
define('ZOOM_ENCRYPTION_SHOWONLYIFPOSSIBLE', 1);
define('ZOOM_ENCRYPTION_ALWAYSSHOW', 2);
// Encryption types. String values for Zoom API.
define('ZOOM_ENCRYPTION_TYPE_ENHANCED', 'enhanced_encryption');
define('ZOOM_ENCRYPTION_TYPE_E2EE', 'e2ee');
// Alternative hosts options.
define('ZOOM_ALTERNATIVEHOSTS_DISABLE', 0);
define('ZOOM_ALTERNATIVEHOSTS_INPUTFIELD', 1);
define('ZOOM_ALTERNATIVEHOSTS_PICKER', 2);
// Scheduling privilege options.
define('ZOOM_SCHEDULINGPRIVILEGE_DISABLE', 0);
define('ZOOM_SCHEDULINGPRIVILEGE_ENABLE', 1);
// All meetings options.
define('ZOOM_ALLMEETINGS_DISABLE', 0);
define('ZOOM_ALLMEETINGS_ENABLE', 1);
// Download iCal options.
define('ZOOM_DOWNLOADICAL_DISABLE', 0);
define('ZOOM_DOWNLOADICAL_ENABLE', 1);
// Capacity warning options.
define('ZOOM_CAPACITYWARNING_DISABLE', 0);
define('ZOOM_CAPACITYWARNING_ENABLE', 1);
// Recurrence type options.
define('ZOOM_RECURRINGTYPE_NOTIME', 0);
define('ZOOM_RECURRINGTYPE_DAILY', 1);
define('ZOOM_RECURRINGTYPE_WEEKLY', 2);
define('ZOOM_RECURRINGTYPE_MONTHLY', 3);
// Recurring monthly repeat options.
define('ZOOM_MONTHLY_REPEAT_OPTION_DAY', 1);
define('ZOOM_MONTHLY_REPEAT_OPTION_WEEK', 2);
// Recurring end date options.
define('ZOOM_END_DATE_OPTION_BY', 1);
define('ZOOM_END_DATE_OPTION_AFTER', 2);
// API endpoint options.
define('ZOOM_API_ENDPOINT_EU', 'eu');
define('ZOOM_API_ENDPOINT_GLOBAL', 'global');
define('ZOOM_API_URL_EU', 'https://eu01api-www4local.zoom.us/v2/');
define('ZOOM_API_URL_GLOBAL', 'https://api.zoom.us/v2/');
// Auto-recording options.
define('ZOOM_AUTORECORDING_NONE', 'none');
define('ZOOM_AUTORECORDING_USERDEFAULT', 'userdefault');
define('ZOOM_AUTORECORDING_LOCAL', 'local');
define('ZOOM_AUTORECORDING_CLOUD', 'cloud');
// Registration options (Moodle-level semantics; on the Zoom side both
// non-OFF modes create auto-approved registration meetings — the difference
// is WHO creates the registrant):
// AUTOMATIC: Moodle registers the participant server-side with their Moodle
//            identity (pre-named, no form).
// MANUAL:    participants register themselves on Zoom's form — an explicit
//            "I will attend" (RSVP); the typed name is not enforced by Moodle.
define('ZOOM_REGISTRATION_AUTOMATIC', 0);
define('ZOOM_REGISTRATION_MANUAL', 1);
define('ZOOM_REGISTRATION_OFF', 2);

/**
 * Terminate the current script with a fatal error.
 *
 * Adapted from core_renderer's fatal_error() method. Needed because throwing errors with HTML links in them will convert links
 * to text using htmlentities. See MDL-66161 - Reflected XSS possible from some fatal error messages.
 *
 * So need custom error handler for fatal Zoom errors that have links to help people.
 *
 * @param string $errorcode The name of the string from error.php to print
 * @param string $module name of module
 * @param string $continuelink The url where the user will be prompted to continue.
 *                             If no url is provided the user will be directed to
 *                             the site index page.
 * @param mixed $a Extra words and phrases that might be required in the error string
 */
function zoom_fatal_error($errorcode, $module = '', $continuelink = '', $a = null) {
    global $CFG, $COURSE, $OUTPUT, $PAGE;

    $output = '';
    $obbuffer = '';

    // Assumes that function is run before output is generated.
    if ($OUTPUT->has_started()) {
        // If not then have to default to standard error.
        throw new moodle_exception($errorcode, $module, $continuelink, $a);
    }

    $PAGE->set_heading(format_string($COURSE->fullname));
    $output .= $OUTPUT->header();

    // Output message without messing with HTML content of error.
    $message = '<p class="errormessage">' . get_string($errorcode, $module, $a) . '</p>';

    $output .= $OUTPUT->box($message, 'errorbox alert alert-danger', null, ['data-rel' => 'fatalerror']);

    if ($CFG->debugdeveloper) {
        if (!empty($debuginfo)) {
            $debuginfo = s($debuginfo); // Removes all nasty JS.
            $debuginfo = str_replace("\n", '<br />', $debuginfo); // Keep newlines.
            $output .= $OUTPUT->notification('<strong>Debug info:</strong> ' . $debuginfo, 'notifytiny');
        }

        if (!empty($backtrace)) {
            $output .= $OUTPUT->notification('<strong>Stack trace:</strong> ' . format_backtrace($backtrace), 'notifytiny');
        }

        if ($obbuffer !== '') {
            $output .= $OUTPUT->notification('<strong>Output buffer:</strong> ' . s($obbuffer), 'notifytiny');
        }
    }

    if (!empty($continuelink)) {
        $output .= $OUTPUT->continue_button($continuelink);
    }

    $output .= $OUTPUT->footer();

    // Padding to encourage IE to display our error page, rather than its own.
    $output .= str_repeat(' ', 512);

    echo $output;

    exit(1); // General error code.
}

/**
 * Get course/cm/zoom objects from url parameters, and check for login/permissions.
 *
 * @return array Array of ($course, $cm, $zoom)
 */
function zoom_get_instance_setup() {
    global $DB;

    $id = optional_param('id', 0, PARAM_INT); // Course_module ID.
    $n = optional_param('n', 0, PARAM_INT);  // Zoom instance ID.

    if ($id) {
        $cm = get_coursemodule_from_id('zoom', $id, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $zoom = $DB->get_record('zoom', ['id' => $cm->instance], '*', MUST_EXIST);
    } else if ($n) {
        $zoom = $DB->get_record('zoom', ['id' => $n], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $zoom->course], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('zoom', $zoom->id, $course->id, false, MUST_EXIST);
    } else {
        throw new moodle_exception('zoomerr_id_missing', 'mod_zoom');
    }

    require_login($course, true, $cm);

    $context = context_module::instance($cm->id);
    require_capability('mod/zoom:view', $context);

    return [$course, $cm, $zoom];
}

/**
 * Retrieves information for a meeting.
 *
 * @param int $zoomid
 * @return array information about the meeting
 */
function zoom_get_sessions_for_display($zoomid) {
    global $DB, $CFG;

    require_once($CFG->libdir . '/moodlelib.php');

    $sessions = [];
    $format = get_string('strftimedatetimeshort', 'langconfig');

    // Sort sessions in start_time ascending order.
    $instances = $DB->get_records('zoom_meeting_details', ['zoomid' => $zoomid], 'start_time');

    foreach ($instances as $instance) {
        // The meeting uuid, not the participant's uuid.
        $uuid = $instance->uuid;
        $participantlist = zoom_get_participants_report($instance->id);
        $sessions[$uuid]['participants'] = $participantlist;

        $uniquevalues = [];
        $uniqueparticipantcount = 0;
        foreach ($participantlist as $participant) {
            $unique = true;
            if ($participant->uuid != null) {
                if (array_key_exists($participant->uuid, $uniquevalues)) {
                    $unique = false;
                } else {
                    $uniquevalues[$participant->uuid] = true;
                }
            }

            if ($participant->userid != null) {
                if (!$unique || !array_key_exists($participant->userid, $uniquevalues)) {
                    $uniquevalues[$participant->userid] = true;
                } else {
                    $unique = false;
                }
            }

            if ($participant->user_email != null) {
                if (!$unique || !array_key_exists($participant->user_email, $uniquevalues)) {
                    $uniquevalues[$participant->user_email] = true;
                } else {
                    $unique = false;
                }
            }

            $uniqueparticipantcount += $unique ? 1 : 0;
        }

        $sessions[$uuid]['count'] = $uniqueparticipantcount;
        $sessions[$uuid]['topic'] = $instance->topic;
        $sessions[$uuid]['duration'] = $instance->duration;
        $sessions[$uuid]['starttime'] = userdate($instance->start_time, $format);
        $sessions[$uuid]['endtime'] = userdate($instance->start_time + $instance->duration, $format);
    }

    return $sessions;
}

/**
 * Get the next occurrence of a meeting.
 *
 * @param stdClass $zoom
 * @return int The timestamp of the next occurrence of a recurring meeting or
 *             0 if this is a recurring meeting without fixed time or
 *             the timestamp of the meeting start date if this isn't a recurring meeting.
 */
function zoom_get_next_occurrence($zoom) {
    global $DB;

    // Prepare an ad-hoc request cache as this function could be called multiple times throughout a request
    // and we want to avoid to make duplicate DB calls.
    $cacheoptions = [
        'simplekeys' => true,
        'simpledata' => true,
    ];
    $cache = cache::make_from_params(cache_store::MODE_REQUEST, 'zoom', 'nextoccurrence', [], $cacheoptions);

    // If the next occurrence wasn't already cached, fill the cache.
    $cachednextoccurrence = $cache->get($zoom->id);
    if ($cachednextoccurrence === false) {
        // If this isn't a recurring meeting.
        if (!$zoom->recurring) {
            // Use the meeting start time.
            $cachednextoccurrence = $zoom->start_time;

            // Or if this is a recurring meeting without fixed time.
        } else if ($zoom->recurrence_type == ZOOM_RECURRINGTYPE_NOTIME) {
            // Use 0 as there isn't anything better to return.
            $cachednextoccurrence = 0;

            // Otherwise we have a recurring meeting with a recurrence schedule.
        } else {
            // Get the calendar event of the next occurrence.
            $selectclause = "modulename = :modulename AND instance = :instance AND (timestart + timeduration) >= :now";
            $selectparams = ['modulename' => 'zoom', 'instance' => $zoom->id, 'now' => time()];
            $nextoccurrence = $DB->get_records_select('event', $selectclause, $selectparams, 'timestart ASC', 'timestart', 0, 1);

            // If we haven't got a single event.
            if (empty($nextoccurrence)) {
                // Use 0 as there isn't anything better to return.
                $cachednextoccurrence = 0;
            } else {
                // Use the timestamp of the event.
                $nextoccurenceobject = reset($nextoccurrence);
                $cachednextoccurrence = $nextoccurenceobject->timestart;
            }
        }

        // Store the next occurrence into the cache.
        $cache->set($zoom->id, $cachednextoccurrence);
    }

    // Return the next occurrence.
    return $cachednextoccurrence;
}

/**
 * Determine if a zoom meeting is in progress, is available, and/or is finished.
 *
 * @param stdClass $zoom
 * @return array Array of booleans: [in progress, available, finished].
 */
function zoom_get_state($zoom) {
    // Get plugin config.
    $config = get_config('zoom');

    // Get the current time as calculation basis.
    $now = time();

    // If this is a recurring meeting with a recurrence schedule.
    if ($zoom->recurring && $zoom->recurrence_type != ZOOM_RECURRINGTYPE_NOTIME) {
        // Get the next occurrence start time.
        $starttime = zoom_get_next_occurrence($zoom);
    } else {
        // Get the meeting start time.
        $starttime = $zoom->start_time;
    }

    // Calculate the time when the recurring meeting becomes available next,
    // based on the next occurrence start time and the general meeting lead time.
    $firstavailable = $starttime - ($config->firstabletojoin * 60);

    // Calculate the time when the meeting ends to be available,
    // based on the next occurrence start time and the meeting duration.
    $lastavailable = $starttime + $zoom->duration;

    // Determine if the meeting is in progress.
    $inprogress = ($firstavailable <= $now && $now <= $lastavailable);

    // Determine if its a recurring meeting with no fixed time.
    $isrecurringnotime = $zoom->recurring && $zoom->recurrence_type == ZOOM_RECURRINGTYPE_NOTIME;

    // Determine if the meeting is available,
    // based on the fact if it is recurring or in progress.
    $available = $isrecurringnotime || $inprogress;

    // Determine if the meeting is finished,
    // based on the fact if it is recurring or the meeting end time is still in the future.
    $finished = !$isrecurringnotime && $now > $lastavailable;

    // Return the requested information.
    return [$inprogress, $available, $finished];
}

/**
 * Get the Zoom id of the currently logged-in user.
 *
 * @param bool $required If true, will error if the user doesn't have a Zoom account.
 * @return string
 */
function zoom_get_user_id($required = true) {
    global $USER;

    $cache = cache::make('mod_zoom', 'zoomid');
    if (!($zoomuserid = $cache->get($USER->id))) {
        $zoomuserid = false;
        try {
            $zoomuser = zoom_get_user(zoom_get_api_identifier($USER));
            if ($zoomuser !== false && isset($zoomuser->id) && ($zoomuser->id !== false)) {
                $zoomuserid = $zoomuser->id;
                $cache->set($USER->id, $zoomuserid);
            }
            // If user does not have a Zoom account, throw an error.
            if ($required && $zoomuser === false) {
                throw new moodle_exception('zoomerr_usernotfound', 'mod_zoom', '', get_config('zoom', 'zoomurl'));
            }
        } catch (moodle_exception $error) {
            if ($required) {
                throw $error;
            }
        }
    }

    return $zoomuserid;
}

/**
 * Get the Zoom meeting security settings, including meeting password requirements of the user's master account.
 *
 * @param string|int $identifier The user's email or the user's ID per Zoom API.
 * @return stdClass
 */
function zoom_get_meeting_security_settings($identifier) {
    $cache = cache::make('mod_zoom', 'zoommeetingsecurity');
    $zoommeetingsecurity = $cache->get($identifier);
    if (empty($zoommeetingsecurity)) {
        $zoommeetingsecurity = zoom_webservice()->get_account_meeting_security_settings($identifier);
        $cache->set($identifier, $zoommeetingsecurity);
    }

    return $zoommeetingsecurity;
}

/**
 * Check if the error indicates that a meeting is gone.
 *
 * @param moodle_exception $error
 * @return bool
 */
function zoom_is_meeting_gone_error($error) {
    // If the meeting's owner/user cannot be found, we consider the meeting to be gone.
    return ($error->zoomerrorcode === ZOOM_MEETING_NOT_FOUND_ERROR_CODE) || zoom_is_user_not_found_error($error);
}

/**
 * Check if the error indicates that a user is not found or does not belong to the current account.
 *
 * @param moodle_exception $error
 * @return bool
 */
function zoom_is_user_not_found_error($error) {
    return ($error->zoomerrorcode === ZOOM_USER_NOT_FOUND_ERROR_CODE) || ($error->zoomerrorcode === ZOOM_INVALID_USER_ERROR_CODE);
}

/**
 * Return the string parameter for zoomerr_meetingnotfound.
 *
 * @param string $cmid
 * @return stdClass
 */
function zoom_meetingnotfound_param($cmid) {
    // Provide links to recreate and delete.
    $recreate = new moodle_url('/mod/zoom/recreate.php', ['id' => $cmid, 'sesskey' => sesskey()]);
    $delete = new moodle_url('/course/mod.php', ['delete' => $cmid, 'sesskey' => sesskey()]);

    // Convert links to strings and pass as error parameter.
    $param = new stdClass();
    $param->recreate = $recreate->out();
    $param->delete = $delete->out();

    return $param;
}

/**
 * Get the data of each user for the participants report.
 * @param string $detailsid The meeting ID that you want to get the participants report for.
 * @return array The user data as an array of records (array of arrays).
 */
function zoom_get_participants_report($detailsid) {
    global $DB;
    $sql = 'SELECT zmp.id,
                   zmp.name,
                   zmp.userid,
                   zmp.user_email,
                   zmp.join_time,
                   zmp.leave_time,
                   zmp.duration,
                   zmp.uuid
              FROM {zoom_meeting_participants} zmp
             WHERE zmp.detailsid = :detailsid
    ';
    $params = [
        'detailsid' => $detailsid,
    ];
    $participants = $DB->get_records_sql($sql, $params);
    return $participants;
}

/**
 * Creates a default passcode from the user's Zoom meeting security settings.
 *
 * @param stdClass $meetingpasswordrequirement
 * @return string passcode
 */
function zoom_create_default_passcode($meetingpasswordrequirement) {
    $length = max($meetingpasswordrequirement->length, 6);
    $random = random_int(0, (int) pow(10, $length) - 1);
    $passcode = str_pad(strval($random), $length, '0', STR_PAD_LEFT);

    // Get a random set of indexes to replace with non-numberic values.
    $indexes = range(0, $length - 1);
    shuffle($indexes);

    if ($meetingpasswordrequirement->have_letter || $meetingpasswordrequirement->have_upper_and_lower_characters) {
        // Random letter from A-Z.
        $passcode[$indexes[0]] = chr(random_int(65, 90));
        // Random letter from a-z.
        $passcode[$indexes[1]] = chr(random_int(97, 122));
    }

    if ($meetingpasswordrequirement->have_special_character) {
        $specialchar = '@_*-';
        $passcode[$indexes[2]] = $specialchar[random_int(0, strlen($specialchar) - 1)];
    }

    return $passcode;
}

/**
 * Creates a description string from the user's Zoom meeting security settings.
 *
 * @param stdClass $meetingpasswordrequirement
 * @return string description of password requirements
 */
function zoom_create_passcode_description($meetingpasswordrequirement) {
    $description = '';
    if ($meetingpasswordrequirement->only_allow_numeric) {
        $description .= get_string('password_only_numeric', 'mod_zoom') . ' ';
    } else {
        if ($meetingpasswordrequirement->have_letter && !$meetingpasswordrequirement->have_upper_and_lower_characters) {
            $description .= get_string('password_letter', 'mod_zoom') . ' ';
        } else if ($meetingpasswordrequirement->have_upper_and_lower_characters) {
            $description .= get_string('password_lower_upper', 'mod_zoom') . ' ';
        }

        if ($meetingpasswordrequirement->have_number) {
            $description .= get_string('password_number', 'mod_zoom') . ' ';
        }

        if ($meetingpasswordrequirement->have_special_character) {
            $description .= get_string('password_special', 'mod_zoom') . ' ';
        } else {
            $description .= get_string('password_allowed_char', 'mod_zoom') . ' ';
        }
    }

    if ($meetingpasswordrequirement->length) {
        $description .= get_string('password_length', 'mod_zoom', $meetingpasswordrequirement->length) . ' ';
    }

    if ($meetingpasswordrequirement->consecutive_characters_length > 0) {
        $description .= get_string(
            'password_consecutive',
            'mod_zoom',
            $meetingpasswordrequirement->consecutive_characters_length - 1
        ) . ' ';
    }

    $description .= get_string('password_max_length', 'mod_zoom');
    return $description;
}

/**
 * Creates an array of users who can be selected as alternative host in a given context.
 *
 * @param context $context The context to be used.
 *
 * @return array Array of users (mail => fullname).
 */
function zoom_get_selectable_alternative_hosts_list(context $context) {
    // Get selectable alternative host users based on the capability.
    $users = get_enrolled_users($context, 'mod/zoom:eligiblealternativehost', 0, 'u.*', 'lastname');

    // Create array of users.
    $selectablealternativehosts = [];

    // Iterate over selectable alternative host users.
    foreach ($users as $u) {
        // Note: Basically, if this is the user's own data row, the data row should be skipped.
        // But this would then not cover the case when a user is scheduling the meeting _for_ another user
        // and wants to be an alternative host himself.
        // As this would have to be handled at runtime in the browser, we just offer all users with the
        // capability as selectable and leave this aspect as possible improvement for the future.
        // At least, Zoom does not care if the user who is the host adds himself as alternative host as well.

        // Verify that the user really has a Zoom account.
        // Furthermore, verify that the user's status is active. Adding a pending or inactive user as alternative host will result
        // in a Zoom API error otherwise.
        $zoomuser = zoom_get_user($u->email);
        if ($zoomuser !== false && $zoomuser->status === 'active') {
            // Add user to array of users.
            $selectablealternativehosts[strtolower($u->email)] = fullname($u);
        }
    }

    return $selectablealternativehosts;
}

/**
 * Creates a string of roles who can be selected as alternative host in a given context.
 *
 * @param context $context The context to be used.
 *
 * @return string The string of roles.
 */
function zoom_get_selectable_alternative_hosts_rolestring(context $context) {
    // Get selectable alternative host users based on the capability.
    $roles = get_role_names_with_caps_in_context($context, ['mod/zoom:eligiblealternativehost']);

    // Compose string.
    $rolestring = implode(', ', $roles);

    return $rolestring;
}

/**
 * Get existing Moodle users from a given set of alternative hosts.
 *
 * @param array $alternativehosts The array of alternative hosts email addresses.
 *
 * @return array The array of existing Moodle user objects.
 */
function zoom_get_users_from_alternativehosts(array $alternativehosts) {
    global $DB;

    // Get the existing Moodle user objects from the DB.
    [$insql, $inparams] = $DB->get_in_or_equal($alternativehosts);
    $sql = 'SELECT *
            FROM {user}
            WHERE email ' . $insql . '
            ORDER BY lastname ASC';
    $alternativehostusers = $DB->get_records_sql($sql, $inparams);

    return $alternativehostusers;
}

/**
 * Get non-Moodle users from a given set of alternative hosts.
 *
 * @param array $alternativehosts The array of alternative hosts email addresses.
 *
 * @return array The array of non-Moodle user mail addresses.
 */
function zoom_get_nonusers_from_alternativehosts(array $alternativehosts) {
    global $DB;

    // Get the non-Moodle user mail addresses by checking which one does not exist in the DB.
    $alternativehostnonusers = [];
    [$insql, $inparams] = $DB->get_in_or_equal($alternativehosts);
    $sql = 'SELECT email
            FROM {user}
            WHERE email ' . $insql . '
            ORDER BY email ASC';
    $alternativehostusersmails = $DB->get_records_sql($sql, $inparams);
    foreach ($alternativehosts as $ah) {
        if (!array_key_exists($ah, $alternativehostusersmails)) {
            $alternativehostnonusers[] = $ah;
        }
    }

    return $alternativehostnonusers;
}

/**
 * Get the unavailability note based on the Zoom plugin configuration.
 *
 * @param object $zoom The Zoom meeting object.
 * @param bool|null $finished The function needs to know if the meeting is already finished.
 *                       You can provide this information, if already available, to the function.
 *                       Otherwise it will determine it with a small overhead.
 *
 * @return string The unavailability note.
 */
function zoom_get_unavailability_note($zoom, $finished = null) {
    // Get config.
    $config = get_config('zoom');

    // Get the plain unavailable string.
    $strunavailable = get_string('unavailable', 'mod_zoom');

    // If this is a recurring meeting without fixed time, just use the plain unavailable string.
    if ($zoom->recurring && $zoom->recurrence_type == ZOOM_RECURRINGTYPE_NOTIME) {
        $unavailabilitynote = $strunavailable;

        // Otherwise we add some more information to the unavailable string.
    } else {
        // If we don't have the finished information yet, get it with a small overhead.
        if ($finished === null) {
            [$inprogress, $available, $finished] = zoom_get_state($zoom);
        }

        // If this meeting is still pending.
        if ($finished !== true) {
            // If the admin wants to show the leadtime.
            if (!empty($config->displayleadtime) && $config->firstabletojoin > 0) {
                $unavailabilitynote = $strunavailable . '<br />' .
                        get_string('unavailablefirstjoin', 'mod_zoom', ['mins' => ($config->firstabletojoin)]);

                // Otherwise.
            } else {
                $unavailabilitynote = $strunavailable . '<br />' . get_string('unavailablenotstartedyet', 'mod_zoom');
            }

            // Otherwise, the meeting has finished.
        } else {
            $unavailabilitynote = $strunavailable . '<br />' . get_string('unavailablefinished', 'mod_zoom');
        }
    }

    return $unavailabilitynote;
}

/**
 * Gets the meeting capacity of a given Zoom user.
 * Please note: This function does not check if the Zoom user really exists, this has to be checked before calling this function.
 *
 * @param string $zoomhostid The Zoom ID of the host.
 * @param bool $iswebinar The meeting is a webinar.
 *
 * @return int|bool The meeting capacity of the Zoom user or false if the user does not have any meeting capacity at all.
 */
function zoom_get_meeting_capacity(string $zoomhostid, bool $iswebinar = false) {
    // Get the 'feature' section of the user's Zoom settings.
    $userfeatures = zoom_get_user_settings($zoomhostid)->feature;

    $meetingcapacity = false;

    // If this is a webinar.
    if ($iswebinar === true) {
        // Get the appropriate capacity value.
        if (!empty($userfeatures->webinar_capacity)) {
            $meetingcapacity = $userfeatures->webinar_capacity;
        } else if (!empty($userfeatures->zoom_events_capacity)) {
            $meetingcapacity = $userfeatures->zoom_events_capacity;
        }
    } else {
        // If this is a meeting, get the 'meeting_capacity' value.
        if (!empty($userfeatures->meeting_capacity)) {
            $meetingcapacity = $userfeatures->meeting_capacity;

            // Check if the user has a 'large_meeting' license that has a higher capacity value.
            if (!empty($userfeatures->large_meeting_capacity) && $userfeatures->large_meeting_capacity > $meetingcapacity) {
                $meetingcapacity = $userfeatures->large_meeting_capacity;
            }
        }
    }

    return $meetingcapacity;
}

/**
 * Gets the number of eligible meeting participants in a given context.
 * Please note: This function only covers users who are enrolled into the given context.
 * It does _not_ include users who have the necessary capability on a higher context without being enrolled.
 *
 * @param context $context The context which we want to check.
 *
 * @return int The number of eligible meeting participants.
 */
function zoom_get_eligible_meeting_participants(context $context) {
    global $DB;

    // Compose SQL query.
    $sqlsnippets = get_enrolled_with_capabilities_join($context, '', 'mod/zoom:view', 0, true);
    $sql = 'SELECT count(DISTINCT u.id)
            FROM {user} u ' . $sqlsnippets->joins . ' WHERE ' . $sqlsnippets->wheres;

    // Run query and count records.
    $eligibleparticipantcount = $DB->count_records_sql($sql, $sqlsnippets->params);

    return $eligibleparticipantcount;
}

/**
 * Get array of alternative hosts from a string.
 *
 * @param string $alternativehoststring Comma (or semicolon) separated list of alternative hosts.
 * @return string[] $alternativehostarray Array of alternative hosts.
 */
function zoom_get_alternative_host_array_from_string($alternativehoststring) {
    if (empty($alternativehoststring)) {
        return [];
    }

    // The Zoom API has historically returned either semicolons or commas, so we need to support both.
    $alternativehoststring = str_replace(';', ',', $alternativehoststring);
    $alternativehostarray = array_filter(explode(',', $alternativehoststring));

    // Lowercase email addresses so that we can do case-insensitive comparisons.
    foreach ($alternativehostarray as $key => $value) {
        if (filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
            $alternativehostarray[$key] = strtolower($value);
        }
    }
    return $alternativehostarray;
}

/**
 * Get all custom user profile fields of type text
 *
 * @return array list of user profile fields
 */
function zoom_get_user_profile_fields() {
    global $DB;

    $userfields = [];
    $records = $DB->get_records('user_info_field', ['datatype' => 'text']);
    foreach ($records as $record) {
        $userfields[$record->shortname] = $record->name;
    }

    return $userfields;
}

/**
 * Get all valid options for API Identifier field
 *
 * @return array list of all valid options
 */
function zoom_get_api_identifier_fields() {
    $options = [
        'email' => get_string('email'),
        'username' => get_string('username'),
        'idnumber' => get_string('idnumber'),
    ];

    $userfields = zoom_get_user_profile_fields();
    if (!empty($userfields)) {
        $options += $userfields;
    }

    return $options;
}

/**
 * Get the zoom api identifier
 *
 * @param object $user The user object
 *
 * @return string the value of the identifier
 */
function zoom_get_api_identifier($user) {
    // Get the value from the config first.
    $field = get_config('zoom', 'apiidentifier');

    $identifier = '';
    if (isset($user->$field)) {
        // If one of the standard user fields.
        $identifier = $user->$field;
    } else if (isset($user->profile[$field])) {
        // If one of the custom user fields.
        $identifier = $user->profile[$field];
    }

    if (empty($identifier)) {
        // Fallback to email if the field is not set.
        $identifier = $user->email;
    }

    return $identifier;
}

/**
 * Creates an iCalendar_event for a Zoom meeting.
 *
 * @param stdClass $event The meeting object.
 * @param string $description The event description.
 *
 * @return iCalendar_event
 */
function zoom_helper_icalendar_event($event, $description) {
    global $CFG;

    // Match Moodle's uid format for iCal events.
    $hostaddress = str_replace('http://', '', $CFG->wwwroot);
    $hostaddress = str_replace('https://', '', $hostaddress);
    $uid = $event->id . '@' . $hostaddress;

    $icalevent = new iCalendar_event();
    $icalevent->add_property('uid', $uid); // A unique identifier.
    $icalevent->add_property('summary', $event->name); // Title.
    $icalevent->add_property('dtstamp', Bennu::timestamp_to_datetime()); // Time of creation.
    $icalevent->add_property('last-modified', Bennu::timestamp_to_datetime($event->timemodified));
    $icalevent->add_property('dtstart', Bennu::timestamp_to_datetime($event->timestart)); // Start time.
    $icalevent->add_property('dtend', Bennu::timestamp_to_datetime($event->timestart + $event->timeduration)); // End time.
    $icalevent->add_property('description', $description);
    return $icalevent;
}

/**
 * Get the configured Zoom API URL.
 *
 * @return string The API URL.
 */
function zoom_get_api_url() {
    // Get the API endpoint setting.
    $apiendpoint = get_config('zoom', 'apiendpoint');

    // Pick the corresponding API URL.
    switch ($apiendpoint) {
        case ZOOM_API_ENDPOINT_EU:
            $apiurl = ZOOM_API_URL_EU;
            break;

        case ZOOM_API_ENDPOINT_GLOBAL:
        default:
            $apiurl = ZOOM_API_URL_GLOBAL;
            break;
    }

    // Return API URL.
    return $apiurl;
}

/**
 * Loads the zoom meeting and passes back a meeting URL
 * after processing events, view completion, grades, and license updates.
 *
 * @param int $id course module id
 * @param object $context moodle context object
 * @param bool $usestarturl
 * @return array $returns contains url object 'nexturl' or string 'error'
 */
function zoom_load_meeting($id, $context, $usestarturl = true) {
    global $CFG, $DB, $USER;
    require_once($CFG->libdir . '/gradelib.php');

    $cm = get_coursemodule_from_id('zoom', $id, 0, false, MUST_EXIST);
    $course = get_course($cm->course);
    $zoom = $DB->get_record('zoom', ['id' => $cm->instance], '*', MUST_EXIST);

    require_login($course, true, $cm);

    require_capability('mod/zoom:view', $context);

    $returns = ['nexturl' => null, 'error' => null];

    [$inprogress, $available, $finished] = zoom_get_state($zoom);

    $userisregistered = false;
    $userisregistering = false;
    if ($zoom->registration != ZOOM_REGISTRATION_OFF) {
        // Check if user already registered.
        $registrantjoinurl = zoom_get_registrant_join_url($USER->email, $zoom->meeting_id, $zoom->webinar);
        $userisregistered = !empty($registrantjoinurl);

        if (!$userisregistered) {
            // Server-side registration (see README.md, 'Pooled hosts mode'):
            // the LMS already knows who this is — create the registrant with
            // the Moodle identity and use the returned personal tk link. No
            // Zoom form, nothing to type, and the registered name is the
            // Moodle name by construction. In AUTOMATIC mode this happens
            // invisibly on the first Join click; in MANUAL (RSVP) mode the
            // button says Register and this click IS the explicit RSVP —
            // same mechanics, same enforced identity.
            try {
                $registrant = zoom_webservice()->add_meeting_registrant(
                    $zoom->meeting_id,
                    $zoom->webinar,
                    $USER->email,
                    $USER->firstname,
                    $USER->lastname
                );
                if (!empty($registrant->join_url)) {
                    $registrantjoinurl = $registrant->join_url;
                    $userisregistered = true;
                }
            } catch (moodle_exception $error) {
                debugging('mod_zoom auto-registration failed: ' . $error->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // Registration failed (API hiccup): fall back to Zoom's registration page.
        if (!$userisregistered) {
            $userisregistering = true;
        }

        // Registered ahead of the join window (RSVP click, or an early Join
        // click): confirm instead of erroring — the join button appears once
        // the session window opens.
        [$inprogressnow, $availablenow] = zoom_get_state($zoom);
        if ($userisregistered && !$availablenow) {
            $returns['notification'] = get_string('registration_done', 'mod_zoom');
            return $returns;
        }
    }

    // If the meeting is not yet available, deny access.
    if (!$available && !$userisregistering) {
        // Get unavailability note.
        $returns['error'] = zoom_get_unavailability_note($zoom, $finished);
        return $returns;
    }

    if (zoom_pooled_group() !== null) {
        // Pooled-hosts feature (see README.md, 'Pooled hosts mode'):
        // the activity's teacher field decides hosting — the teacher has no
        // Zoom identity of their own, the pool host is infrastructure.
        $userisrealhost = (!empty($zoom->teacherid) && $zoom->teacherid == $USER->id);
    } else {
        $userisrealhost = (zoom_get_user_id(false) === $zoom->host_id);
    }

    // Alternative hosts keep working in both modes: anyone whose Zoom email is
    // listed on the meeting can still claim host through Zoom's own mechanics.
    $alternativehosts = zoom_get_alternative_host_array_from_string($zoom->alternative_hosts);
    // Lowercase email addresses so that we can do case-insensitive comparisons.
    $userapiidentifier = zoom_get_api_identifier($USER);
    if (filter_var($userapiidentifier, FILTER_VALIDATE_EMAIL) !== false) {
        $userapiidentifier = strtolower($userapiidentifier);
    }

    $userishost = ($userisrealhost || in_array($userapiidentifier, $alternativehosts, true));

    // Check if we should use the start meeting url.
    if ($userisrealhost && $usestarturl) {
        if (zoom_pooled_group() !== null) {
            // Pooled-hosts feature: warn ops when the pool host is
            // still in another live meeting (starting anyway prompts the
            // teacher to end it — T12), rename the host to the teacher's name,
            // and queue the end-of-session task that restores it.
            try {
                foreach (zoom_webservice()->get_user_live_meetings($zoom->host_id) as $livemeeting) {
                    if ((string) $livemeeting->id !== (string) $zoom->meeting_id) {
                        \mod_zoom\event\collision_imminent::create([
                            'context' => $context,
                            'objectid' => $zoom->id,
                            'other' => [
                                'meetingid' => (int) $zoom->meeting_id,
                                'hostid' => $zoom->host_id,
                                'cmid' => $id,
                                'courseid' => (int) $zoom->course,
                            ],
                        ])->trigger();
                        break;
                    }
                }
            } catch (moodle_exception $error) {
                debugging('mod_zoom pooled live-check failed: ' . $error->getMessage(), DEBUG_DEVELOPER);
            }

            zoom_pooled_apply_rename($zoom, $USER);
            \mod_zoom\task\end_of_session::queue_for($zoom);
        }

        // Important: Only the real host can use this URL, because it joins the meeting as the host user.
        $starturl = zoom_get_start_url($zoom->meeting_id, $zoom->webinar, $zoom->join_url);
        $returns['nexturl'] = new moodle_url($starturl);
    } else {
        $url = $zoom->join_url;
        if ($userisregistered) {
            $url = $registrantjoinurl;
        }

        $unamesetting = get_config('zoom', 'unamedisplay');
        switch ($unamesetting) {
            case 'fullname':
            default:
                $unamedisplay = fullname($USER);
                break;

            case 'firstname':
                $unamedisplay = $USER->firstname;
                break;

            case 'idfullname':
                $unamedisplay = '(' . $USER->id . ') ' . fullname($USER);
                break;

            case 'id':
                $unamedisplay = '(' . $USER->id . ')';
                break;
        }

        // Try to send the user email (not guaranteed).
        $returns['nexturl'] = new moodle_url($url, ['uname' => $unamedisplay, 'uemail' => $USER->email]);
    }

    // If the user is pre-registering, skip grading/completion.
    if (!$available && $userisregistering) {
        return $returns;
    }

    // Record user's clicking join.
    \mod_zoom\event\join_meeting_button_clicked::create([
        'context' => $context,
        'objectid' => $zoom->id,
        'other' => [
            'cmid' => $id,
            'meetingid' => (int) $zoom->meeting_id,
            'userishost' => $userishost,
        ],
    ])->trigger();

    // Track completion viewed.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);

    // Check the grading method settings.
    if (!empty($zoom->grading_method)) {
        $gradingmethod = $zoom->grading_method;
    } else if ($defaultgrading = get_config('gradingmethod', 'zoom')) {
        $gradingmethod = $defaultgrading;
    } else {
        $gradingmethod = 'entry';
    }

    if ($gradingmethod === 'entry') {
        // Check whether user has a grade. If not, then assign full credit to them.
        $gradelist = grade_get_grades($course->id, 'mod', 'zoom', $cm->instance, $USER->id);

        // Assign full credits for user who has no grade yet, if this meeting is gradable (i.e. the grade type is not "None").
        if (!empty($gradelist->items) && empty($gradelist->items[0]->grades[$USER->id]->grade)) {
            $grademax = $gradelist->items[0]->grademax;
            $grades = [
                'rawgrade' => $grademax,
                'userid' => $USER->id,
                'usermodified' => $USER->id,
                'dategraded' => '',
                'feedbackformat' => '',
                'feedback' => '',
            ];

            zoom_grade_item_update($zoom, $grades);
        }
    } // Otherwise, the get_meetings_report task calculates the grades according to duration.

    // Upgrade host upon joining meeting, if host is not Licensed.
    if ($userishost) {
        $config = get_config('zoom');
        if (!empty($config->recycleonjoin)) {
            zoom_webservice()->provide_license($zoom->host_id);
        }
    }

    return $returns;
}

/**
 * Fetches a fresh URL that can be used to start the Zoom meeting.
 *
 * @param string $meetingid Zoom meeting ID.
 * @param bool $iswebinar If the session is a webinar.
 * @param string $fallbackurl URL to use if the webservice call fails.
 * @return string Best available URL for starting the meeting.
 */
function zoom_get_start_url($meetingid, $iswebinar, $fallbackurl) {
    try {
        $response = zoom_webservice()->get_meeting_webinar_info($meetingid, $iswebinar);
        return $response->start_url ?? $response->join_url;
    } catch (moodle_exception $e) {
        // If an exception was thrown, gracefully use the fallback URL.
        return $fallbackurl;
    }
}

/**
 * Get the configured Zoom tracking fields.
 *
 * @return array tracking fields, keys as lower case
 */
function zoom_list_tracking_fields() {
    $trackingfields = [];

    // Get the tracking fields configured on the account.
    $response = zoom_webservice()->list_tracking_fields();
    if (isset($response->tracking_fields)) {
        foreach ($response->tracking_fields as $trackingfield) {
            $field = str_replace(' ', '_', strtolower($trackingfield->field));
            $trackingfields[$field] = (array) $trackingfield;
        }
    }

    return $trackingfields;
}

/**
 * Trim and lower case tracking fields.
 *
 * @return array tracking fields trimmed, keys as lower case
 */
function zoom_clean_tracking_fields() {
    $config = get_config('zoom');
    $defaulttrackingfields = explode(',', $config->defaulttrackingfields);
    $trackingfields = [];

    foreach ($defaulttrackingfields as $key => $defaulttrackingfield) {
        $trimmed = trim($defaulttrackingfield);
        if (!empty($trimmed)) {
            $key = str_replace(' ', '_', strtolower($trimmed));
            $trackingfields[$key] = $trimmed;
        }
    }

    return $trackingfields;
}

/**
 * Synchronize tracking field data for a meeting.
 *
 * @param int $zoomid Zoom meeting ID
 * @param array $trackingfields Tracking fields configured in Zoom.
 */
function zoom_sync_meeting_tracking_fields($zoomid, $trackingfields) {
    global $DB;

    $tfvalues = [];
    foreach ($trackingfields as $trackingfield) {
        $field = str_replace(' ', '_', strtolower($trackingfield->field));
        $tfvalues[$field] = $trackingfield->value;
    }

    $tfrows = $DB->get_records('zoom_meeting_tracking_fields', ['meeting_id' => $zoomid]);
    $tfobjects = [];
    foreach ($tfrows as $tfrow) {
        $tfobjects[$tfrow->tracking_field] = $tfrow;
    }

    $defaulttrackingfields = zoom_clean_tracking_fields();
    foreach ($defaulttrackingfields as $key => $defaulttrackingfield) {
        $value = $tfvalues[$key] ?? '';
        if (isset($tfobjects[$key])) {
            $tfobject = $tfobjects[$key];
            if ($value === '') {
                $DB->delete_records('zoom_meeting_tracking_fields', ['meeting_id' => $zoomid, 'tracking_field' => $key]);
            } else if ($tfobject->value !== $value) {
                $tfobject->value = $value;
                $DB->update_record('zoom_meeting_tracking_fields', $tfobject);
            }
        } else if ($value !== '') {
            $tfobject = new stdClass();
            $tfobject->meeting_id = $zoomid;
            $tfobject->tracking_field = $key;
            $tfobject->value = $value;
            $DB->insert_record('zoom_meeting_tracking_fields', $tfobject);
        }
    }
}

/**
 * Get all meeting records
 *
 * @return array All zoom meetings stored in the database.
 */
function zoom_get_all_meeting_records() {
    global $DB;

    $meetings = [];
    // Only get meetings that exist on zoom.
    $records = $DB->get_records('zoom', ['exists_on_zoom' => ZOOM_MEETING_EXISTS]);
    foreach ($records as $record) {
        $meetings[] = $record;
    }

    return $meetings;
}

/**
 * Get all recordings for a particular meeting.
 *
 * @param int $zoomid Optional. The id of the zoom meeting.
 *
 * @return array All the recordings for the zoom meeting.
 */
function zoom_get_meeting_recordings($zoomid = null) {
    global $DB;

    $params = [];
    if ($zoomid !== null) {
        $params['zoomid'] = $zoomid;
    }

    $records = $DB->get_records('zoom_meeting_recordings', $params);
    $recordings = [];
    foreach ($records as $recording) {
        $recordings[$recording->zoomrecordingid] = $recording;
    }

    return $recordings;
}

/**
 * Get all meeting recordings grouped together.
 *
 * @param int $zoomid Optional. The id of the zoom meeting.
 *
 * @return array All recordings for the zoom meeting grouped together.
 */
function zoom_get_meeting_recordings_grouped($zoomid = null) {
    global $DB;

    $params = [];
    if ($zoomid !== null) {
        $params['zoomid'] = $zoomid;
    }

    $records = $DB->get_records('zoom_meeting_recordings', $params, 'recordingstart ASC');
    $recordings = [];
    foreach ($records as $recording) {
        $recordings[$recording->meetinguuid][$recording->zoomrecordingid] = $recording;
    }

    return $recordings;
}

/**
 * Singleton for Zoom webservice class.
 *
 * @return \mod_zoom\webservice
 */
function zoom_webservice() {
    static $service;

    if (empty($service)) {
        $service = new \mod_zoom\webservice();
    }

    return $service;
}

/**
 * Helper to get a Zoom user, efficiently.
 *
 * @param string|int $identifier The user's email or the user's ID per Zoom API.
 * @return stdClass|false If user is found, returns a Zoom user object. Otherwise, returns false.
 */
function zoom_get_user($identifier) {
    static $users = [];

    if (!isset($users[$identifier])) {
        $users[$identifier] = zoom_webservice()->get_user($identifier);
    }

    return $users[$identifier];
}

/**
 * Helper to get Zoom user settings, efficiently.
 *
 * @param string|int $identifier The user's email or the user's ID per Zoom API.
 * @return stdClass|false If user is found, returns a Zoom user object. Otherwise, returns false.
 */
function zoom_get_user_settings($identifier) {
    static $settings = [];

    if (!isset($settings[$identifier])) {
        $settings[$identifier] = zoom_webservice()->get_user_settings($identifier);
    }

    return $settings[$identifier];
}

/**
 * Get the zoom meeting registrants.
 *
 * @param string $meetingid Zoom meeting ID.
 * @param bool $iswebinar If the session is a webinar.
 * @return stdClass Returns a Zoom object containing the registrants (if found).
 */
function zoom_get_meeting_registrants($meetingid, $iswebinar) {
    $response = zoom_webservice()->get_meeting_registrants($meetingid, $iswebinar);
    return $response;
}

/**
 * Checks if a user has registered for a meeting/webinar based on their email address.
 *
 * @param string $useremail The email address of a user used to determine if they registered or not.
 * @param string $meetingid Zoom meeting ID.
 * @param bool $iswebinar If the session is a webinar.
 * @return bool Returns whether or not the user has registered for the zoom meeting/webinar based on their email address.
 */
function zoom_is_user_registered_for_meeting($useremail, $meetingid, $iswebinar) {
    $registrantjoinurl = zoom_get_registrant_join_url($useremail, $meetingid, $iswebinar);
    return !empty($registrantjoinurl);
}

/**
 * Get the join url for a user for the specified meeting/webinar.
 *
 * @param string $useremail The email address of a user used to determine if they registered or not.
 * @param string $meetingid Zoom meeting ID.
 * @param bool $iswebinar If the session is a webinar.
 * @return string|false Returns the join url for the user (based on email address) for the specified meeting (if found).
 */
function zoom_get_registrant_join_url($useremail, $meetingid, $iswebinar) {
    $response = zoom_get_meeting_registrants($meetingid, $iswebinar);
    if (isset($response->registrants)) {
        foreach ($response->registrants as $registrant) {
            if (strcasecmp($useremail, $registrant->email) == 0) {
                return $registrant->join_url;
            }
        }
    }

    return false;
}

/**
 * The configured pooled-hosts group name, or null when pooled mode is off.
 *
 * Pooled-hosts feature (see README.md, 'Pooled hosts mode'): pooled
 * mode is on iff zoom/pooledhostsgroup is non-empty.
 *
 * @return ?string
 */
function zoom_pooled_group() {
    $group = trim((string) get_config('zoom', 'pooledhostsgroup'));
    return ($group !== '') ? $group : null;
}

/**
 * Get the pool member Zoom user objects for the configured group.
 *
 * Pooled-hosts feature. Fails loudly (never degrades to an empty
 * pool) when the configured group does not exist or cannot be read.
 *
 * @param ?context $context Context for the pool_misconfigured event (system context if null).
 * @return array Zoom user objects (full /users/{id} payload, so ->type is present).
 * @throws moodle_exception When the configured group is missing/unreadable.
 */
function zoom_pooled_members($context = null) {
    $groupname = zoom_pooled_group();
    $service = zoom_webservice();

    $groupid = $service->get_group_id_by_name($groupname);
    if ($groupid === false) {
        \mod_zoom\event\pool_misconfigured::create([
            'context' => $context ?? context_system::instance(),
            'other' => ['group' => $groupname],
        ])->trigger();
        throw new moodle_exception('zoomerr_pool_misconfigured', 'mod_zoom', '', $groupname);
    }

    // Member payloads carry id/email/type directly (measured, T13) — no
    // per-member user lookup needed.
    $members = array_filter($service->get_group_members($groupid));

    // Default-on safety net: only Licensed pool members may host (a Basic
    // host's registration-bearing writes are silently stripped — T1/T3).
    $requirelicense = get_config('zoom', 'pooledrequirelicense');
    if ($requirelicense === false || $requirelicense === '' || $requirelicense === null || $requirelicense) {
        $members = array_filter($members, function ($member) {
            return ($member->type ?? ZOOM_USER_TYPE_BASIC) != ZOOM_USER_TYPE_BASIC;
        });
    }

    // Empty pool — whether the group has no members or none survive the
    // license filter — is a configuration problem, not a capacity signal.
    // Distinct message: "group unreadable" sent a debugging session the
    // wrong way when the actual cause was a Basic-only pool under the
    // licensed-only filter (local dev, 2026-08-17).
    if (empty($members)) {
        \mod_zoom\event\pool_misconfigured::create([
            'context' => $context ?? context_system::instance(),
            'other' => ['group' => $groupname],
        ])->trigger();
        throw new moodle_exception('zoomerr_pool_nousable', 'mod_zoom', '', $groupname);
    }

    return array_values($members);
}

/**
 * Whether a candidate pool host has a scheduling conflict with any slot.
 *
 * Pooled-hosts feature. Checks the host's complete Zoom calendar against
 * every slot, requiring zoom/slotbuffer minutes of gap on both sides. The
 * listing includes meetings scheduled outside Moodle and is per-occurrence
 * and occurrence-edit-aware (measured 2026-08-16: cancelled occurrences
 * absent, moved ones at their actual date), fetched once over a window
 * spanning all slots — O(1) API calls per host however many slots.
 * NB Zoom itself accepts overlapping schedules — only runtime enforces
 * one-active-meeting (T12) — so this check is the only scheduling-time guard.
 *
 * @param string $zoomuserid Candidate pool host.
 * @param array $slots [start (Unix timestamp), duration (seconds)] pairs.
 * @param ?int $excludemeetingid Meeting ID to ignore (when revalidating an
 *        existing meeting; matches every occurrence of that series).
 * @return bool
 */
function zoom_pooled_slots_conflict($zoomuserid, array $slots, $excludemeetingid = null) {
    if (empty($slots)) {
        return false;
    }

    $bufferseconds = ((int) get_config('zoom', 'slotbuffer') ?: 15) * MINSECS;
    $intervals = [];
    foreach ($slots as $slot) {
        $intervals[] = [$slot[0] - $bufferseconds, $slot[0] + $slot[1] + $bufferseconds];
    }

    // Bound the listing server-side to the day(s) around the slots. UTC dates
    // on purpose: Zoom interprets from/to as dates, and the one-day padding on
    // each side absorbs any timezone offset between UTC and the account TZ.
    // The overlap math itself is all epoch-based (Zoom start_time is ISO8601
    // UTC, strtotime handles the Z suffix), so only these bounds involve dates.
    $from = gmdate('Y-m-d', min(array_column($intervals, 0)) - DAYSECS);
    $to = gmdate('Y-m-d', max(array_column($intervals, 1)) + DAYSECS);

    foreach (zoom_webservice()->get_user_upcoming_meetings($zoomuserid, $from, $to) as $meeting) {
        if ($excludemeetingid !== null && (string) $meeting->id === (string) $excludemeetingid) {
            continue;
        }

        if (empty($meeting->start_time)) {
            continue;
        }

        $otherstart = strtotime($meeting->start_time);
        $otherend = $otherstart + (($meeting->duration ?? 60) * MINSECS);
        foreach ($intervals as $interval) {
            if ($otherstart < $interval[1] && $otherend > $interval[0]) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Normalise Zoom occurrence objects into [start, duration] slots.
 *
 * Pooled-hosts feature. Accepts raw API occurrences (ISO start_time,
 * duration in minutes) or populate_zoom_from_response()-normalised ones
 * (epoch seconds / duration seconds); deleted tombstones are skipped.
 *
 * @param stdClass $zoom Meeting record (series duration fallback).
 * @param array $occurrences Zoom occurrence objects.
 * @return array[] [start (Unix timestamp), duration (seconds)] pairs.
 */
function zoom_pooled_occurrence_slots($zoom, array $occurrences) {
    $seriesduration = (int) ($zoom->duration ?? 0) ?: HOURSECS;
    $slots = [];
    foreach ($occurrences as $occurrence) {
        if (($occurrence->status ?? '') === 'deleted') {
            continue;
        }

        $start = is_numeric($occurrence->start_time) ? (int) $occurrence->start_time : strtotime($occurrence->start_time);
        $duration = (int) ($occurrence->duration ?? 0);
        if ($duration > 0 && !is_numeric($occurrence->start_time)) {
            // Raw API values carry the duration in minutes.
            $duration = $duration * MINSECS;
        }

        $slots[] = [$start, $duration ?: $seriesduration];
    }

    return $slots;
}

/**
 * Refresh the persisted occurrence store from a Zoom occurrence list.
 *
 * Pooled-hosts feature (occurrence-first scheduling): the store is a cache
 * of Zoom's authoritative occurrence list, refreshed immediately after every
 * occurrence action and daily by the update_meetings task (which also picks
 * up out-of-band portal edits). FUTURE rows absent from the list are
 * removed; past rows are kept forever — Zoom ages ended occurrences out of
 * the readback, and the sessions table (and its recordings) needs the
 * history.
 *
 * @param int $zoomid zoom table id.
 * @param array $occurrences Zoom occurrence objects (raw or normalised).
 * @return void
 */
function zoom_pooled_sync_occurrences($zoomid, array $occurrences) {
    global $DB;

    $existing = $DB->get_records('zoom_occurrences', ['zoomid' => $zoomid], '', 'occurrenceid, id, starttime, duration, status');
    $seen = [];
    foreach ($occurrences as $occurrence) {
        $occurrenceid = (string) ($occurrence->occurrence_id ?? '');
        if ($occurrenceid === '') {
            continue;
        }

        $start = is_numeric($occurrence->start_time) ? (int) $occurrence->start_time : strtotime($occurrence->start_time);
        $duration = (int) ($occurrence->duration ?? 0);
        if ($duration > 0 && !is_numeric($occurrence->start_time)) {
            $duration = $duration * MINSECS;
        }

        $status = ($occurrence->status ?? '') === 'deleted' ? 'deleted' : 'available';
        // 'removed' is a Moodle-side refinement of Zoom's tombstone: the
        // occurrence was struck before it was ever really planned, so the
        // table hides it instead of showing a cancellation. Zoom cannot
        // distinguish the two (both are the same permanent tombstone) —
        // preserve the local refinement across syncs.
        if ($status === 'deleted' && isset($existing[$occurrenceid]) && $existing[$occurrenceid]->status === 'removed') {
            $status = 'removed';
        }

        $seen[$occurrenceid] = true;

        if (isset($existing[$occurrenceid])) {
            $row = $existing[$occurrenceid];
            if ((int) $row->starttime !== $start || (int) $row->duration !== $duration || $row->status !== $status) {
                $DB->update_record('zoom_occurrences', (object) [
                    'id' => $row->id,
                    'starttime' => $start,
                    'duration' => $duration,
                    'status' => $status,
                    'timemodified' => time(),
                ]);
            }
        } else {
            $DB->insert_record('zoom_occurrences', (object) [
                'zoomid' => $zoomid,
                'occurrenceid' => $occurrenceid,
                'starttime' => $start,
                'duration' => $duration,
                'status' => $status,
                'timemodified' => time(),
            ]);
        }
    }

    foreach ($existing as $occurrenceid => $row) {
        if (isset($seen[$occurrenceid])) {
            continue;
        }

        // Past rows are history — Zoom ages ended occurrences out of the
        // meeting readback, but the table still needs them (dates, and the
        // recordings that hang off them). Only future rows absent from the
        // readback are really gone (e.g. the grid was regenerated).
        if ((int) $row->starttime < time()) {
            continue;
        }

        $DB->delete_records('zoom_occurrences', ['id' => $row->id]);
    }
}

/**
 * Re-read a meeting from Zoom and refresh record, occurrence store and calendar.
 *
 * Pooled-hosts feature (occurrence-first scheduling): called after every
 * occurrence action so Moodle reflects the change immediately instead of at
 * the daily sync.
 *
 * @param stdClass $zoom zoom record (must have id, meeting_id, webinar).
 * @return stdClass The refreshed record (with ->occurrences).
 */
function zoom_pooled_refresh_from_zoom($zoom) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/zoom/lib.php');

    $response = zoom_webservice()->get_meeting_webinar_info($zoom->meeting_id, $zoom->webinar);
    $zoom = populate_zoom_from_response($zoom, $response);
    $DB->update_record('zoom', $zoom);
    zoom_pooled_sync_occurrences($zoom->id, $zoom->occurrences ?? []);
    zoom_calendar_item_update($zoom);
    return $zoom;
}

/**
 * Zoom recording types that carry video (the occurrence table lists only
 * these; audio-only, transcript, chat etc. files are not shown).
 */
define('ZOOM_POOLED_VIDEO_RECORDING_TYPES', [
    'shared_screen_with_speaker_view',
    'shared_screen_with_speaker_view(CC)',
    'shared_screen_with_gallery_view',
    'shared_screen',
    'speaker_view',
    'gallery_view',
    'active_speaker',
]);

/**
 * Render the occurrence table for a pooled meeting.
 *
 * Pooled-hosts feature (occurrence-first scheduling): the single schedule
 * surface — one row per occurrence with inline video recordings; managers
 * get add/move/cancel actions (occurrence.php). Replaces the Schedule box.
 *
 * @param stdClass $zoom zoom record.
 * @param stdClass $cm Course module.
 * @param bool $iszoommanager Whether the viewer manages this activity.
 * @return string HTML ('' when there is nothing to show).
 */
function zoom_pooled_occurrence_table($zoom, $cm, $iszoommanager) {
    global $DB, $OUTPUT;

    $rows = $DB->get_records('zoom_occurrences', ['zoomid' => $zoom->id], 'starttime ASC');
    if (empty($rows) && !empty($zoom->recurring) && $zoom->exists_on_zoom == ZOOM_MEETING_EXISTS) {
        // Series predating the store (or created out-of-band): hydrate once.
        try {
            $zoom = zoom_pooled_refresh_from_zoom($zoom);
            $rows = $DB->get_records('zoom_occurrences', ['zoomid' => $zoom->id], 'starttime ASC');
        } catch (moodle_exception $error) {
            debugging('mod_zoom pooled: occurrence hydration failed: ' . $error->getMessage(), DEBUG_DEVELOPER);
        }
    }

    if (empty($rows)) {
        if (empty($zoom->start_time) || !empty($zoom->recurring)) {
            // Recurring-no-fixed-time (or nothing to show at all).
            return '';
        }

        // Legacy single meeting: one read-only row.
        $rows = [(object) [
            'occurrenceid' => '',
            'starttime' => (int) $zoom->start_time,
            'duration' => (int) $zoom->duration,
            'status' => 'available',
        ]];
    }

    // 'removed' rows were struck before they were ever really planned —
    // invisible to everyone (unlike cancelled ones, which stay listed).
    $rows = array_values(array_filter($rows, function ($row) {
        return $row->status !== 'removed';
    }));
    if (empty($rows)) {
        return '';
    }

    // Video recordings grouped by local calendar day of the recording start.
    $recordingsbyday = [];
    if (get_config('zoom', 'viewrecordings')) {
        foreach ($DB->get_records('zoom_meeting_recordings', ['zoomid' => $zoom->id], 'recordingstart ASC') as $recording) {
            if (!in_array($recording->recordingtype, ZOOM_POOLED_VIDEO_RECORDING_TYPES, true)) {
                continue;
            }

            if (!$iszoommanager && intval($recording->showrecording) !== 1) {
                continue;
            }

            $recordingsbyday[userdate($recording->recordingstart, '%Y%m%d')][] = $recording;
        }
    }

    $table = new html_table();
    // w-100: the auto-width table otherwise sits as a small island on the
    // page's left edge while the rest of the activity page reads as a full
    // content-width block.
    $table->attributes['class'] = 'generaltable w-100';
    $table->id = 'zoom_occurrence_table';
    $table->head = [
        get_string('occ_date', 'mod_zoom'),
        get_string('occ_time', 'mod_zoom'),
        get_string('occ_duration', 'mod_zoom'),
        get_string('occ_status', 'mod_zoom'),
    ];
    if (!empty($recordingsbyday) || $iszoommanager) {
        $table->head[] = get_string('recordings', 'mod_zoom');
    }

    if ($iszoommanager) {
        $table->head[] = '';
    }

    $canedit = $iszoommanager && !empty($zoom->recurring) && $zoom->exists_on_zoom == ZOOM_MEETING_EXISTS;
    if ($canedit) {
        global $PAGE;
        $PAGE->requires->js_call_amd('mod_zoom/occurrences', 'init');
    }
    // Inputs live in table cells while their <form> sits in the actions cell
    // — tied together by the HTML5 form="" attribute (no JS, valid nesting).
    $formhiddens = function ($formid, $action, $occurrenceid = '') use ($cm) {
        $html = html_writer::start_tag('form', [
            'id' => $formid, 'method' => 'post', 'class' => 'd-inline',
            'action' => new moodle_url('/mod/zoom/occurrence.php'),
        ]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'occurrence', 'value' => $occurrenceid]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        return $html;
    };
    // Date input (native calendar — weekdays visible) + 24h time select
    // (a native time input would render AM/PM under an English browser
    // locale, whatever Moodle's language is). The weekday label makes the
    // schedule validatable at a glance; mod_zoom/occurrences JS keeps it in
    // step while the date is being edited.
    // [date part, time part] — rendered in separate table columns. The
    // weekday rides inside the date field as an input-group prefix (a
    // native date input cannot display it in its own text).
    $slotinputs = function ($formid, $epoch) {
        [$local] = zoom_pooled_local_start($epoch);
        $time = substr($local, 11, 5);
        $datehtml = html_writer::start_div('input-group d-inline-flex w-auto align-middle flex-nowrap');
        $datehtml .= html_writer::span(userdate($epoch, '%a'), 'input-group-text zoom-occ-weekday', [
            'data-zoom-occ-weekday' => 1, 'style' => 'min-width:3.2em;justify-content:center',
        ]);
        $datehtml .= html_writer::empty_tag('input', [
            'type' => 'date', 'name' => 'newdate', 'form' => $formid,
            'value' => substr($local, 0, 10), 'required' => 'required',
            'class' => 'form-control w-auto zoom-occ-date',
        ]);
        $datehtml .= zoom_pooled_date_companion();
        $datehtml .= html_writer::end_div();
        $timehtml = html_writer::select(
            zoom_pooled_time_options($time),
            'newtime',
            $time,
            false,
            ['form' => $formid, 'class' => 'custom-select d-inline-block w-auto']
        );
        return [$datehtml, $timehtml];
    };

    $now = time();
    $lastactive = null;
    foreach ($rows as $row) {
        $cancelled = ($row->status === 'deleted');
        $past = !$cancelled && ($row->starttime + ($row->duration ?: HOURSECS)) < $now;
        $editable = $canedit && !$cancelled && !$past && $row->occurrenceid !== '';
        if (!$cancelled) {
            $lastactive = $row;
        }

        $datetext = userdate($row->starttime, get_string('strftimedaydate', 'langconfig'));
        $timetext = userdate($row->starttime, get_string('strftimetime24', 'langconfig'));
        if ($cancelled) {
            $datetext = html_writer::tag('s', $datetext);
            $timetext = html_writer::tag('s', $timetext);
            $status = html_writer::span(get_string('occ_cancelled', 'mod_zoom'), 'badge badge-secondary text-muted');
        } else if ($past) {
            $status = html_writer::span(get_string('occ_past', 'mod_zoom'), 'badge badge-light');
        } else {
            $status = html_writer::span(get_string('occ_upcoming', 'mod_zoom'), 'badge badge-info');
        }

        if ($editable) {
            $formid = 'zoom-occ-move-' . $row->occurrenceid;
            [$datecell, $timecell] = $slotinputs($formid, (int) $row->starttime);
            $durationcell = html_writer::empty_tag('input', [
                'type' => 'number', 'name' => 'newduration', 'form' => $formid,
                'value' => (int) round(($row->duration ?: $zoom->duration) / 60), 'min' => 1, 'max' => 1440,
                'class' => 'form-control d-inline-block', 'style' => 'width:5.5em',
            ]) . ' min';
        } else {
            $datecell = $datetext;
            $timecell = $timetext;
            $durationcell = $cancelled ? '' : format_time((int) ($row->duration ?: $zoom->duration));
        }

        $cells = [$datecell, $timecell, $durationcell, $status];

        if (!empty($recordingsbyday) || $iszoommanager) {
            $links = [];
            if (!$cancelled) {
                foreach ($recordingsbyday[userdate($row->starttime, '%Y%m%d')] ?? [] as $recording) {
                    $url = new moodle_url('/mod/zoom/loadrecording.php', ['id' => $cm->id, 'recordingid' => $recording->id]);
                    $label = get_string('occ_recording', 'mod_zoom');
                    if ($iszoommanager && intval($recording->showrecording) !== 1) {
                        $label .= ' ' . get_string('occ_recording_hidden', 'mod_zoom');
                    }

                    $links[] = html_writer::link($url, $label, ['target' => '_blank']);
                }
            }

            $cells[] = implode(' ', $links);
        }

        if ($iszoommanager) {
            $actions = '';
            if ($editable) {
                $formid = 'zoom-occ-move-' . $row->occurrenceid;
                $actions .= $formhiddens($formid, 'move', $row->occurrenceid);
                // Grey while the row matches the stored schedule, primary
                // when dirty; Revert discards the edit (mod_zoom/occurrences).
                $actions .= html_writer::empty_tag('input', [
                    'type' => 'submit', 'value' => get_string('savechanges'),
                    'class' => 'btn btn-secondary btn-sm mr-1',
                ]);
                $actions .= html_writer::tag('button', get_string('occ_revert', 'mod_zoom'), [
                    'type' => 'button', 'data-zoom-occ-revert' => 1,
                    'class' => 'btn btn-link btn-sm d-none',
                ]);
                $actions .= html_writer::end_tag('form');
                $actions .= ' ' . html_writer::link(new moodle_url('/mod/zoom/occurrence.php', [
                    'id' => $cm->id, 'action' => 'cancel', 'occurrence' => $row->occurrenceid,
                ]), get_string('occ_cancel', 'mod_zoom'));
                $actions .= ' | ' . html_writer::link(new moodle_url('/mod/zoom/occurrence.php', [
                    'id' => $cm->id, 'action' => 'delete', 'occurrence' => $row->occurrenceid,
                ]), get_string('occ_delete', 'mod_zoom'));
            } else if ($canedit && $cancelled && $row->occurrenceid !== '') {
                // Cancellation artifact cleanup: hide it from the list.
                $actions .= html_writer::link(new moodle_url('/mod/zoom/occurrence.php', [
                    'id' => $cm->id, 'action' => 'remove', 'occurrence' => $row->occurrenceid, 'sesskey' => sesskey(),
                ]), get_string('occ_remove', 'mod_zoom'));
            }

            $cells[] = html_writer::div($actions, 'text-nowrap');
        }

        $table->data[] = $cells;
    }

    $html = $OUTPUT->box_start('', 'zoom_section-occurrences');
    $html .= $OUTPUT->heading(get_string('occurrences', 'mod_zoom'), 3);
    $html .= html_writer::table($table);

    // Collapsed add form (native <details>, no JS): the inputs only appear
    // once "Add an occurrence" is clicked.
    if ($canedit) {
        $adddefault = $lastactive ? ((int) $lastactive->starttime + WEEKSECS) : ($now + DAYSECS);
        $adddurationdefault = (int) round((($lastactive->duration ?? 0) ?: $zoom->duration) / 60);
        $html .= html_writer::start_tag('details', ['class' => 'mb-2']);
        $html .= html_writer::tag('summary', get_string('occ_add', 'mod_zoom'), ['class' => 'btn btn-secondary']);
        $html .= html_writer::start_div('p-2');
        [$adddate, $addtime] = $slotinputs('zoom-occ-add', $adddefault);
        $html .= $adddate . ' ' . $addtime;
        $html .= ' ' . html_writer::empty_tag('input', [
            'type' => 'number', 'name' => 'newduration', 'form' => 'zoom-occ-add',
            'value' => $adddurationdefault, 'min' => 1, 'max' => 1440,
            'class' => 'form-control d-inline-block', 'style' => 'width:5.5em',
        ]) . ' min ';
        $html .= $formhiddens('zoom-occ-add', 'add')
            . html_writer::empty_tag('input', [
                'type' => 'submit', 'value' => get_string('occ_add', 'mod_zoom'),
                'class' => 'btn btn-primary btn-sm ml-2',
            ])
            . html_writer::tag('button', get_string('cancel'), [
                'type' => 'button', 'data-zoom-occ-close' => 1,
                'class' => 'btn btn-link btn-sm',
            ])
            . html_writer::end_tag('form');
        $html .= html_writer::end_div();
        $html .= html_writer::end_tag('details');
    }

    $html .= $OUTPUT->box_end();
    return $html;
}

/**
 * Collect the planned session dates from the activity form data.
 *
 * Pooled-hosts feature (occurrence-first scheduling): the plan is the first
 * session (start_time) plus every enabled row of the plandates repeater,
 * de-duplicated and sorted.
 *
 * @param stdClass $data Form data.
 * @return int[] Sorted Unix timestamps.
 */
function zoom_pooled_collect_plan($data) {
    $dates = [(int) ($data->start_time ?? 0)];
    foreach ((array) ($data->plandates ?? []) as $date) {
        if ((int) $date > 0) {
            $dates[] = (int) $date;
        }
    }

    $dates = array_values(array_unique(array_filter($dates)));
    sort($dates);
    return $dates;
}

/**
 * Move a freshly created scaffold series onto the planned dates.
 *
 * Pooled-hosts feature (occurrence-first scheduling): after create the
 * series sits on the scaffold grid (weekly from the first session); each
 * grid occurrence is PATCHed onto its planned date (both sorted, 1:1), then
 * record, store and calendar are refreshed from Zoom.
 *
 * @param stdClass $zoom zoom record (id, meeting_id, webinar set).
 * @param array $occurrences The create response's occurrence list.
 * @param array $slots Planned [start, duration(seconds)] pairs (sorted).
 * @return stdClass The refreshed record.
 */
function zoom_pooled_apply_plan($zoom, array $occurrences, array $slots) {
    // [start, duration, occurrence_id] per non-deleted occurrence, by start.
    $grid = [];
    foreach ($occurrences as $occurrence) {
        if (($occurrence->status ?? '') === 'deleted') {
            continue;
        }

        $start = is_numeric($occurrence->start_time) ? (int) $occurrence->start_time : strtotime($occurrence->start_time);
        $gridduration = (int) ($occurrence->duration ?? 0);
        if ($gridduration > 0 && !is_numeric($occurrence->start_time)) {
            $gridduration = $gridduration * MINSECS;
        }

        $grid[] = [$start, $gridduration, (string) $occurrence->occurrence_id];
    }

    usort($grid, function ($a, $b) {
        return $a[0] <=> $b[0];
    });
    if (count($grid) !== count($slots)) {
        debugging('mod_zoom pooled: scaffold expanded to ' . count($grid) . ' occurrences for '
            . count($slots) . ' planned dates', DEBUG_DEVELOPER);
    }

    foreach ($slots as $i => $slot) {
        if (!isset($grid[$i])) {
            break;
        }

        if ($grid[$i][0] !== $slot[0] || $grid[$i][1] !== $slot[1]) {
            zoom_webservice()->patch_meeting_occurrence($zoom, $grid[$i][2], $slot[0], $slot[1]);
        }
    }

    return zoom_pooled_refresh_from_zoom($zoom);
}

/**
 * Companion controls for a native date input: a JJ/MM/AAAA text field and a
 * calendar button.
 *
 * A native date input displays in the BROWSER's locale — an English-UI
 * browser shows MM/DD/YYYY whatever Moodle's language is. The
 * mod_zoom/occurrences module makes the text field the visible control
 * (deterministic day/month/year everywhere) and shrinks the native input to
 * an invisible value carrier whose picker opens from the button
 * (showPicker()). Without JS the native input stays as-is.
 *
 * @return string HTML (hidden until the module activates it).
 */
function zoom_pooled_date_companion() {
    global $OUTPUT;
    $html = html_writer::empty_tag('input', [
        'type' => 'text', 'size' => 10, 'maxlength' => 10,
        'placeholder' => get_string('occ_dateformat', 'mod_zoom'),
        'inputmode' => 'numeric',
        'aria-label' => get_string('occ_date', 'mod_zoom'),
        'class' => 'form-control w-auto zoom-occ-datetext d-none',
    ]);
    $html .= html_writer::tag('button', $OUTPUT->pix_icon('i/calendar', ''), [
        'type' => 'button', 'tabindex' => '-1',
        'aria-label' => get_string('calendar', 'calendar'),
        'class' => 'btn btn-outline-secondary zoom-occ-datebtn d-none',
    ]);
    return $html;
}

/**
 * The plain-HTML occurrence planner for the create form.
 *
 * Rendered inside a 'static' form element: formslib outputs static HTML
 * verbatim, which sidesteps custom-QuickForm-element rendering entirely
 * (a raw PEAR element degraded the whole form to the legacy renderer and
 * scattered duplicated inputs — 2026-08-17). The inputs are read back via
 * optional_param_array() in the form's validation()/data_postprocessing().
 *
 * Row 1 is the first occurrence; empty date = row skipped. The
 * mod_zoom/occurrences module reveals rows on demand and fills bulk
 * patterns (+5 daily/weekly/monthly).
 *
 * @param int $rows Total rows rendered (hidden until used).
 * @return string HTML.
 */
function zoom_pooled_planner_html($rows = 30) {
    $defaultstart = time() + 3600;
    $defaultdate = date('Y-m-d', $defaultstart);
    $defaulttime = sprintf('%02d:%02d', date('H', $defaultstart), 15 * floor(date('i', $defaultstart) / 15));

    $html = html_writer::start_div('zoom-occ-planner', ['data-zoom-occ-planner' => 1]);
    for ($i = 0; $i < $rows; $i++) {
        $first = ($i === 0);
        $html .= html_writer::start_div('mb-1 zoom-occ-planner-row' . ($first ? '' : ' d-none'), [
            'data-zoom-occ-row' => $i,
        ]);
        $html .= html_writer::start_div('input-group d-inline-flex w-auto mr-1 align-middle flex-nowrap');
        $html .= html_writer::span('', 'input-group-text zoom-occ-weekday', [
            'data-zoom-occ-weekday' => 1, 'style' => 'min-width:3.2em;justify-content:center',
        ]);
        $html .= html_writer::empty_tag('input', [
            'type' => 'date', 'name' => 'zoomplan_date[]',
            'value' => $first ? $defaultdate : '',
            'class' => 'form-control w-auto zoom-occ-date',
        ]);
        $html .= zoom_pooled_date_companion();
        $html .= html_writer::end_div();
        $html .= html_writer::empty_tag('input', [
            'type' => 'text', 'name' => 'zoomplan_time[]',
            'value' => $first ? $defaulttime : '',
            'size' => 5, 'maxlength' => 5, 'placeholder' => 'HH:MM',
            'pattern' => '([01][0-9]|2[0-3]):[0-5][0-9]',
            'class' => 'form-control d-inline-block w-auto mr-1',
        ]);
        $html .= html_writer::empty_tag('input', [
            'type' => 'number', 'name' => 'zoomplan_minutes[]',
            'value' => $first ? 60 : '',
            'min' => 1, 'max' => 1440, 'placeholder' => 'min',
            'class' => 'form-control d-inline-block', 'style' => 'width:5.5em',
        ]);
        $html .= html_writer::span(' min ', 'mr-1');
        foreach (['daily' => 'occ_planner_daily', 'weekly' => 'occ_planner_weekly',
                'monthly' => 'occ_planner_monthly'] as $kind => $string) {
            $html .= html_writer::tag('button', get_string($string, 'mod_zoom'), [
                'type' => 'button', 'data-zoom-occ-spread' => $kind,
                'class' => 'btn btn-link btn-sm d-none px-1',
                'title' => get_string($string . '_help', 'mod_zoom'),
            ]);
        }

        $html .= html_writer::tag('button', '✕', [
            'type' => 'button', 'data-zoom-occ-clearrow' => 1,
            'class' => 'btn btn-link btn-sm d-none px-1',
            'aria-label' => get_string('delete'), 'title' => get_string('delete'),
        ]);
        $html .= html_writer::end_div();
    }

    $buttons = [
        ['data-zoom-occ-addrow' => 1, 'label' => get_string('occ_planner_addrow', 'mod_zoom')],
    ];
    $html .= html_writer::start_div('mt-1 zoom-occ-planner-buttons d-none');
    foreach ($buttons as $button) {
        $label = $button['label'];
        unset($button['label']);
        $html .= html_writer::tag('button', $label, $button + [
            'type' => 'button', 'class' => 'btn btn-secondary btn-sm mr-1',
        ]);
    }

    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    return $html;
}

/**
 * Read the planner rows from the submitted request.
 *
 * The planner lives in a static element, so its inputs are not part of the
 * moodleform data — they are read straight from the request. Rows with an
 * empty date are skipped; a filled row that does not parse yields 0 (the
 * caller reports it).
 *
 * @return array [array rows keyed by input index, each
 *                ['start' => int epoch (0 = unparseable), 'minutes' => int],
 *                bool whether any row was submitted at all]
 */
function zoom_pooled_planner_submitted() {
    $dates = optional_param_array('zoomplan_date', [], PARAM_RAW_TRIMMED);
    $times = optional_param_array('zoomplan_time', [], PARAM_RAW_TRIMMED);
    $minutes = optional_param_array('zoomplan_minutes', [], PARAM_RAW_TRIMMED);
    $rows = [];
    foreach ($dates as $i => $date) {
        if (trim($date) === '') {
            continue;
        }

        $rows[$i] = [
            'start' => zoom_pooled_parse_local(trim($date) . ' ' . trim($times[$i] ?? '')),
            'minutes' => (int) ($minutes[$i] ?? 0),
        ];
    }

    return [$rows, !empty($dates)];
}

/**
 * 24h time options for the occurrence time dropdowns, 15-minute steps.
 *
 * A native time input renders AM/PM under an English browser locale
 * whatever Moodle's language is — a select is unambiguous everywhere.
 *
 * @param ?string $include Off-grid value to include (e.g. an existing
 *        occurrence at 09:05).
 * @return array value => label ('HH:MM').
 */
function zoom_pooled_time_options($include = null) {
    $options = [];
    for ($h = 0; $h < 24; $h++) {
        for ($m = 0; $m < 60; $m += 15) {
            $value = sprintf('%02d:%02d', $h, $m);
            $options[$value] = $value;
        }
    }

    if ($include !== null && $include !== '' && !isset($options[$include])) {
        $options[$include] = $include;
        ksort($options);
    }

    return $options;
}

/**
 * Parse a local wall-clock string into an epoch.
 *
 * Counterpart of zoom_pooled_local_start(): the site timezone is the one
 * every Zoom write uses, so form inputs are interpreted in it too.
 *
 * @param string $raw e.g. '2026-09-07T09:00' or '2026-09-07 09:00'.
 * @return int Unix timestamp, or 0 when the string cannot be parsed.
 */
function zoom_pooled_parse_local($raw) {
    global $CFG;
    $raw = trim((string) $raw);
    if ($raw === '') {
        return 0;
    }

    $tzname = !empty($CFG->timezone) ? $CFG->timezone : date_default_timezone_get();
    try {
        return (new DateTimeImmutable($raw, new DateTimeZone($tzname)))->getTimestamp();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Local wall-clock representation of an epoch for occurrence PATCH bodies.
 *
 * Zoom occurrence updates take a local datetime + timezone; use the same
 * timezone create_meeting() sends so the semantics line up.
 *
 * @param int $epoch Unix timestamp.
 * @return array [start_time (Y-m-d\TH:i:s), timezone]
 */
function zoom_pooled_local_start($epoch) {
    global $CFG;
    $tzname = !empty($CFG->timezone) ? $CFG->timezone : date_default_timezone_get();
    try {
        $tz = new DateTimeZone($tzname);
    } catch (Exception $e) {
        $tz = new DateTimeZone(date_default_timezone_get());
        $tzname = $tz->getName();
    }

    $local = (new DateTimeImmutable('@' . $epoch))->setTimezone($tz)->format('Y-m-d\TH:i:s');
    return [$local, $tzname];
}

/**
 * Add an occurrence to a pooled series at a given slot.
 *
 * Pooled-hosts feature (occurrence-first scheduling). Zoom has no
 * add-occurrence API; the measured-safe composite is: extend the scaffold
 * rule by one (grid-compatible end_times+1 preserves every occurrence edit),
 * then move the appended occurrence onto the target date. The target slot is
 * conflict-checked against the meeting's (fixed) host first.
 *
 * @param stdClass $zoom zoom record.
 * @param int $start Target start (Unix timestamp).
 * @param int $duration Target duration (seconds).
 * @return void
 * @throws moodle_exception zoomerr_pool_exhausted on slot conflict,
 *         zoomerr_occurrence_limit at the series cap.
 */
function zoom_pooled_occurrence_add($zoom, $start, $duration) {
    global $DB;

    if (zoom_pooled_slots_conflict($zoom->host_id, [[$start, $duration]], $zoom->meeting_id)) {
        throw new moodle_exception('zoomerr_pool_exhausted', 'mod_zoom');
    }

    $known = $DB->get_records('zoom_occurrences', ['zoomid' => $zoom->id], '', 'occurrenceid');
    // The extend re-sends Zoom's OWN rule verbatim (readback-based, cap
    // enforced there): anything else risks a grid regeneration that wipes
    // every move and cancellation.
    zoom_webservice()->extend_meeting_series($zoom);

    // Find the appended occurrence (the one the store has never seen).
    $response = zoom_webservice()->get_meeting_webinar_info($zoom->meeting_id, $zoom->webinar);
    $appended = null;
    foreach ($response->occurrences ?? [] as $occurrence) {
        if (!isset($known[(string) $occurrence->occurrence_id])) {
            $appended = $occurrence;
            break;
        }
    }

    if ($appended !== null) {
        zoom_webservice()->patch_meeting_occurrence($zoom, $appended->occurrence_id, $start, $duration);
    }

    $zoom = zoom_pooled_refresh_from_zoom($zoom);
    // Keep the stored rule counter in step (informational only — every rule
    // write derives from the Zoom readback, never from this field). Zoom's
    // end_times counts tombstones, i.e. every store row.
    $DB->set_field('zoom', 'end_times', $DB->count_records('zoom_occurrences', ['zoomid' => $zoom->id]), ['id' => $zoom->id]);
}

/**
 * Move an occurrence of a pooled series to a new slot.
 *
 * @param stdClass $zoom zoom record.
 * @param string $occurrenceid Zoom occurrence_id.
 * @param int $start New start (Unix timestamp).
 * @param int $duration New duration (seconds).
 * @return void
 * @throws moodle_exception zoomerr_pool_exhausted on slot conflict.
 */
function zoom_pooled_occurrence_move($zoom, $occurrenceid, $start, $duration) {
    if (zoom_pooled_slots_conflict($zoom->host_id, [[$start, $duration]], $zoom->meeting_id)) {
        throw new moodle_exception('zoomerr_pool_exhausted', 'mod_zoom');
    }

    zoom_webservice()->patch_meeting_occurrence($zoom, $occurrenceid, $start, $duration);
    zoom_pooled_refresh_from_zoom($zoom);
}

/**
 * Cancel (or fully delete) an occurrence of a pooled series.
 *
 * Deletion is a permanent tombstone on Zoom (measured 2026-08-16): it
 * survives any later meeting PATCH and frees the host's slot. Moodle-side
 * the tombstone has two flavors: a CANCELLED session stays visible in the
 * table (struck through — it was planned, students should see the change),
 * a DELETED one is hidden entirely (it was never really planned — e.g. a
 * scaffold surplus). The last remaining active occurrence cannot be
 * cancelled — delete the activity instead (Zoom may drop the whole meeting
 * with its final occurrence).
 *
 * @param stdClass $zoom zoom record.
 * @param string $occurrenceid Zoom occurrence_id.
 * @param bool $remove True = hide from the table too ('removed'), false =
 *        show as cancelled ('deleted').
 * @return void
 * @throws moodle_exception zoomerr_last_occurrence when it is the last one.
 */
function zoom_pooled_occurrence_cancel($zoom, $occurrenceid, $remove = false) {
    global $DB;

    $active = $DB->count_records('zoom_occurrences', ['zoomid' => $zoom->id, 'status' => 'available']);
    if ($active <= 1) {
        throw new moodle_exception('zoomerr_last_occurrence', 'mod_zoom');
    }

    zoom_webservice()->delete_meeting_occurrence($zoom, $occurrenceid);
    zoom_pooled_refresh_from_zoom($zoom);
    if ($remove) {
        zoom_pooled_occurrence_remove($zoom, $occurrenceid);
    }
}

/**
 * Hide a cancelled occurrence from the sessions table ('removed').
 *
 * Moodle-only operation — the Zoom tombstone is untouchable either way.
 * Used directly to clean up cancellation artifacts (occurrences struck
 * during series construction that were never planned from the students'
 * perspective).
 *
 * @param stdClass $zoom zoom record.
 * @param string $occurrenceid Zoom occurrence_id.
 * @return void
 */
function zoom_pooled_occurrence_remove($zoom, $occurrenceid) {
    global $DB;

    $row = $DB->get_record('zoom_occurrences', ['zoomid' => $zoom->id, 'occurrenceid' => $occurrenceid], '*', MUST_EXIST);
    if ($row->status !== 'removed') {
        $DB->update_record('zoom_occurrences', (object) [
            'id' => $row->id,
            'status' => 'removed',
            'timemodified' => time(),
        ]);
    }
}

/**
 * Pick a pool host free for EVERY given slot, or fail loudly.
 *
 * Pooled-hosts feature (occurrence-first scheduling): called at activity
 * save time with the full planned-date set, so placement is batch-shaped —
 * the host must fit the whole plan up front (a later host change would mean
 * a new meeting id and so a new join link). A save that finds no free host
 * errors out — that error is the capacity (buy-a-seat) signal, surfaced via
 * the pool_exhausted event.
 *
 * @param stdClass $zoom The meeting as built from the form.
 * @param array $slots [start (Unix timestamp), duration (seconds)] pairs;
 *        empty = nothing to check (first member wins).
 * @param ?context $context Module/course context for events.
 * @return string The chosen Zoom user ID.
 * @throws moodle_exception When no pool host is free for every slot.
 */
function zoom_pooled_pick_host($zoom, array $slots, $context = null) {
    $members = zoom_pooled_members($context);

    // Registration is a licensed-host feature (an unlicensed host's
    // registration settings are silently stripped — T1): registration-bearing
    // meetings only consider Licensed pool members, whatever
    // pooledrequirelicense says.
    if (isset($zoom->registration) && $zoom->registration != ZOOM_REGISTRATION_OFF) {
        $members = array_values(array_filter($members, function ($member) {
            return ($member->type ?? ZOOM_USER_TYPE_BASIC) != ZOOM_USER_TYPE_BASIC;
        }));
    }

    // Teacher stickiness: start scanning at a position derived from the
    // teacher, so the same teacher's sessions tend to land on the same pool
    // host — their own meetings never overlap, which keeps overrun collisions
    // between DIFFERENT teachers' slots, where the buffer already guards.
    if (!empty($zoom->teacherid) && count($members) > 1) {
        $offset = crc32((string) $zoom->teacherid) % count($members);
        $members = array_merge(array_slice($members, $offset), array_slice($members, 0, $offset));
    }

    $exclude = !empty($zoom->meeting_id) && $zoom->meeting_id != -1 ? $zoom->meeting_id : null;

    foreach ($members as $member) {
        if (empty($slots) || !zoom_pooled_slots_conflict($member->id, $slots, $exclude)) {
            return $member->id;
        }
    }

    \mod_zoom\event\pool_exhausted::create([
        'context' => $context ?? context_system::instance(),
        'other' => [
            'start' => empty($slots) ? 0 : $slots[0][0],
            'duration' => empty($slots) ? 0 : $slots[0][1],
            'occurrences' => count($slots),
        ],
    ])->trigger();
    throw new moodle_exception('zoomerr_pool_exhausted', 'mod_zoom');
}

/**
 * Apply the host-name template before a pooled session start, with CAS stash.
 *
 * Pooled-hosts feature. Renders zoom/hostdisplaynametemplate (placeholders
 * %first/%last from the teacher's Moodle name) and patches the pool host's
 * display_name — the field Zoom actually shows in meetings. First/last name
 * are left untouched: Zoom resets display_name to "first last" whenever
 * first/last are patched, so writing them would fight this. The (previous,
 * set) display name is stashed on the zoom record so the end-of-session task
 * can restore compare-and-swap style: it only restores while the current
 * display name still equals what we set — any other value means someone
 * renamed out of band, hands off. Rename failures are swallowed — a class
 * start is never blocked over a display name.
 *
 * @param stdClass $zoom The zoom activity record.
 * @param stdClass $teacher The Moodle user record of the teacher.
 * @return void
 */
function zoom_pooled_apply_rename($zoom, $teacher) {
    global $DB;

    $template = trim((string) get_config('zoom', 'hostdisplaynametemplate'));
    if ($template === '') {
        return;
    }

    try {
        $hostuser = zoom_get_user($zoom->host_id);
        if (empty($hostuser)) {
            return;
        }

        $setdisplay = trim(str_replace(['%first', '%last'],
            [$teacher->firstname, $teacher->lastname], $template));
        if ($setdisplay === '') {
            return;
        }

        $DB->set_field('zoom', 'poolrename', json_encode([
            'prevdisplay' => $hostuser->display_name ?? '',
            'setdisplay' => $setdisplay,
        ]), ['id' => $zoom->id]);

        zoom_webservice()->update_user_display_name($zoom->host_id, $setdisplay);
    } catch (moodle_exception $error) {
        debugging('mod_zoom pooled rename failed: ' . $error->getMessage(), DEBUG_DEVELOPER);
    }
}

/**
 * Get the display name for a Zoom user.
 * This is wrapped in a function to avoid unnecessary API calls.
 *
 * @param string $zoomuserid Zoom user ID.
 * @return ?string
 */
function zoom_get_user_display_name($zoomuserid) {
    try {
        $hostuser = zoom_get_user($zoomuserid);

        // Prefer the profile display name — the field Zoom actually shows.
        if (!empty($hostuser->display_name)) {
            return $hostuser->display_name;
        }

        // Compose Moodle user object for host.
        $hostmoodleuser = new stdClass();
        $hostmoodleuser->firstname = $hostuser->first_name;
        $hostmoodleuser->lastname = $hostuser->last_name;
        $hostmoodleuser->alternatename = '';
        $hostmoodleuser->firstnamephonetic = '';
        $hostmoodleuser->lastnamephonetic = '';
        $hostmoodleuser->middlename = '';

        return fullname($hostmoodleuser);
    } catch (moodle_exception $error) {
        return null;
    }
}
