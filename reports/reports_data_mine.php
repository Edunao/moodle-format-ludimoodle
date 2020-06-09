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
 * Quiz report data mine class
 *
 * @package    course
 * @subpackage format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     celine <celine@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class reports_data_mine {
    private $db;

    public function __construct() {
        global $DB;
        $this->db = $DB;
    }

    /**
     * Return cohorts
     * @param $courseid
     * @return array
     * @throws dml_exception
     */
    public function get_cohorts($courseid) {
        $context = \context_course::instance($courseid);

        return $this->db->get_records_sql('
            SELECT DISTINCT c.id, c.name
            FROM {cohort} as c
            JOIN {cohort_members} as cm ON c.id = cm.cohortid
            JOIN {role_assignments} ra ON cm.id = ra.userid
            JOIN {role} r ON ra.roleid = r.id
            WHERE ra.contextid = ?
            AND r.shortname = ?
            AND c.name LIKE "%classe%"
        ', array($context->id, 'student'));
    }

    /**
     * Return course sections
     * @param $courseid
     * @return array
     * @throws dml_exception
     */
    public function get_course_sections($courseid) {
        return $this->db->get_records_sql('
            SELECT * 
            FROM {course_sections}
            WHERE course = ?
            ORDER BY section ASC
        ', array($courseid));
    }

    /**
     * Return user with role student in course with firstname and lastname
     * @param $courseid
     * @return array
     * @throws dml_exception
     */
    public function get_course_students($courseid, $cohortid = null) {
        $context = \context_course::instance($courseid);
        if ($cohortid != null && $cohortid > 0) {
            $sql = '
                SELECT DISTINCT u.id, u.firstname, u.lastname
                FROM {cohort} as c
                JOIN {cohort_members} as cm ON c.id = cm.cohortid
                JOIN {user} as u ON cm.userid = u.id
                JOIN {role_assignments} ra ON u.id = ra.userid
                JOIN {role} r ON ra.roleid = r.id
                WHERE ra.contextid = ?
                  AND r.shortname = ?
                  AND cm.cohortid = ?
            ';
            $params = [$context->id, 'student', $cohortid];
        } else {
            $sql = '
                SELECT DISTINCT u.id, u.firstname, u.lastname
                FROM {user} u
                JOIN {role_assignments} ra ON u.id = ra.userid
                JOIN {role} r ON ra.roleid = r.id
                WHERE ra.contextid = ?
                  AND r.shortname = ?
            ';
            $params = [$context->id, 'student'];
        }
        return $this->db->get_records_sql($sql, $params);
    }

    /**
     * Return quiz on given section in right order
     * @param $section
     * @return array
     * @throws dml_exception
     */
    function get_section_quiz_ordered($section) {

        $cms = $this->db->get_records_sql('
        SELECT cm.id as cmid, q.id, q.name, cm.section 
        FROM {course_modules} cm
        JOIN {course_sections} cs ON cm.section = cs.id
        JOIN {modules} m ON cm.module = m.id
        JOIN {quiz} q ON q.id = cm.instance
        WHERE cm.section = ? 
        AND m.name = ?
    ', array($section->id, 'quiz'));

        $orderedcms = array();
        $ordercmsid = explode(',', $section->sequence);
        foreach ($ordercmsid as $cmid) {
            if (array_key_exists($cmid, $cms)) {
                $orderedcms[$cms[$cmid]->id] = $cms[$cmid];
            }
        }

        return $orderedcms;
    }

    /**
     * Get best fraction value between all attempts for each user and each question
     * Use ludigrade
     * @param $cmid
     * @param int $since
     * @return array [userid => questionid => step]
     * @throws dml_exception
     */
    public function get_better_quiz_steps($cmid, $since = 0) {

        $context = \context_module::instance($cmid);

        $steps = $this->db->get_records_sql('
            SELECT qas.*, qa.questionid, qa.maxfraction, qa.maxmark
            FROM {question_usages} qu
            JOIN {question_attempts} qa ON  qu.id = questionusageid
            JOIN {question_attempt_steps} qas ON  qa.id = qas.questionattemptid
            WHERE qu.contextid = ? AND qu.component = ?
            AND qas.timecreated >= ?
            ORDER BY qas.timecreated ASC
        ', array($context->id, 'mod_quiz', $since));

        if(!$steps){
            return array();
        }

        // Get max ludigrade and transform the value to fraction
        $stepsid = implode(',', array_keys($steps));

        $ludistepsdata = $this->db->get_records_sql('
            SELECT attemptstepid, MAX(value) as value
            FROM {question_attempt_step_data} qasd
            WHERE qasd.attemptstepid IN (' . $stepsid . ')
            AND name = "-ludigrade"
            GROUP BY attemptstepid
        ');

        foreach ($ludistepsdata as $stepdata) {
            if (array_key_exists($stepdata->attemptstepid, $steps)) {
                $ludigrade = $stepdata->value;
                $step = $steps[$stepdata->attemptstepid];
                $steps[$stepdata->attemptstepid]->fraction = $step->maxfraction * $ludigrade / $step->maxmark;
            }
        }

        // Get better grade for each question and each user
        $usersteps = array();
        foreach ($steps as $step) {
            $userid     = $step->userid;
            $questionid = $step->questionid;

            if (isset($usersteps[$userid])) {
                if (!isset($usersteps[$userid][$questionid])) {
                    $usersteps[$userid][$questionid] = $step;
                } else if ($usersteps[$userid][$questionid]->fraction <= $step->fraction) {
                    $usersteps[$userid][$questionid] = $step;
                }
            } else {
                $usersteps[$userid][$questionid] = $step;
            }
        }

        return $usersteps;
    }

    /**
     * @param $cmids
     * @param $since
     * @return array
     * @throws dml_exception
     */
    public function get_last_attempts_update($cmids, $since) {

        $contexts = array();
        foreach ($cmids as $cmid) {
            $contexts[] = \context_module::instance($cmid)->id;
        }

        $contexts = implode(',', $contexts);

        $steps = $this->db->get_records_sql('
            SELECT qas.*, qa.questionid, qa.questionusageid, qu.contextid, qa.maxfraction, qa.maxmark
            FROM {question_usages} qu
            JOIN {question_attempts} qa ON  qu.id = qa.questionusageid
            JOIN {question_attempt_steps} qas ON  qa.id = qas.questionattemptid 
            WHERE qu.contextid IN (' . $contexts . ') AND component = ?
            AND qas.timecreated > ? 
        ', array('mod_quiz', $since));

        if (!$steps) {
            return array();
        }

        $stepsid = implode(',', array_keys($steps));

        $ludistepsdata = $this->db->get_records_sql('
            SELECT *
            FROM {question_attempt_step_data} qasd
            WHERE qasd.attemptstepid IN (' . $stepsid . ')
            AND name = "-ludigrade"
        ');

        foreach ($ludistepsdata as $stepdata) {
            if (array_key_exists($stepdata->attemptstepid, $steps)) {
                $steps[$stepdata->attemptstepid]->ludigrade = $stepdata->value;
            }
        }

        return $steps;
    }

    /**
     *
     * @param $quizid
     * @return array
     * @throws dml_exception
     */
    public function get_quiz_questions($quizid) {
        return $this->db->get_records_sql('
            SELECT q.id, q.name, q.defaultmark
            FROM {quiz_slots} qs
            JOIN {question} q ON qs.questionid = q.id
            WHERE qs.quizid = ?
            ORDER BY qs.slot ASC
        ', array($quizid));

    }

}