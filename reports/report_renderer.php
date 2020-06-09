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
 * Quiz report renderer class
 *
 * @package    course
 * @subpackage format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     celine <celine@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once $CFG->dirroot . '/course/format/ludimoodle/reports/reports_data_mine.php';

class report_renderer extends renderer_base {

    private $reportdatamine;
    private $students = array();
    private $allquiz  = array();

    public function __construct(moodle_page $page, $target, $reportdatamine) {
        $this->reportdatamine = $reportdatamine;
        parent::__construct($page, $target);
    }

    /**
     * Render Liris cohorts selector for experimentation
     *
     * @param $course
     * @param null $cohortid
     * @return string
     * @throws coding_exception
     */
    public function select_cohort($course, $cohortid = null) {
        $cohorts = $this->reportdatamine->get_cohorts($course->id);
        $reporttype = optional_param('report', null, PARAM_TEXT);


        if (count($cohorts) > 0) {
            $output = "<form action='' method='get' class='margin-top-30 text-center'>";
            $output .= '<input type="hidden" name="id" value="' . $course->id . '">';
            if ($reporttype != null) {
                $output .= '<input type="hidden" name="report" value="' . $reporttype . '">';
            }
            $output .= "<select name='cohortid' onchange='this.form.submit();'>";
            $output .= '<option value="">-- Choisissez une cohorte --</option>';
            $selected = $cohortid === 0 ? ' selected' : '';
            $output .= '<option value="0" '.$selected.'>Tous les utilisateurs</option>';
            foreach ($cohorts as $key => $cohort) {
                $selected = '';
                if ($cohortid != null && $cohortid == $cohort->id) {
                    $selected = ' selected';
                }
                $output .= '<option value="'.$cohort->id.'"  '.$selected.'>'. $cohort->name .'</option>';
            }
            $output .= "</select>";
            $output .= "</form>";
        } else {
            $output = "<h2 class='text-center'>Aucune cohorte trouvée</h2>";
        }

        return $output;
    }

    /**
     * Render course report
     *
     * @param $course
     * @param $cohortid
     * @return string
     * @throws coding_exception
     */
    public function get_course_report($course, $cohortid) {
        global $CFG;

        $sections        = $this->reportdatamine->get_course_sections($course->id);
        $this->students  = $this->reportdatamine->get_course_students($course->id, $cohortid);
        $activereporturl = $CFG->wwwroot . '/course/format/ludimoodle/reports/quiz_report.php?id=' . $course->id . '&report=active&cohortid=' . $cohortid;
        $btnreportstr    = get_string('btn-activereport', 'format_ludimoodle');

        $output = '<a href="' . $activereporturl . '"><button class="btn">Accéder au '.strtolower($btnreportstr).'</button></a>';
        $output .= $this->get_reports_section_tabs($sections);
        $output .= $this->get_reports_sections_content($sections);

        return $output;
    }

    /**
     * Render dynamic course report
     *
     * @param $course
     * @param $cohortid
     * @return string
     * @throws coding_exception
     */
    public function get_active_course_report($course, $cohortid) {
        global $CFG;

        $since          = strtotime('today midnight');
        $sections       = $this->reportdatamine->get_course_sections($course->id);
        $this->students = $this->reportdatamine->get_course_students($course->id, $cohortid);
        $message        = get_string('activereport-message', 'format_ludimoodle');
        $reporturl      = $CFG->wwwroot . '/course/format/ludimoodle/reports/quiz_report.php?id=' . $course->id . '&cohortid=' . $cohortid;
        $btnreportstr   = get_string('btn-report', 'format_ludimoodle');

        $output    = '<p>'.$message.'</p>';
        $output    .= '<a href="' . $reporturl . '"><button class="btn">Accéder au '.strtolower($btnreportstr).'</button></a>';
        foreach ($sections as $section) {
            $output .= $this->get_section_quiz_reports($section, true, $since);
        }

        $this->page->requires->js_call_amd('format_ludimoodle/reports', 'init', array($course->id, $this->students, $this->allquiz));

        return $output;
    }

    /**
     * Render section tabs list
     *
     * @param $sections
     * @return string
     */
    private function get_reports_section_tabs($sections) {
        $output = '';

        $output .= '<div class="nav nav-tabs sections-tabs" role="tablist">';
        foreach ($sections as $section) {
            $class = '';
            if (!$section->name) {
                $section->name = 'Section ' . $section->section;
            }
            if ($section->section == 0) {
                $class .= 'active';
            }
            $output .= '<a class="nav-item nav-link ' . $class . '" data-toggle="tab" 
                href="#section-content-' . $section->id . '" role="tab" aria-controls="nav-contact"> ' . $section->name . '</a>';
        }
        $output .= '</div>';

        return $output;
    }

    /**
     * Render section tabs content
     *
     * @param $sections
     * @return string
     */
    private function get_reports_sections_content($sections) {
        $output = '';

        $output .= '<div class="tab-content">';
        foreach ($sections as $section) {
            $class = '';
            if ($section->section == 0) {
                $class .= 'active show';
            }
            $output .= '<div id="section-content-' . $section->id . '" class="tab-pane fade ' . $class . '" role="tabpanel">';
            $output .= $this->get_section_quiz_reports($section);
            $output .= '</div>';
        }
        $output .= '</div>';

        return $output;
    }

    /**
     * Render report content
     *
     * @param $section
     * @param bool $activedata
     * @param int $since
     * @return string
     * @throws coding_exception
     */
    private function get_section_quiz_reports($section, $activedata = false, $since = 0) {
        global $CFG;

        $quizzes = $this->reportdatamine->get_section_quiz_ordered($section);
        if ($activedata) {
            $this->allquiz = array_merge($this->allquiz, $quizzes);
        }

        $output = '';

        if (!$quizzes) {
            if (!$activedata) {
                $output .= '<p>' . get_string('noquiz', 'format_ludimoodle') . '</p>';
            }

            return $output;
        }

        $output .= '<div class="section-container">';

        if ($activedata) {
            if (!$section->name) {
                $section->name = 'Section ' . $section->section;
            }
            $output .= '<h2>' . $section->name . '</h2>';
        }

        foreach ($quizzes as $quiz) {

            $quizquestions = array_values($this->reportdatamine->get_quiz_questions($quiz->id));
            if (!$quizquestions) {
                continue;
            }
            $bettersteps = $this->reportdatamine->get_better_quiz_steps($quiz->cmid, $since);

            $context = \context_module::instance($quiz->cmid);

            $quizurl  = $CFG->wwwroot . '/mod/quiz/view.php?id=' . $quiz->cmid;
            $output  .= '<div class="report-quiz-container">';
            $output  .= '<h4><a href="' . $quizurl . '">' . $quiz->name . '</a></h4>';

            if (!$bettersteps && !$activedata) {
                $output .= '<p>' . get_string('noattempt', 'format_ludimoodle') . '</p>';
                continue;
            }

            $output .= '<table class="report-quiz table table-striped table-hover" 
            data-quizid="' . $quiz->id . '" data-cmid="' . $quiz->cmid . '" data-contextid="' . $context->id . '"">';

            // Header
            $output .= '<thead><tr>';

            // Student
            $output .= '<th>' . get_string('header-student', 'format_ludimoodle') . '</th>';

            // Questions
            foreach ($quizquestions as $order => $question) {
                $smallquestionname = get_string('header-questionnumber', 'format_ludimoodle', $order + 1);
                $output            .= '<th class="header-question-' . $question->id . '" 
                    data-questionid="' . $question->id . '" tabindex="0" data-toggle="popover" data-placement="top" data-trigger="focus" data-content="' . $question->name . '">' . $smallquestionname . '</th>';
            }

            $output .= '</tr></thead>';

            // Content
            $output .= '<tbody>';
            foreach ($bettersteps as $userid => $usersteps) {
                if (!array_key_exists($userid, $this->students)) {
                    continue;
                }

                $output .= '<tr class="user-' . $userid . '">';

                // Student
                $output .= '<td class="user-info">' . $this->students[$userid]->firstname . ' ' . $this->students[$userid]->lastname . '</td>';

                // Question
                foreach ($quizquestions as $question) {

                    if (isset($usersteps[$question->id])) {
                        $step   = $usersteps[$question->id];
                        $output .= '<td data-questionid="' . $step->questionid . '" data-fraction="' . $step->fraction . '" data-maxfraction="' . $step->maxfraction . '"">';
                        if ($step->fraction == '') {
                            $output .= get_string('icon-todo', 'format_ludimoodle');
                        } else if ($step->fraction == $step->maxfraction) {
                            $output .= get_string('icon-gradedright', 'format_ludimoodle');
                        } else {
                            $output .= get_string('icon-gradedwrong', 'format_ludimoodle');
                        }

                    } else {
                        $output .= '<td>';
                    }

                    $output .= '</td>';
                }

                $output .= '</tr>';
            }

            if ($activedata) {
                // Empty new line
                $output .= '<tr class="user-0">';
                $output .= '<td class="user-info"></td>';
                foreach ($quizquestions as $question) {
                    $output .= '<td data-questionid="' . $question->id . '" data-fraction="null"></td>';
                }
            }

            $output .= '</tbody>';

            $output .= '</table>';
            $output .= '</div>';

        }

        $output .= '</div>';

        return $output;
    }

}