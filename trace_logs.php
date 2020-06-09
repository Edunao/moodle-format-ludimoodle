<?php
// This file is part of Moodle - http://moodle.org/
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
 * @copyright  2017 Edunao SAS (contact@edunao.com)
 * @author     Adrien JAMOT (adrien@edunao.com)
 * @package    format_ludimoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config.php';

$execute = optional_param('execute', false, PARAM_BOOL);

function format_ludimoodle_generate_traces($returnfile = false) {
    global $DB, $CFG;
    //----------- motivator achievements
    $cmidtosectionid = [];
    function explode_achievement($record, &$cmidtosectionid) {
        global $DB;

        preg_match('%^(\D):(\d{1,})[#-:]?(\d*)?:(.*)$%', $record->eventcode, $parts);

        $sectionid = 'all';
        $cmid      = null;
        $context   = $parts[1];
        $courseid  = $parts[2];

        if ($context == 'M') {
            $cmid = $parts[3];

            if (!isset($cmidtosectionid[$cmid])) {
                $cmidtosectionid[$cmid] = $DB->get_field('course_modules', 'section', ['id' => $cmid]);
            }
            $sectionid = $cmidtosectionid[$cmid];

        } else if ($context == 'S') {
            $sectionid = $parts[3];
            $cmid      = null;
        }

        $achievementname = $parts[4];

        return [
                'courseid' => $courseid, 'sectionid' => $sectionid, 'cmid' => $cmid, 'achievement' => $achievementname
        ];
    }

    // Motivatorkey to motivatorname
    $motivatormatch = [
            '7021786285255910241' => 'acceleration_indicator', '8319104478719472240' => 'progression',
            '7311146993654195570' => 'relativeprogression', '125762889938529' => 'avatar', '435711599475' => 'score',
            '126879363522914'     => 'badges', '7022916617737432942' => 'nomotivator', '1701736302' => 'none'
    ];

    // Only motivators to add in traces
    $motivatorstracked = [
            'acceleration_indicator', 'progression', 'relativeprogression', 'avatar', 'score', 'badges'
    ];

    // Achievements displayed as event
    $achievementstoaction = [
            'game_element_info_text_show', 'game_element_info_text_dismiss', 'avatar_inventory_open', 'avatar_inventory_close',
            'avatar_object_equip', 'avatar_object_remove', 'course-view', 'section-view', 'mod-view'
    ];

    //----------------------------------------
    //----------------------------------------
    //----------------------------------------

    // Fetch motivators for each user and section
    $usersmotivators = [];

    $motivatorachievements = $DB->get_records_sql('
    SELECT a.id, a.timestamp, u.username, a.achievement as eventcode, a.value
    FROM {ludimoodle_achievements} a
    JOIN {user} u ON u.id = a.userid
    WHERE a.achievement LIKE "%motivator"
    ORDER BY a.timestamp ASC
');

    foreach ($motivatorachievements as $achievement) {

        $username  = $achievement->username;
        $data      = explode_achievement($achievement, $cmidtosectionid);
        $sectionid = $data['sectionid'];

        if (!isset($usersmotivators[$username])) {
            $usersmotivators[$username] = [];
        }

        if (!isset($usersmotivators[$username][$sectionid])) {
            $motivatorname                          = $motivatormatch[$achievement->value];
            $usersmotivators[$username][$sectionid] = $motivatorname;

            if ($motivatorname == 'avatar') {
                $usersmotivators[$username]['all'] = $motivatorname;
            }
        }

    }

    // setup wish lists of log fields to fetch
    $logsu = [
            "login" => ["core", "loggedin", "user"], "dashboard" => ["core", "viewed", "dashboard"],
    ];

    $logsuc = [
            "course_pageview" => ["core", "viewed", "course"], "quiz_start" => ["mod_quiz", "started", "attempt"],
            "quiz_moduleview" => ["mod_quiz", "viewed", "course_module"],
    ];

    $logsucq = [
            "quiz_review"   => ["mod_quiz", "reviewed", "attempt"], "quiz_submit" => ["mod_quiz", "submitted", "attempt"],
            "quiz_pageview" => ["mod_quiz", "viewed", "attempt"], "quiz_summaryview" => ["mod_quiz", "viewed", "attempt_summary"],
    ];

    // initialise the logs by time container for holding the results
    $logsbytime = [];

    // Fetch logs that are only user-related
    foreach ($logsu as $eventname => $logtype) {
        $params     = [
                "component" => $logtype[0], "action" => $logtype[1], "target" => $logtype[2],
        ];
        $query      = '
        SELECT l.id, l.courseid, u.username, l.timecreated, l.other, l.objecttable, l.objectid
        FROM {logstore_standard_log} l
        JOIN {user} u ON l.userid = u.id
        WHERE component=:component
        AND action=:action
        AND target=:target
        AND u.id > 2
    ';
        $logrecords = $DB->get_records_sql($query, $params);
        foreach ($logrecords as $record) {
            $time   = $record->timecreated;
            $other  = ($record->other != 'N;') ? unserialize($record->other) : [];
            $newlog = (object) $other;
            if (!empty($objtable)) {
                $newlog->$objtable = $record->objectid;
            }
            $newlog->event = $eventname;
            $newlog->user  = $record->username;
            $objtable      = $record->objecttable;
            if (array_key_exists($time, $logsbytime)) {
                $logsbytime[$time][] = $newlog;
            } else {
                $logsbytime[$time] = [$newlog];
            }
        }
    }

    // Fetch logs that are user and course related
    foreach ($logsuc as $eventname => $logtype) {
        $params = [
                "component" => $logtype[0], "action" => $logtype[1], "target" => $logtype[2],
        ];

        // in mod_quiz log you can select sectionid
        if (strpos($logtype[0], '_quiz')) {
            $query = '
            SELECT l.id, l.courseid, u.username, c.shortname as coursename, cs.id as sectionid, l.timecreated, l.other, l.objecttable, l.objectid
            FROM {logstore_standard_log} l
            JOIN {user} u ON l.userid = u.id
            JOIN {course} c ON l.courseid = c.id
            JOIN {course_modules} cm ON l.contextinstanceid = cm.id
            JOIN {course_sections} cs ON cm.section = cs.id
            WHERE component=:component
            AND action=:action
            AND target=:target
            AND u.id > 2
    ';
        } else {
            $query = '
            SELECT l.id, l.courseid, u.username, c.shortname as coursename, l.timecreated, l.other, l.objecttable, l.objectid
            FROM {logstore_standard_log} l
            JOIN {user} u ON l.userid = u.id
            JOIN {course} c ON l.courseid = c.id
            WHERE component=:component
            AND action=:action
            AND target=:target
            AND u.id > 2
    ';
        }

        $logrecords = $DB->get_records_sql($query, $params);
        foreach ($logrecords as $record) {
            $time   = $record->timecreated;
            $other  = ($record->other != 'N;') ? unserialize($record->other) : [];
            $newlog = (object) $other;
            if ($objtable) {
                $newlog->$objtable = $record->objectid;
            }
            $newlog->event  = $eventname;
            $newlog->user   = $record->username;
            $newlog->course = $record->coursename;
            if (isset($record->sectionid)) {
                $newlog->sectionid = $record->sectionid;
            }
            $objtable = $record->objecttable;
            if (array_key_exists($time, $logsbytime)) {
                $logsbytime[$time][] = $newlog;
            } else {
                $logsbytime[$time] = [$newlog];
            }
        }
    }

    // Fetch logs that are user, course and question related
    foreach ($logsucq as $eventname => $logtype) {
        $params = [
                "component" => $logtype[0], "action" => $logtype[1], "target" => $logtype[2],
        ];
        $query  = '
        SELECT DISTINCT l.id, l.courseid, u.username, c.shortname as coursename, cs.id as sectionid, l.timecreated, l.other, l.objecttable, l.objectid
        FROM {logstore_standard_log} l
        JOIN {user} u ON l.userid = u.id
        JOIN {course} c ON l.courseid = c.id
        JOIN {course_modules} cm ON l.contextinstanceid = cm.id
        JOIN {course_sections} cs ON cm.section = cs.id
        WHERE component=:component
        AND action=:action
        AND target=:target
        AND u.id > 2
    ';

        $logrecords = $DB->get_records_sql($query, $params);

        // extract the quiz identifiers
        $quizids = [];
        foreach ($logrecords as $record) {
            $other            = unserialize($record->other);
            $quizid           = $other['quizid'];
            $record->quizid   = $quizid;
            $quizids[$quizid] = $quizid;
            unset($other['quizid']);
        }

        if (count($quizids) > 0) {

            // lookup the quiz records from the database
            $query       = '
                SELECT DISTINCT q.id, q.name, cs.section, cs.id as sectionid
                FROM {quiz} q
                JOIN {course_modules} cm ON cm.instance = q.id
                JOIN {modules} m ON cm.module = m.id
                JOIN {course_sections} cs ON cm.section = cs.id
                WHERE m.name = "quiz"
                AND q.id in (' . join(',', $quizids) . ')
            ';
            $quizrecords = $DB->get_records_sql($query);

            // combine the logs and quiz info into result records
            foreach ($logrecords as $record) {
                $time   = $record->timecreated;
                $quizid = $record->quizid;
                $quiz   = $quizrecords[$quizid];
                $newlog = (object) ([
                        "event"   => $eventname, "user" => $record->username, "course" => $record->coursename,
                        "section" => $quiz->section, "sectionid" => $quiz->sectionid, "quizid" => $quizid, "quiz" => $quiz->name,
                ]);
                //        ] + $other);
                $objtable = $record->objecttable;
                if ($objtable) {
                    $newlog->$objtable = $record->objectid;
                }
                if (array_key_exists($time, $logsbytime)) {
                    $logsbytime[$time][] = $newlog;
                } else {
                    $logsbytime[$time] = [$newlog];
                }
            }
        }
    }

    // mine the mdl_ludimoodle_achievements table
    $achievementrecords = $DB->get_records_sql('
    SELECT a.id, a.timestamp, u.username, a.achievement as eventcode, a.value
    FROM {ludimoodle_achievements} a
    JOIN {user} u ON u.id = a.userid
    WHERE a.achievement NOT LIKE "%motivator" 
      AND a.achievement NOT LIKE "%step" 
      AND a.achievement NOT LIKE "%useditem-%"
    ORDER BY a.timestamp ASC
');

    foreach ($achievementrecords as $record) {

        // Data from eventcode
        $data            = explode_achievement($record, $cmidtosectionid);
        $courseid        = $data['courseid'];
        $sectionid       = $data['sectionid'];
        $cmid            = $data['cmid'];
        $achievementname = $data['achievement'];

        // Data from record
        $username  = $record->username;
        $time      = $record->timestamp;
        $eventcode = $record->eventcode;
        $value     = $record->value;

        // If motivator was not found for a section, continue - don't log;
        if (!isset($usersmotivators[$username][$sectionid])) {
            continue;
        }

        // User motivator in section
        $motivator = $usersmotivators[$username][$sectionid];

        // ignore data from none and nomotivator
        if (!in_array($motivator, $motivatorstracked)) {
            continue;
        }

        // remove advancement log for motivators in array
        if (in_array($motivator, ['acceleration_indicator', 'avatar', 'badges', 'relativeprogression']) &&
            $achievementname == 'advancement') {
            continue;
        }

        // for badges motivator keep only achievement when the user has gained a new badge
        if ($motivator == 'badges' && !strpos($achievementname, 'level') && !in_array($achievementname, $achievementstoaction)) {
            continue;
        }

        $newlog = (object) [
                "event" => $motivator . '_update', "user" => $username, "course" => $courseid, "sectionid" => $sectionid,
                "cmid"  => $cmid, "property" => $achievementname, "value" => $value
        ];

        // some achievements are wanted like main action
        if (isset($newlog->property) && in_array($newlog->property, $achievementstoaction)) {
            $newlog->event = $newlog->property;
            unset($newlog->property);
        }

        if (array_key_exists($time, $logsbytime)) {
            $logsbytime[$time][] = $newlog;
        } else {
            $logsbytime[$time] = [$newlog];
        }
    }

    // fetch quiz results
    $query              = '
    SELECT DISTINCT qa.id, q.name as quizname, q.id as quizid, u.username, c.shortname as coursename, cs.section, cs.id as sectionid, qa.attempt, qa.timestart, qa.timefinish, qa.timemodified
    FROM {quiz_attempts} qa
    JOIN {user} u ON u.id = qa.userid
    JOIN {quiz} q ON q.id = qa.quiz
    JOIN {course} c ON c.id = q.course
    JOIN {course_modules} cm ON cm.instance = q.id
    JOIN {modules} m ON cm.module = m.id
    JOIN {course_sections} cs ON cm.section = cs.id
    WHERE u.id > 2
    AND m.name = "quiz"
';
    $quizattemptrecords = $DB->get_records_sql($query);
    foreach ($quizattemptrecords as $record) {
        // extract key event elements
        $newlog            = new \StdClass;
        $newlog->user      = $record->username;
        $newlog->course    = $record->coursename;
        $newlog->section   = $record->section;
        $newlog->sectionid = $record->sectionid;
        $newlog->attempt   = $record->attempt;
        $newlog->quiz      = $record->quizname;
        $newlog->quizid    = $record->quizname;

        // start with the start of quiz event
        $time          = $record->timestart;
        $newlog->event = "quiz_attempt_started";
        if (array_key_exists($time, $logsbytime)) {
            $logsbytime[$time][] = clone $newlog;
        } else {
            $logsbytime[$time] = [clone $newlog];
        }

        if ($record->timefinish > $record->timestart) {
            // add and 'end' log
            $time          = $record->timefinish;
            $newlog->event = "quiz_attempt_finished";
            if (array_key_exists($time, $logsbytime)) {
                $logsbytime[$time][] = clone $newlog;
            } else {
                $logsbytime[$time] = [clone $newlog];
            }
        } else {
            // add an 'abandonned' log
            $time          = $record->timemodified;
            $newlog->event = "quiz_attempt_unfinished";
            if (array_key_exists($time, $logsbytime)) {
                $logsbytime[$time][] = clone $newlog;
            } else {
                $logsbytime[$time] = [clone $newlog];
            }
        }
    }

    $query                  = '
    SELECT DISTINCT qas.id, q.name as quizname, u.username, c.shortname as coursename, cs.section, cs.id as sectionid, qas.timecreated, qas.state, qa.attempt, GREATEST(qa.timefinish, qa.timemodified) as endtime, a.slot, qas.sequencenumber , qas.state
    FROM {quiz_attempts} qa
    JOIN {user} u ON u.id = qa.userid
    JOIN {quiz} q ON q.id = qa.quiz
    JOIN {course} c ON c.id = q.course
    JOIN {course_modules} cm ON cm.instance = q.id
    JOIN {modules} m ON cm.module = m.id
    JOIN {course_sections} cs ON cm.section = cs.id
    JOIN {question_usages} qu ON qu.id=qa.uniqueid
    JOIN {question_attempts} a ON a.questionusageid=qu.id
    JOIN {question_attempt_steps} qas ON questionattemptid=a.id
    WHERE u.id > 2
    AND m.name = "quiz"
    AND qas.state != "todo"
    AND sequencenumber > 0
';
    $questionattemptrecords = $DB->get_records_sql($query);
    foreach ($questionattemptrecords as $record) {
        // extract key event elements
        $time              = $record->timecreated;
        $newlog            = new \StdClass;
        $newlog->event     = "question_" . $record->state;
        $newlog->user      = $record->username;
        $newlog->course    = $record->coursename;
        $newlog->section   = $record->section;
        $newlog->sectionid = $record->sectionid;
        $newlog->attempt   = $record->attempt;
        $newlog->quiz      = $record->quizname;
        $newlog->state     = $record->state;
        $newlog->question  = $record->slot;
        $newlog->sequence  = $record->sequencenumber;

        // start with the start of quiz event
        if (array_key_exists($time, $logsbytime)) {
            $logsbytime[$time][] = clone $newlog;
        } else {
            $logsbytime[$time] = [clone $newlog];
        }
    }

    $times = array_keys($logsbytime);
    sort($times);

    // setup target path and file name
    $path    = "{$CFG->dataroot}/filedir/trace_logs/";
    $tgtFile = "$path/" . time() . ".gz";
    if (!file_exists($path)) {
        mkdir($path, 0777, true);
    }

    // generate the output file
    $outputStr = '';
    foreach ($times as $time) {

        foreach ($logsbytime[$time] as $log) {
            $motivator = '';
            $username  = $log->user;
            $action    = $log->event;
            $elements  = (array) clone($log);
            unset($elements['user']);
            unset($elements['event']);

            // Motivator by user and section
            if (isset($log->sectionid) && isset($usersmotivators[$username][$log->sectionid])) {
                $motivator = $usersmotivators[$username][$log->sectionid];
                if (!strpos($action, '-view')) {
                    unset($elements['sectionid']);
                }
            }

            $elementsStr = '';
            if ($elements) {
                ksort($elements);
                foreach ($elements as $attr => $value) {
                    $elementsStr .= $attr . ' : ' . $value . ' ; ';
                }
                $elementsStr = rtrim($elementsStr, " ; ");
            }

            //$outputStr .= "$time ; $username ; $action ; " . ($elements ? json_encode($elements) : '') . "\n";
            $outputStr .= "$time ; $username ; $motivator ; $action ; " . $elementsStr . "\n";
        }

    }

    file_put_contents('compress.zlib://' . $tgtFile, $outputStr);

    if ($returnfile) {
        // shut access to courses
        //$DB->execute('UPDATE {course_sections} SET visible=0 WHERE course>2');
        //purge_all_caches();

        // Upload to server for storage
        //$ch = curl_init('http://ludimoodle-demo.proto.edunao.com/store_logs.php');
        //$cfile = new CURLFile($tgtFile, 'application/gzip','ludimoodle_trace.txt.gz');
        //$data = array('ludilog' => $cfile);
        //curl_setopt($ch, CURLOPT_POST,1);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        //curl_exec($ch);

        // dump the output file
        header('Content-Description: File Transfer');
        header("Content-Type: application/gzip");
        header("Content-disposition: attachment; filename=\"ludimoodle_trace.log.gz\"");
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($tgtFile));
        readfile($tgtFile);
    }
}

if ($execute) {
    format_ludimoodle_generate_traces(true);
}


