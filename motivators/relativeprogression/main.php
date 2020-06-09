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
 * The student is in competition with fake users
 *
 * @package    course
 * @package    format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)max-module-score
 * @author     celine <celine@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ludimoodle;

require_once $CFG->dirroot . '/course/format/ludimoodle/classes/base_classes/motivator_base.class.php';
require_once $CFG->dirroot . '/course/format/ludimoodle/motivators/relativeprogression/relative_progress.class.php';

class motivator_relativeprogression extends motivator_base implements i_motivator {

    private $statname = 'relativeprogressionrank';

    public function get_loca_strings($lang = 'en') {
        $strings['en'] =  [
                'relativeprogression'  => 'Progression comparée',
                'relativeprogression-description' => 'Gagnez des points en répondant correctement et comparez-vous aux élèves des années précédentes en essayant de finir en tête du classement !'
        ];
        $strings['fr'] =  [
                'relativeprogression'  => 'Progression comparée',
                'relativeprogression-description' => 'Gagnez des points en répondant correctement et comparez-vous aux élèves des années précédentes en essayant de finir en tête du classement !'
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

    public static function get_required_behavior_preset_attributes(){
        return [];
    }

    public static function get_required_visual_preset_attributes(){
        return ['todo-image', 'summary-image', 'mod-image', 'summary-js-data', 'mod-js-data'];
    }

    public static function get_default_behavior_preset(){
        return 'none';
    }

    public static function get_default_visual_preset(){
        return 'characters';
    }

    /*----------------------------------------
        Required render functions
    */


    public function render_section_view($env, $section) {
        $userid     = $env->get_userid();
        $datamine   = $env->get_data_mine();
        $courseid   = $env->get_course_id();
        $cmsid      = $section->sequence;
        $mybestrank = 99;
        $rankcmid   = 0;

        // Prepare js data
        if (count($cmsid) > 0) {
            foreach ($cmsid as $cmid) {
                $rankcmid = $rankcmid == 0 ? $cmid : $rankcmid;
                $rank = $datamine->get_user_mod_achievement($userid, $courseid, $cmid, $this->statname, 99);
                if ($rank < $mybestrank) {
                    $mybestrank = $rank;
                    $rankcmid = $cmid;
                }
            }
        }
        $mybestrank = $mybestrank == 99 ? 0 : $mybestrank;

        $jsdata           = [];
        $visualpresetname = $this->get_visual_preset();
        $visualdata       = $this->get_visual_preset_data($visualpresetname);

        $summaryconfig = $visualdata['summary-js-data'];
        if (!$mybestrank) {
            // "no attempt" case
            $jsdata['revealed_layers'] = $summaryconfig->no_attempt_layers;
        } else if ($mybestrank == 1) {
            // "victory" case
            $jsdata['revealed_layers'] = $summaryconfig->victory_layers;
        } else {
            $jsdata['revealed_layers'] = $summaryconfig->layers;
            $jsdata['rank_container']  = $summaryconfig->rank_container;
            $jsdata['rank_prefix_container'] = $summaryconfig->rank_prefix_container;
            $jsdata['rank'] = $mybestrank;
        }

        // Render
        $output = '';
        if (isset($visualdata['css-file'])) {
            $output .= $this->include_preset_css($env);
        }
        $output .= '<div class="ludi-section-view ludi-relativeprogression relativeprogress-summary-container" >';

        if ($mybestrank === false) {
            $todoimg = $this->get_preset_image_url($visualdata['todo-image'], $visualpresetname);
            $output  .= '<img id="ludi_relativeprogression_img_' . $rankcmid . '" class="todo-img" src="' . $todoimg . '">';
        } else {
            $progressimg = $this->get_preset_image_url($visualdata['summary-image'], $visualpresetname);
            $output      .= '<img class="relativeprogress-summary-img svg" id="ludi_relativeprogression_img_' . $rankcmid . '" src="' . $progressimg . '">';
        }

        $output .= '</div>';

        $env->set_js_init_data($this->get_short_name(), ['summarydata' => ['ludi_relativeprogression_img_' . $rankcmid => $jsdata]]);

        return $output;
    }

    public function render_summary_section_view($env, $section){
        $userid     = $env->get_userid();
        $datamine   = $env->get_data_mine();
        $courseid   = $env->get_course_id();
        $cmsid      = $section->sequence;
        $mybestrank = 99;
        $rankcmid   = 0;

        // Prepare js data
        if (count($cmsid) > 0) {
            foreach ($cmsid as $cmid) {
                $rankcmid = $rankcmid == 0 ? $cmid : $rankcmid;
                $rank = $datamine->get_user_mod_achievement($userid, $courseid, $cmid, $this->statname, 99);
                if ($rank < $mybestrank) {
                    $mybestrank = $rank;
                    $rankcmid = $cmid;
                }
            }
        }
        $mybestrank = $mybestrank == 99 ? 0 : $mybestrank;
        $jsdata           = [];
        $visualpresetname = $this->get_visual_preset();
        $visualdata       = $this->get_visual_preset_data($visualpresetname);

        $summaryconfig = $visualdata['summary-js-data'];
        if (!$mybestrank) {
            // "no attempt" case
            $jsdata['revealed_layers'] = $summaryconfig->no_attempt_layers;
        } else if ($mybestrank == 1) {
            // "victory" case
            $jsdata['revealed_layers'] = $summaryconfig->victory_layers;
        } else {
            $jsdata['revealed_layers'] = $summaryconfig->layers;
            $jsdata['rank_container']  = $summaryconfig->rank_container;
            $jsdata['rank_prefix_container'] = $summaryconfig->rank_prefix_container;
            $jsdata['rank'] = $mybestrank;
        }

        // Render
        $output = '';
        if (isset($visualdata['css-file'])) {
            $output .= $this->include_preset_css($env);
        }
        $output .= '<div class="ludi-summary-section-view ludi-relativeprogression relativeprogress-summary-container" >';

        if ($mybestrank === false) {
            $todoimg = $this->get_preset_image_url($visualdata['todo-image'], $visualpresetname);
            $output  .= '<img id="ludi_relativeprogression_img_' . $rankcmid . '" class="todo-img" src="' . $todoimg . '">';
        } else {
            $progressimg = $this->get_preset_image_url($visualdata['summary-image'], $visualpresetname);
            $output      .= '<img class="relativeprogress-summary-img svg" id="ludi_relativeprogression_img_' . $rankcmid . '" src="' . $progressimg . '">';
        }

        $output .= '</div>';

        $env->set_js_init_data($this->get_short_name(), ['summarydata' => ['ludi_relativeprogression_img_' . $rankcmid => $jsdata]]);

        return $output;
    }

    public function render_mod_view($env, $cm) {
        $attemptid = $env->get_attempt_id();
        $userid    = $env->get_userid();
        $datamine  = $env->get_data_mine();
        $courseid  = $env->get_course_id();
        $section   = $env->get_current_section();
        $animate   = $this->set_user_section_advancement($env, $section);

        // Get attempt grades and put them between 0 and 1
        $answersdata = $datamine->fetch_attempt_answers($attemptid);

        $ranks = [];
        if (count($answersdata) > 0) {
            $answerscores = [];
            foreach ($answersdata as $data){
                $answerscores[] = $data->grade / $data->maxgrade;
            }
            $attempt = $datamine->fetch_attempt_info($attemptid);

            $calculator = new relative_progress_position_calculator();
            $results    = $calculator->calculate($attempt->questionsnumber, 50, $answerscores);

            // Convert results to ranks
            $counter = 1;
            for ($i = 3; $i >= -3; $i--) {
                $nbatrank = $results[$i];
                if ($nbatrank == 0) {
                    $ranks[$i] = 0;
                    continue;
                }

                $ranks[$i] = $counter;
                $counter   += $nbatrank;
            }

            $myrank = $ranks[0];

            // Save values in achievements
            $datamine = $env->get_data_mine();
            $datamine->set_user_mod_achievement($userid, $courseid, $cm->id, $this->statname, $myrank);

        }

        // Prepare js data
        $visualpresetname = $this->get_visual_preset();
        $visualdata       = $this->get_visual_preset_data($visualpresetname);

        $jsdata          = [];
        $jsdata['ranks'] = $ranks;
        $jsdata          = array_merge($jsdata, (array)$visualdata['mod-js-data']);

        // Render
        $output = '';
        if (isset($visualdata['css-file'])) {
            $output .= $this->include_preset_css($env);
        }
        $img = $this->get_preset_image_url($visualdata['mod-image'], $visualpresetname);
        $output      .= '<img class="relativeprogress-mod-img svg" id="ludi_relativeprogression_img_' . $cm->id . '" src="' . $img . '">';

        $env->set_js_init_data($this->get_short_name(), [
                        'moddata' => ['ludi_relativeprogression_img_' . $cm->id => $jsdata],
                        'animate' => $animate
        ]);

        return $output;
    }

    public function render_summary_mod_view($env, $cm) {
        // Get user data
        $datamine = $env->get_data_mine();
        $userid   = $env->get_userid();
        $courseid = $env->get_course_id();
        $quizrank = $datamine->get_user_mod_achievement($userid, $courseid, $cm->id, $this->statname, false);

        // Prepare js data
        $jsdata           = [];
        $visualpresetname = $this->get_visual_preset();
        $visualdata       = $this->get_visual_preset_data($visualpresetname);

        $summaryconfig = $visualdata['summary-js-data'];
        if (!$quizrank) {
            // "no attempt" case
            $jsdata['revealed_layers'] = $summaryconfig->no_attempt_layers;
        } else if ($quizrank == 1) {
            // "victory" case
            $jsdata['revealed_layers'] = $summaryconfig->victory_layers;
        } else {
            $jsdata['revealed_layers'] = $summaryconfig->layers;
            $jsdata['rank_container']  = $summaryconfig->rank_container;
            $jsdata['rank_prefix_container'] = $summaryconfig->rank_prefix_container;
            $jsdata['rank'] = $quizrank;
        }

        // Render
        $output = '';
        if (isset($visualdata['css-file'])) {
            $output .= $this->include_preset_css($env);
        }
        $output .= '<div class="relativeprogress-summary-container" >';

        if ($quizrank === false) {
            $todoimg = $this->get_preset_image_url($visualdata['todo-image'], $visualpresetname);
            $output  .= '<img id="ludi_relativeprogression_img_' . $cm->id . '" class="todo-img" src="' . $todoimg . '">';
        } else {
            $progressimg = $this->get_preset_image_url($visualdata['summary-image'], $visualpresetname);
            $output      .= '<img class="relativeprogress-summary-img svg" id="ludi_relativeprogression_img_' . $cm->id . '" src="' . $progressimg . '">';
        }

        $output .= '</div>';

        $env->set_js_init_data($this->get_short_name(), ['summarydata' => ['ludi_relativeprogression_img_' . $cm->id => $jsdata]]);

        return $output;
    }

}