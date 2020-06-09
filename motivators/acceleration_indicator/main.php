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
 * Beat your reference time so that your character evolves
 *
 * @package    course
 * @package    format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     Céline Hernandez <celine@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ludimoodle;

require_once $CFG->dirroot . '/course/format/ludimoodle/classes/base_classes/motivator_base.class.php';

class motivator_acceleration_indicator extends motivator_base implements i_motivator {

    private $statname = 'accelerationlevel';
    public function get_loca_strings($lang = 'en') {
        $strings['en'] =  [
                'acceleration_indicator' => 'Timer',
                'acceleration_indicator-description' => 'Chaque question est chronométrée et chaque bonne réponse enregistre un temps qui servira de référence future. Quand ce temps de référence est battu, le personnage accélère. À quelle vitesse le ferez-vous aller ? ',
                'acceleration_indicator-best-rank' => 'Best rank !'
        ];
        $strings['fr'] = [
                'acceleration_indicator' => 'Chronomètre',
                'acceleration_indicator-description' => 'Chaque question est chronométrée et chaque bonne réponse enregistre un temps qui servira de référence future. Quand ce temps de référence est battu, le personnage accélère. À quelle vitesse le ferez-vous aller ? ',
                'acceleration_indicator-best-rank' => 'Meilleur score !'
        ];
        if (isset($strings[$lang]) && !empty($strings[$lang])) {
            return $strings[$lang];
        } else {
            return $strings;
        }
    }

    public static function get_valid_modules() {
        return true;
    }

    public static function get_valid_contexts() {
        return ['cm', 'section'];
    }

    public static function get_required_behavior_preset_attributes() {
        return [];
    }

    public static function get_required_visual_preset_attributes() {
        return [
            'todo-image',
            'reftime-image',
            'chrono-image',
            'progress-images'
        ];
    }

    public static function get_default_behavior_preset() {
        return false;
    }

    public static function get_default_visual_preset() {
        return 'run';
    }

    /*----------------------------------------
        Required render functions
    */

    public function render_section_view($env, $section){
        $datamine = $env->get_data_mine();
        $userid   = $env->get_userid();
        $courseid = $env->get_course_id();
        $cmsid    = $section->sequence;

        $mybestlevel = 0;
        $levelcmid   = 0;
        // Prepare js data
        if (count($cmsid) > 0) {
            foreach ($cmsid as $cmid) {
                $levelcmid = $levelcmid == 0 ? $cmid : $levelcmid;
                $level = $datamine->get_user_mod_achievement($userid, $courseid, $cmid, $this->statname, 0);
                if ($level > $mybestlevel) {
                    $mybestlevel = $level;
                    $levelcmid = $cmid;
                }
            }
        }

        // Render
        $visualpresetname = $this->get_visual_preset();
        $visualdata       = $this->get_visual_preset_data($visualpresetname);

        $output = $this->include_css($env);
        if (isset($visualdata['css-file'])) {
            $output .= $this->include_preset_css($env);
        }

        if ($mybestlevel == 0) {
            // No attempt
            $todoimg = $this->get_preset_image_url($visualdata['todo-image'], $visualpresetname);
            $output  .= '<img id="ludi_acceleration_indicator_img_' . $levelcmid . '" class="todo-img" src="' . $todoimg . '">';
        } else {
            $progressimageinfo = $this->get_preset_img_info_from_level($mybestlevel, $visualdata['progress-images']);
            $progressimage     = $this->get_preset_image_url($progressimageinfo->name, $visualpresetname);
            $output            .= '<div class="bestrank">';
            $output            .= '<p>'. get_string('acceleration_indicator-best-rank', 'format_ludimoodle').'</p>';
            $output            .= '<img id="ludi_acceleration_indicator_img_' . $levelcmid . '" src="' . $progressimage . '">';
            $output            .= '</div>';
        }

        return $output;
    }

    public function render_summary_section_view($env, $section){
        $datamine = $env->get_data_mine();
        $userid   = $env->get_userid();
        $courseid = $env->get_course_id();
        $cmsid     = $section->sequence;

        $mybestlevel = 0;
        $levelcmid   = 0;
        // Prepare js data
        if (count($cmsid) > 0) {
            foreach ($cmsid as $cmid) {
                $levelcmid = $levelcmid == 0 ? $cmid : $levelcmid;
                $level = $datamine->get_user_mod_achievement($userid, $courseid, $cmid, $this->statname, 0);
                if ($level > $mybestlevel) {
                    $mybestlevel = $level;
                    $levelcmid = $cmid;
                }
            }
        }
        
        // Render
        $visualpresetname = $this->get_visual_preset();
        $visualdata       = $this->get_visual_preset_data($visualpresetname);

        $output = $this->include_css($env);
        if (isset($visualdata['css-file'])) {
            $output .= $this->include_preset_css($env);
        }

        if ($mybestlevel == 0) {
            // No attempt
            $todoimg = $this->get_preset_image_url($visualdata['todo-image'], $visualpresetname);
            $output  .= '<img id="ludi_acceleration_indicator_img_' . $levelcmid . '" class="todo-img" src="' . $todoimg . '">';
        } else {
            $progressimageinfo = $this->get_preset_img_info_from_level($mybestlevel, $visualdata['progress-images']);
            $progressimage     = $this->get_preset_image_url($progressimageinfo->name, $visualpresetname);
            $output            .= '<img id="ludi_acceleration_indicator_img_' . $levelcmid . '" src="' . $progressimage . '">';
        }

        return $output;
    }

    public function render_mod_view($env, $cm) {
        $output = $this->include_css($env);
        // Get current achievement
        $datamine  = $env->get_data_mine();
        $section   = $env->get_current_section();
        $userid    = $env->get_userid();
        $courseid  = $env->get_course_id();
        $oldlevel  = $datamine->get_user_mod_achievement($userid, $courseid, $cm->id, $this->statname, 0);
        $inprocess = $env->get_attempt_id() > 0 && $env->is_page_type_in(['mod-quiz-attempt']);

        // update section advancement
        $this->set_user_section_advancement($env, $section);

        $quizid = $cm->instance;
        $page   = optional_param('page', 0, PARAM_INT) + 1;

        // Stock reftime time for future check
        $questionstimes = $this->get_quiz_attempt_stats($quizid, $userid, $page);

        $currentquestionids = $this->get_page_questionids($quizid, $page);

        $currentlasttime    = [];
        foreach ($currentquestionids as $currentquestion) {
            $lasttime = $this->get_last_time($questionstimes[$currentquestion->id]);
            if (empty($currenttime)) {
                $currentlasttime = $lasttime;
                continue;
            }

            if (($currentlasttime->endtime < $lasttime->endtime) || ($currentlasttime->duration < $lasttime->duration)) {
                $currentlasttime = $lasttime;
                continue;
            }
        }

        $currenttime = isset($currentlasttime->duration) ? $currentlasttime->duration : 0;
        $isdone      = isset($currentlasttime->endtime) && $currentlasttime->endtime > 0 ? $currenttime : false;

        $reftimeinfo = $this->get_ref_time_and_progress($questionstimes, $quizid);
        $reftime     = $reftimeinfo->reftime;
        $progress    = $reftimeinfo->progress;

        // Prepare js data
        $visualpresetname = $this->get_visual_preset();
        $visualdata       = $this->get_visual_preset_data($visualpresetname);

        // Update achievement
        $progressimageinfo = $this->get_preset_img_info_from_percent($progress, $visualdata['progress-images']);
        $progressimage     = $this->get_preset_image_url($progressimageinfo->name, $visualpresetname);
        $datamine          = $env->get_data_mine();
        $datamine->set_user_mod_achievement($userid, $courseid, $cm->id, $this->statname, $progressimageinfo->level);
        $level             = $datamine->get_user_mod_achievement($userid, $courseid, $cm->id, $this->statname, 0);

        // Render
        if (isset($visualdata['css-file'])) {
            $output .= $this->include_preset_css($env);
        }

        // Image
        $output  .= '<div class="progress-img-container"><img id="ludi_acceleration_indicator_img_' . $cm->id . '" src="' . $progressimage . '"></div>';
        $animate = $oldlevel < $level ? true : false;

        $pagetime = '00:00';
        if ($inprocess) {
            // Chrono
            $pagetime  = sprintf("%02d", floor(($currenttime / 60))) . ':' . sprintf("%02d", $currenttime % 60);
            $chronoimg = $this->get_preset_image_url($visualdata['chrono-image'], $visualpresetname);
            $output    .= '<div class="chrono-container">';
            $output    .= '<div class="chrono">';
            $output    .= '<img src="' . $chronoimg . '">';
            $output    .= '<span class="current-time">' . $pagetime . '</span>';
            $output    .= '</div>';
            $output    .= '</div>';

            // Ref time
            $pagereftime = !empty($reftime) ? sprintf("%02d", floor(($reftime / 60))) . ':' . sprintf("%02d", $reftime % 60) : '--:--';
            $reftimeimg  = $this->get_preset_image_url($visualdata['reftime-image'], $visualpresetname);
            $output      .= '<div class="reftime-container">';
            $output      .= '<div class="reftime">';
            $output      .= '<span>Temps de référence !</span>';
            $output      .= '<img src="' . $reftimeimg . '">';
            $output      .= '<span class="reftime-value">' . $pagereftime . '</span>';
            $output      .= '</div>';
            $output      .= '</div>';
        }

        $env->set_js_init_data($this->get_short_name(), [
                'moddata' => [
                        'inprocess'   => $inprocess,
                        'currenttime' => $pagetime,
                        'endtime'     => $isdone
                ],
                'animate'     => $animate
        ]);

        return $output;
    }

    public function render_summary_mod_view($env, $cm) {
        $output = $this->include_css($env);

        $datamine = $env->get_data_mine();
        $userid   = $env->get_userid();
        $courseid = $env->get_course_id();
        $level    = $datamine->get_user_mod_achievement($userid, $courseid, $cm->id, $this->statname, false);

        // Render
        $visualpresetname = $cm->presets->visual;
        $visualdata       = $this->get_visual_preset_data($visualpresetname);

        if (isset($visualdata['css-file'])) {
            $output .= $this->include_preset_css($env);
        }

        if ($level === false) {
            // No attempt
            $todoimg = $this->get_preset_image_url($visualdata['todo-image'], $visualpresetname);
            $output  .= '<img id="ludi_acceleration_indicator_img_' . $cm->id . '" class="todo-img" src="' . $todoimg . '">';
        } else {
            $progressimageinfo = $this->get_preset_img_info_from_level($level, $visualdata['progress-images']);
            $progressimage     = $this->get_preset_image_url($progressimageinfo->name, $visualpresetname);
            $output            .= '<img id="ludi_acceleration_indicator_img_' . $cm->id . '" src="' . $progressimage . '">';
        }

        return $output;
    }

    /*----------------------------------------
        Utils OK
    */

    private function get_page_questionids($quizid, $page) {
        global $DB;

        return $DB->get_records_sql('
            SELECT questionid as id
            FROM {quiz_slots}
            WHERE quizid = :quizid AND page = :page', array(
                'quizid' => $quizid,
                'page'   => $page
            ));
    }

    private function get_last_time($questiontimes) {
        // Add all duration before question is correct
        $duration = 0;
        $isright  = false;

        $questiontimes = array_reverse($questiontimes);
        foreach ($questiontimes as $order => $timeinfo) {
            if ($isright && $timeinfo->correct) {
                break;
            }
            $duration += $timeinfo->duration;

            if ($timeinfo->correct) {
                $isright = true;
            }

            if (isset($questiontimes[$order + 1]) && $questiontimes[$order + 1]->correct) {
                break;
            }
        }

        if ($duration <= 0) {
            $duration = isset($questiontimes[0]->duration) && $questiontimes[0]->duration > 0 ? $questiontimes[0]->duration : 0;
        }
        $endtime = isset($questiontimes[0]->endtime) ? $questiontimes[0]->endtime : false;
        $lasttime = (object) [
                'duration' => $duration,
                'endtime'  => $endtime
        ];
        return $lasttime;
    }

    private function get_quiz_attempt_stats($quizid, $userid, $currentpage = false) {
        global $DB;

        $quizquestions = $DB->get_records_sql('
            SELECT q.id, q.name, q.defaultmark, qs.page
            FROM {quiz_slots} qs
            JOIN {question} q ON qs.questionid = q.id
            WHERE qs.quizid = ?
            ORDER BY qs.slot ASC
        ', array($quizid));

        $questionstimes = array();
        foreach ($quizquestions as $questionid => $question) {
            $iscurrentquestion = false;
            if ($question->page == $currentpage) {
                $iscurrentquestion = true;
            }
            $questionstimes[$questionid] = $this->get_question_attempt_stats($questionid, $quizid, $userid, $iscurrentquestion);
        }

        return $questionstimes;
    }

    private function get_question_attempt_stats($questionid, $quizid, $userid, $iscurrentquestion = false) {
        global $DB;

        $quizattempts = $DB->get_records('quiz_attempts', array(
            'quiz'   => $quizid,
            'userid' => $userid
        ));

        $questiontimes = [];
        foreach ($quizattempts as $quizattemptid => $quizattempt) {

            $quizstarttime = $quizattempt->timestart;
            $quizendtime   = $quizattempt->timefinish;

            $steps = $DB->get_records_sql('
                SELECT qas.*, qa.id as quizattemptid, qua.id as questionattemptid, qs.maxmark
                FROM {quiz_attempts} qa
                JOIN {question_attempts} qua ON qa.uniqueid = qua.questionusageid
                JOIN {question_attempt_steps} qas ON qua.id = qas.questionattemptid
                JOIN {quiz_slots} qs ON qua.questionid = qs.questionid
                WHERE qa.id = :quizattemptid AND qa.userid = :userid
                AND qua.questionid = :questionid
            ', array(
                'quizattemptid' => $quizattemptid,
                'userid'        => $userid,
                'questionid'    => $questionid
            ));

            $attemptstarttime = false;
            $attemptendtime   = false;
            $questioncorrect  = false;
            $maxmark          = 1;

            foreach ($steps as $stepid => $step) {

                if (isset($step->maxmark) && !empty($step->maxmark)) {
                    $maxmark = $step->maxmark;
                }

                // Check if step timecreated is not out of bound (cause by "Allow redo within an attempt " option what copy some step and step data
                $steptime = $step->timecreated;
                if ($steptime < $quizstarttime) {
                    continue;
                }

                $stepsdata = $DB->get_records_sql('
                    SELECT *
                    FROM {question_attempt_step_data} 
                    WHERE  attemptstepid = :stepid
                    AND name = "start" OR name = "ludigrade"
              ', array('stepid' => $stepid));

                foreach ($stepsdata as $data) {
                    if ($data->name == 'start') {
                        if ($data->value < $quizstarttime && $iscurrentquestion) {
                            // If value is incorrect, update it
                            $data->value = time();
                            $DB->update_record('question_attempt_step_data', $data);
                        }
                        $attemptstarttime = $data->value;
                    }
                }

                // TODO check if question is correct with ludigrade

                if ($step->state == 'todo' || $step->state == 'completed' || $step->state == 'gradedright' || $step->state == 'gradedwrong' || $step->state == 'gradedpartial') {
                    $attemptendtime = $step->timecreated;
                }

                if ($step->state == 'gradedright') {
                    $questioncorrect = true;
                }

            }

            // If there is no start record, created it for first step
            if ($attemptstarttime == 0 && $iscurrentquestion) {
                $steps                        = array_values($steps);
                $step                         = $steps[0];
                $startstepdata                = new \stdClass();
                $startstepdata->attemptstepid = $step->id;
                $startstepdata->name          = 'start';
                $startstepdata->value         = time();
                $DB->insert_record('question_attempt_step_data', $startstepdata);
                $attemptstarttime = $startstepdata->value;
            }

            $seconds = 0;
            if ($attemptstarttime != false) {
                if ($attemptendtime > 0) {
                    $seconds = $attemptendtime - $attemptstarttime;
                } else {
                    $seconds = time() - $attemptstarttime;
                }
            }

            $questiontimes[] = (object) [
                'maxmark'           => $maxmark,
                'duration'          => $seconds,
                'starttime'         => $attemptstarttime,
                'endtime'           => $attemptendtime,
                'correct'           => $questioncorrect
            ];

        }

        return $questiontimes;
    }

    private function get_questions_by_pages($quizid) {
        global $DB;

        $slots            = $DB->get_records('quiz_slots', array('quizid' => $quizid));
        $questionsbypages = [];
        foreach ($slots as $slot) {
            $questionsbypages[$slot->page][] = $slot->questionid;
        }

        return $questionsbypages;
    }

    private function get_ref_time_and_progress($questionstimes, $quizid) {
        global $DB;

        $reftime        = false;
        $progress       = false;
        $pagesmaxmark   = [];
        $pagetimetopage = [];

        if (!empty($questionstimes)) {
            // Get time order by date
            $questionsbypage = $this->get_questions_by_pages($quizid);
            $alltimes    = array();
            foreach ($questionsbypage as $page => $questions) {
                $pagetime = null;
                // max mark of page
                $pagesmaxmark[$page] = 0;
                foreach ($questions as $questionid) {
                    $questiontimes = $questionstimes[$questionid];
                    // if question is on page increment max mark of page
                    if (isset($questiontimes[0])) {
                        $pagesmaxmark[$page] += $questiontimes[0]->maxmark;
                    }
                    $firsttime     = $this->get_question_first_time($questiontimes);

                    if (!$firsttime) {
                        $pagetime = null; // remove page time because page is not fully completed
                        continue;
                    }
                    if (!isset($pagetime->duration) || ($pagetime->duration < $firsttime->duration)) {
                        $pagetime = $firsttime;
                    }
                }
                if ($pagetime) {
                    $pagetimetopage[$pagetime->starttime] = $page;
                    $alltimes[$pagetime->starttime] = $pagetime->duration;
                }
            }


            ksort($alltimes);

            if ($alltimes) {
                // Get median time
                $reftime     = 10000000;
                $score       = -1;
                $sortedtimes = array();
                foreach ($alltimes as $starttime => $time) {
                    // more the question is graded more the question is long to do
                    $pagemaxmark = 1;
                    $page = isset($pagetimetopage[$starttime]) ? $pagetimetopage[$starttime] : false;
                    if ($page && isset($pagesmaxmark[$page])) {
                        $pagemaxmark = $pagesmaxmark[$page];
                    }
                    $score         += $time < ($reftime * $pagemaxmark) ? 1 : 0;
                    $sortedtimes[] = $time;
                    sort($sortedtimes);
                    $x = count($sortedtimes);
                    $y = $x - 1;
                    $z = floor($y / 2);

                    // ( S[(x-1)/2] + S[(x-1)-(x-1)/2] ) /2
                    $reftime = (($sortedtimes[$z] + $sortedtimes[$y - $z]) / 2);
                }

                $nbquestions = count($DB->get_records('quiz_slots', array('quizid' => $quizid)));

                $progress = 100 * $score / $nbquestions;
            }
        }

        return (object) [
            'reftime'  => $reftime,
            'progress' => $progress,
        ];
    }

    private function get_question_first_time($questiontimes) {
        $duration = 0;
        foreach ($questiontimes as $order => $timeinfo) {
            $duration += $timeinfo->duration;
            if ($timeinfo->correct) {
                $firstime           = clone($timeinfo);
                $firstime->duration = $duration;
                return $firstime;
            }
        }

        return false;
    }

    private function get_preset_img_info_from_percent($percent, $presetconfig) {
        $image = '';
        $level = 0;
        foreach ($presetconfig as $minpercent => $img) {
            if ($minpercent > $percent) {
                break;
            }
            $image = $img;
            $level++;
        }
        return (object) [
            'name'  => $image,
            'level' => $level
        ];
    }

    private function get_preset_img_info_from_level($level, $presetconfig) {
        $newkey = 1;
        $config = [];
        foreach ($presetconfig as $img) {
            $config[$newkey] = $img;
            $newkey++;
        }
        $levelconfig = isset($config[$level]) ? $config[$level] : false;
        return (object) [
            'name'  => $levelconfig,
            'level' => $level
        ];
    }
}