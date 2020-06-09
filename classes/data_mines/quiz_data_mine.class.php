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
 * Data mine for quiz mod
 *
 * @package    course
 * @subpackage format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     celine <celine@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ludimoodle;
defined('MOODLE_INTERNAL') || die();

class quiz_data_mine {

    // cache the set of question attempts that have had their grades updated just now
    private $fixedupgrades;
    private $rawludigrades = [];

    /**
     * Return an array of grades object indexed by attemptid
     *
     * @param $quizid
     * @param $userid
     * @return array
     */
    public function fetch_grades($quizid, $userid) {

        $attempts = $this->fetch_attempts($quizid, $userid);
        $grades   = [];

        foreach ($attempts as $attempt) {
            // Attempt is finished, we can use it real grade
            if ($attempt->state != 'inprogress') {
                $grades[$attempt->id] = (object) [
                        'grade' => $attempt->grade, 'grademax' => $attempt->grademax, 'gradepass' => $attempt->gradepass,
                        'state' => $attempt->state
                ];
                continue;
            }

            // Attempt is in progress - use ludigrades
            if ($attempt->state == 'inprogress') {
                $questionsgrade = $this->fetch_raw_ludigrades_for_quiz($attempt->id);
                $partialgrade   = 0;

                foreach ($questionsgrade as $questiongrade) {
                    $partialgrade += $questiongrade->grade;
                }
                $grades[$attempt->id] = (object) [
                        'grade' => $partialgrade, 'grademax' => $attempt->grademax, 'gradepass' => $attempt->gradepass,
                        'state' => $attempt->state
                ];
                continue;
            }
        }

        return $grades;
    }

    /**
     * Return an array of attempt answers object with questionid, grade, maxgrade
     *
     * @param $attemptid
     * @return array
     */
    public function fetch_attempt_answers($attemptid) {
        return $this->fetch_raw_ludigrades_for_quiz($attemptid);
    }

    /**
     * Return quiz attempts record + info
     *
     * @param $attemptid
     * @return object|false
     * @throws \dml_exception
     */
    public function fetch_attempt_info($attemptid) {
        global $DB;

        $attempt = $DB->get_record('quiz_attempts', array('id' => $attemptid));

        $questionsnumber          = $DB->get_record_sql('
            SELECT count(*) as qnumber
            FROM {question_attempts} 
            WHERE questionusageid = ?
        ', array($attempt->uniqueid));
        $attempt->questionsnumber = isset($questionsnumber->qnumber) ? $questionsnumber->qnumber : 0;

        return $attempt;
    }

    /**
     * Return quiz attempts for a user
     *
     * @param $quizid
     * @param $userid
     * @return array
     * @throws \dml_exception
     */
    private function fetch_attempts($quizid, $userid) {
        global $DB;

        $query
                = '
            SELECT qa.id, qa.attempt, q.sumgrades AS grademax, qa.sumgrades AS grade, qa.state, qa.timestart, qa.timemodified, qa.timefinish, gi.gradepass
            FROM {quiz} q 
            LEFT JOIN {quiz_attempts} qa ON qa.quiz = q.id AND qa.userid = :userid
            LEFT JOIN {grade_items} gi ON q.id = gi.iteminstance AND itemmodule="quiz"
            WHERE q.id = :quizid
        ';

        $attempts = $DB->get_records_sql($query, ['userid' => $userid, 'quizid' => $quizid]);

        return $attempts;
    }



    //---------------------------------------------------------------------------------------------
    // Times

    //TODO implement this function from acceleration indicator motivator
    public function fetch_question_time($questionid, $userid) {

    }

    //TODO implement this function from acceleration indicator motivator
    public function fetch_quiz_times($quizid, $userid) {
        global $DB;

        $attempts = $this->fetch_attempts($quizid, $userid);

        $questionstimes = [];
        foreach ($attempts as $attemptid => $attempt) {
            $steps = $DB->get_records_sql('
                SELECT qas.*, qa.id as attemptid, qua.id as questionattemptid
                FROM {quiz_attempts} qa
                JOIN {question_attempts} qua ON qa.uniqueid = qua.questionusageid
                JOIN {question_attempt_steps} qas ON qua.id = qas.questionattemptid
                WHERE qa.id = :quizattemptid AND qa.userid = :userid
                AND qua.questionid = :questionid
            ', array('quizattemptid' => $attemptid, 'userid' => $userid));
        }
    }

    //TODO implement this function from acceleration indicator motivator
    private function fetch_question_times($attemptid, $questionid) {

    }


    //---------------------------------------------------------------------------------------------
    // Ludigrades

    /**
     * @param $quizattempt
     * @return array of ludigrades std object with questionid, grade, maxgrade
     * @throws \dml_exception
     */
    private function fetch_raw_ludigrades_for_quiz($quizattempt) {
        // make sure the fetch is only run the once - this is a big query so not to be run more often than necessary
        $quizkey = 'Q:' . $quizattempt;
        if (array_key_exists($quizkey, $this->rawludigrades)) {
            return $this->rawludigrades[$quizkey];
        }

        // make sure that grade table has been fixed up as required
        $this->fixup_ludigrades_for_quiz($quizattempt);

        // fetch the appropriate grade data from sql
        global $DB;
        $query = '
            SELECT qa.questionid as questionid, max(qas.fraction) AS fraction, max(qa.maxmark) as maxgrade, max(qasd.value) as ludigrade, qas.state
            FROM {quiz_attempts} za
            JOIN {question_attempts} qa ON qa.questionusageid=za.uniqueid
            JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id AND qas.state IN ("complete", "gaveup", "gradedwrong", "gradedright", "gradedpartial")
            LEFT JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid = qas.id AND qasd.name = "-ludigrade"
            WHERE za.id=:quizattempt
            GROUP BY qa.id
        ';
        $sqlresult = $DB->get_records_sql($query, ['quizattempt' => $quizattempt]);

        $this->rawludigrades[$quizkey] = $this->consolidate_raw_ludi_grades($sqlresult);

        // return the result
        return $this->rawludigrades[$quizkey];
    }

    /**
     * Identify attempts who don't have ludigrades and fix it
     *
     * @param $quizattempt
     * @throws \dml_exception
     */
    private function fixup_ludigrades_for_quiz($quizattempt) {
        // put in a database request for the set of attempt step data fields for completed questions that have yet to be ludigraded
        global $DB;
        $query
                   = '
            SELECT qasd.id, l.questionid, l.maxgrade, l.attemptstepid, qas.questionattemptid, qasd.name, qasd.value
            FROM (
                SELECT qa.id, qa.questionid, qa.maxmark as maxgrade, qas.id as attemptstepid
                FROM {quiz_attempts} za
                JOIN {question_attempts} qa ON qa.questionusageid=za.uniqueid
                JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id AND qas.state IN("complete")
                LEFT JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid=qas.id AND qasd.name="-ludigrade"
                WHERE za.id=:quizattempt
                AND qasd.id is null
                GROUP BY qa.id
            ) l
            JOIN {question_attempt_steps} qas ON qas.questionattemptid = l.id
            JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid=qas.id
            ORDER BY qas.id
        ';
        $sqlresult = $DB->get_records_sql($query, ['quizattempt' => $quizattempt]);

        // calculate the grades for any questions identified as requiring recalculation
        $this->evaluate_ludigrades($sqlresult);
    }

    /**
     * Calculate the grades for any questions identified as requiring recalculation
     * Insert grades in DB
     *
     * @param $questionstograde
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private function evaluate_ludigrades($questionstograde) {
        global $DB;

        // organise the data records by question attempt
        $attemptdata    = [];
        $attemptrecords = [];
        foreach ($questionstograde as $record) {
            // ignore non-question-type data
            if ($record->name[0] === ":" || $record->name[0] === "-") {
                continue;
            }
            // store the field away as required
            $attempt = $record->attemptstepid;
            if (!array_key_exists($attempt, $attemptdata)) {
                $attemptdata[$attempt]    = [$record->name => $record->value];
                $attemptrecords[$attempt] = $record;
            } else {
                $attemptdata[$attempt][$record->name] = $record->value;
            }
        }

        // iterate over the problem attempts, grading them and preparing new records for DB application
        $newrecords = [];
        foreach ($attemptdata as $attemptid => $qtdata) {
            $questionid = $attemptrecords[$attemptid]->questionid;
            $maxgrade   = $attemptrecords[$attemptid]->maxgrade;

            // calculate the score for the question
            $question    = \question_bank::load_question($questionid);
            $attemptstep = new \question_attempt_step($qtdata);
            $question->apply_attempt_state($attemptstep);
            list($gradefraction, $gradestate) = $question->grade_response($qtdata);
            $newgrade = $gradefraction * $maxgrade;
            // queue a record for batched writing to database
            $newrecords[] = (object) [
                    'attemptstepid' => $attemptid, 'name' => '-ludigrade', 'value' => $newgrade,
            ];

            // cache the set of question attempts that have had their grades updated just now
            $this->fixedupgrades[$attemptid] = $newgrade;
        }

        // write any calculated scores back to the database
        if ($newrecords) {
            $DB->insert_records('question_attempt_step_data', $newrecords);
        }
    }

    /**
     * Prepare sql return
     *
     * @param $sqlresult
     * @return array
     */
    private function consolidate_raw_ludi_grades($sqlresult) {
        $result = [];
        foreach ($sqlresult as $questionid => $record) {
            if ($record->fraction !== null) {
                $grade = $record->fraction * $record->maxgrade;
            } else if ($record->ludigrade !== null) {
                $grade = $record->ludigrade;
            } else if ($record->state == 'gaveup') {
                $grade = 0;
            } else {
                continue;
            }
            $result[] = (object) [
                    "questionid" => $record->questionid, "grade" => $grade, "maxgrade" => $record->maxgrade
            ];
        }
        return $result;
    }

    //==============================================================================
    //==============================================================================
    //====================EXPERIMENTATION===========================================
    //==============================================================================

    /**
     * Return ludigrades for a user on a course section
     *
     * @param $userid
     * @param $courseid
     * @param $sectionid
     * @return mixed
     * @throws \dml_exception
     */
    public function fetch_raw_ludigrades_for_section($userid, $courseid, $sectionid) {
        // make sure the fetch is only run the once - this is a big query so not to be run more often thn necessary
        $sectionkey = $courseid . '#' . $sectionid;
        if (array_key_exists($sectionkey, $this->rawludigrades)) {
            return $this->rawludigrades[$sectionkey];
        }

        // make sure that grade table has been fixed up as required
        $this->fixup_ludigrades_for_section($userid, $courseid, $sectionid);

        // fetch the appropriate grade data from sql
        // attention on utilise un max sur le grade mais ca peut prendre le state gradedwrong car ca prend toujours le premier state avec le max fraction
        global $DB;
        $query = '
            SELECT qa.questionid as questionid, qa.id as attemptid, qa.questionusageid, max(qas.fraction) AS fraction, max(qa.maxmark) as maxgrade, max(qasd.value) as ludigrade, qas.state
            FROM {course} c
            JOIN {course_modules} cm                    ON cm.course = c.id
            JOIN {course_sections} cs                   ON cs.id = cm.section
            JOIN {modules} m                            ON cm.module=m.id
            JOIN {context} ctxt                         ON ctxt.instanceid = cm.id AND ctxt.contextlevel = :contextlevel
            JOIN {question_usages} qu                   ON qu.contextid = ctxt.id
            JOIN {question_attempts} qa                 ON qa.questionusageid = qu.id
            JOIN {question_attempt_steps} qas           ON qas.questionattemptid = qa.id AND qas.userid = :userid AND qas.state IN("complete", "gaveup", "gradedright","gradedwrong", "gradedpartial")
            LEFT JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid = qas.id AND qasd.name = "-ludigrade"
            WHERE c.id   =:courseid
              AND cs.id  =:sectionid
              AND m.name ="quiz"
            GROUP BY qa.questionid
            ORDER BY qas.timecreated ASC
        ';

        $sqlresult = $DB->get_records_sql($query, [
                'contextlevel' => CONTEXT_MODULE, 'userid' => $userid, 'courseid' => $courseid, 'sectionid' => $sectionid
        ]);

        $this->rawludigrades[$sectionkey] = $this->consolidate_raw_ludi_grades($sqlresult);
        // return the result
        return $this->rawludigrades[$sectionkey];
    }

    /**
     * Fix up ludigrades for a user on a course section
     *
     * @param $userid
     * @param $courseid
     * @param $sectionid
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private function fixup_ludigrades_for_section($userid, $courseid, $sectionid) {

        // put in a database request for the set of attempt step data fields for completed questions that have yet to be ludigraded
        global $DB;
        $query
                   = '
            SELECT qasd.id, l.coursename, l.questionid, l.maxgrade, l.attemptstepid, qas.questionattemptid, qasd.name, qasd.value
            FROM (
                SELECT qa.id, c.shortname as coursename, qa.questionid, qa.maxmark as maxgrade, qas.id as attemptstepid
                FROM {course} c
                JOIN {course_modules} cm ON cm.course = c.id
                JOIN {course_sections} cs ON cs.id = cm.section
                JOIN {modules} m ON cm.module=m.id
                JOIN {context} ctxt ON ctxt.instanceid = cm.id AND ctxt.contextlevel=70
                JOIN {question_usages} qu ON qu.contextid = ctxt.id
                JOIN {question_attempts} qa ON qa.questionusageid = qu.id
                JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id AND qas.state IN("complete")
                LEFT JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid=qas.id AND qasd.name="-ludigrade"
                WHERE c.id=:courseid
                AND cs.id=:sectionid
                AND m.name="quiz"
                AND qas.userid = :userid
                AND qasd.id is null
                GROUP BY qa.id
            ) l
            JOIN {question_attempt_steps} qas ON qas.questionattemptid = l.id
            JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid=qas.id
            ORDER BY qas.id
        ';
        $sqlresult = $DB->get_records_sql($query, ['userid' => $userid, 'courseid' => $courseid, 'sectionid' => $sectionid]);

        // calculate the grades for any questions identified as requiring recalculation
        $this->evaluate_ludigrades($sqlresult);
    }
}