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
 * Page for quiz report display
 *
 * @package    course
 * @subpackage format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     celine <celine@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once '../../../../config.php';

require_once $CFG->dirroot . '/course/format/ludimoodle/reports/report_renderer.php';
require_once $CFG->dirroot . '/course/format/ludimoodle/reports/reports_data_mine.php';

$courseid = required_param('id', PARAM_INT);
$cohortid = optional_param('cohortid', null, PARAM_INT);

$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);
require_capability('format/ludimoodle:monitor', $context);

$url = new moodle_url('/course/format/ludimoodle/reports/quiz_report.php', array('id' => $courseid));
$courseurl = new moodle_url('/course/view.php', array('id' => $courseid));
$PAGE->set_url($url);


$PAGE->set_context($context);
$PAGE->set_pagelayout('course');
$PAGE->set_title('Rapport des quiz');
$PAGE->set_heading(get_string('quizreport-title', 'format_ludimoodle', $course->fullname));

$datamine = new reports_data_mine();
$renderer = new report_renderer($PAGE, '', $datamine);


$PAGE->requires->strings_for_js(array(
        'icon-gradedright',
        'icon-gradedwrong',
        'icon-todo'
), 'format_ludimoodle');
$PAGE->requires->css('/course/format/ludimoodle/styles.css');

$reporttype = optional_param('report', null, PARAM_TEXT);


echo $OUTPUT->header();
echo '<div class="margin-bottom-30"><a href="'.$courseurl.'">Retour au cours</a></div>';
echo $renderer->select_cohort($course, $cohortid);

if ($cohortid !== null) {
    if(!$reporttype){
        $btnreportstr = get_string('btn-report', 'format_ludimoodle');
        echo '<h3>'.$btnreportstr.'</h3>';
        echo $renderer->get_course_report($course, $cohortid);
    } else {
        $btnreportstr = get_string('btn-activereport', 'format_ludimoodle');
        echo '<h3>'.$btnreportstr.'</h3>';
        echo $renderer->get_active_course_report($course, $cohortid);
    }
}


echo $OUTPUT->footer();