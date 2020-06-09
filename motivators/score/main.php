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
 * Earn points across section and activities
 *
 * @package    course
 * @subpackage format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     celine <celine@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ludimoodle;

require_once $CFG->dirroot . '/course/format/ludimoodle/classes/base_classes/motivator_base.class.php';

class motivator_score extends motivator_base implements i_motivator {

    public function get_loca_strings($lang = 'en') {
        $strings['en'] = [
                'score' => 'Score', 'score-description' => 'Chaque bonne réponse rapporte des points. Visez le score maximum !'
        ];
        $strings['fr'] = [
                'score' => 'Score', 'score-description' => 'Chaque bonne réponse rapporte des points. Visez le score maximum !'
        ];
        // get strings in visual config
        $visuals = $this->get_all_presets('visual');
        foreach ($visuals as $visual) {
            $allvisualstr = $this->get_visual_preset_str($visual);
            foreach ($allvisualstr as $lang => $visualstr) {
                foreach ($visualstr as $key => $str) {
                    $strings[$lang][$key] = $str;
                }
            }
        }
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
        return ['section', 'cm'];
    }

    public static function get_required_behavior_preset_attributes() {
        return ['max-module-score'];
    }

    public static function get_required_visual_preset_attributes() {
        return ['base-background-image', 'summary-background-image'];
    }

    public static function get_default_behavior_preset() {
        return '1000pts';
    }

    public static function get_default_visual_preset() {
        return 'coin';
    }

    /*----------------------------------------
        Required render functions
    */

    public function render_mod_view($env, $cm) {
        // Get data

        $section      = $env->get_current_section();
        $behaviordata = $this->get_behavior_preset_data();
        $maxscore     = $behaviordata['max-module-score'];

        // Check if value if better than old and animate if needed
        $animate = $this->set_user_cm_advancement($env, $cm);
        $env->set_js_init_data($this->get_short_name(), ['animate' => $animate]);

        $newadvancement = $this->get_user_cm_advancement($env, $cm);
        $newmodscore    = round($newadvancement * $maxscore / 100);

        // Calculate new section score advancement x/100
        $animatesection = $this->set_user_section_advancement($env, $section);

        // Render
        $output = $this->include_css($env);
        $output .= $this->include_preset_css($env);
        $output .= '<div class="ludi-score ludi-mod-view">';
        $output .= $this->render_score_pane($newmodscore, $maxscore);
        $output .= '</div>';

        return $output;
    }

    public function render_summary_mod_view($env, $cm) {
        $behaviordata = $this->get_behavior_preset_data();
        $maxscore     = $behaviordata['max-module-score'];
        $scorebase    = $this->get_user_cm_advancement($env, $cm);
        $score        = round($scorebase * $maxscore / 100);

        // Render
        $output = $this->include_css($env);
        $output .= $this->include_preset_css($env);
        $output .= '<div class="ludi-score ludi-summary-mod-view">';
        $output .= $this->render_score_pane($score, $maxscore);
        $output .= '</div>';

        return $output;
    }

    public function render_section_view($env, $section) {
        $sectionscore    = 0;
        $cmsid           = $section->sequence;
        $behaviordata    = $this->get_behavior_preset_data();
        $maxmodscore     = $behaviordata['max-module-score'];
        foreach ($cmsid as $cmid) {
            $cmadvancement = $this->get_user_cm_advancement($env, $cmid);
            $cmscore       = round($cmadvancement * $maxmodscore / 100);
            $sectionscore += $cmscore;
        }
        $maxsectionscore = count($cmsid) * $behaviordata['max-module-score'];

        // Render
        $output = $this->include_css($env);
        $output .= $this->include_preset_css($env);
        $output .= '<div class="ludi-score ludi-section-view">';
        $output .= $this->render_score_pane($sectionscore, $maxsectionscore, true);
        $output .= '</div>';

        return $output;
    }

    public function render_summary_section_view($env, $section) {
        $sectionscore    = 0;
        $cmsid           = $section->sequence;
        $behaviordata    = $this->get_behavior_preset_data();
        $maxmodscore     = $behaviordata['max-module-score'];
        foreach ($cmsid as $cmid) {
            $cmadvancement = $this->get_user_cm_advancement($env, $cmid);
            $cmscore       = round($cmadvancement * $maxmodscore / 100);
            $sectionscore += $cmscore;
        }
        $maxsectionscore = count($cmsid) * $behaviordata['max-module-score'];

        // Render
        $output = $this->include_css($env);
        $output .= $this->include_preset_css($env);
        $output .= '<div class="ludi-score ludi-summary-section-view">';
        $output .= $this->render_score_pane($sectionscore, $maxsectionscore, true);
        $output .= '</div>';

        return $output;
    }

    /*----------------------------------------
      Custom render functions
     */

    protected function render_score_pane($score, $maxscore, $summary = false) {
        $mode               = $summary ? 'summary' : 'base';
        $presetname         = $this->get_visual_preset();
        $visualpresetconfig = $this->get_visual_preset_data();
        $backgroundimage    = $this->get_preset_image_url($visualpresetconfig[$mode . '-background-image'], $presetname);

        $class = strlen($score) > 1 ? ' str-multiple' : ' str-one';
        // render the score pane
        $score     = !$score ? 0 : round($score);
        $scorehtml = '<div class="ludi-score-pane' . $mode . '">';
        $scorehtml .= "<img src='$backgroundimage' class='ludi-score-icon'/>";
        $scorehtml .= '<div class="ludi-score-total">';
        $scorehtml .= '<span class="numerator' . $class . '">' . $score . '</span>';
        $scorehtml .= '<span class="fraction">/</span>';
        $scorehtml .= '<span class="denumerator">' . $maxscore;
        $scorehtml .= '<span class="ludi-score-pts">' . get_string('pts', 'format_ludimoodle') . '</span></span>';
        $scorehtml .= '</div>';
        $scorehtml .= '</div>';

        return $scorehtml;
    }

}