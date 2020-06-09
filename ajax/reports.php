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
 * Return dynamic report data
 *
 * @package    course
 * @subpackage format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     celine <celine@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once '../../../../config.php';
require_once $CFG->dirroot . '/course/format/ludimoodle/reports/reports_data_mine.php';

$courseid = required_param('courseid', PARAM_INT);
require_login($courseid);

$action = optional_param('action', null, PARAM_TEXT);

/**
 * Update attempts states in dynamic report
 */
if($action == 'updatewipreport'){
    $context = context_course::instance($courseid);
    require_capability('format/ludimoodle:monitor', $context);
    $since = required_param('since', PARAM_INT);
    $quizs = required_param('quizzes', PARAM_TEXT);
    $quizs = json_decode($quizs);

    $cmids = array();
    foreach($quizs as $quiz){
        $cmids[] = $quiz->cmid;
    }

    $datamine = new reports_data_mine();
    $newsteps = $datamine->get_last_attempts_update($cmids, $since);
    $newsteps = array_values($newsteps);
    echo json_encode($newsteps);
}