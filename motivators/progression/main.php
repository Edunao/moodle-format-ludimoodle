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
 * Nice visualization of the progression through section and activities
 *
 * @package    course
 * @subpackage format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     David Bokobza <david.bokobza@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ludimoodle;

require_once $CFG->dirroot . '/course/format/ludimoodle/classes/base_classes/motivator_base.class.php';

class motivator_progression extends motivator_base implements i_motivator {

    public function get_loca_strings($lang = 'en') {
        $strings['en'] = [
                'progression'             => 'Progression de tâche',
                'progression-description' => 'Les bonnes réponses rechargent les réservoirs de la fusée. Grâce au carburant, la fusée avance vers la prochaine planète. Partez à la conquête de l\'espace ! ',
        ];
        $strings['fr'] = [
                'progression'             => 'Progression de tâche',
                'progression-description' => 'Les bonnes réponses rechargent les réservoirs de la fusée. Grâce au carburant, la fusée avance vers la prochaine planète. Partez à la conquête de l\'espace ! ',
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
        return [];
    }

    public static function get_default_behavior_preset() {
        return 'default';
    }

    public static function get_default_visual_preset() {
        return 'planets';
    }

    /*----------------------------------------
         Required render functions
     */

    public function render_mod_view($env, $cm) {
        $section        = $env->get_current_section();
        $animatemod     = $this->set_user_cm_advancement($env, $cm);
        $animatesection = $this->set_user_section_advancement($env, $section);
        $advancement    = $this->get_user_section_advancement($env, $section);
        $modadvancement = $this->get_user_cm_advancement($env, $cm);
        $env->set_js_init_data($this->get_short_name(), ['section' => false, 'mod' => true]);

        // Render in 2 parts
        $output = $this->include_css($env);
        $output .= '<div class="ludi-progression ludi-mod-view">';

        // First part : section progression steps and section advancement indicator
        $output .= '<div class="ludi-progression ludi-left-part">';
        $output .= $this->render_progression_steps($env, $section);
        $output .= $this->render_progression_advancement($advancement);
        $output .= '</div>';

        // Second part : cm advancement
        $output .= '<div class="ludi-progression ludi-right-part">';
        $output .= $this->render_progression_bar($modadvancement, $env);
        $output .= '</div>';

        $output .= '</div>';

        return $output;
    }

    public function render_summary_mod_view($env, $cm) {
        if ($cm->visible) {
            $imgurl = $this->image_url('icon.svg');
            $class  = ' icon';
        } else {
            $imgurl = $this->get_preset_image_url('mod_default.svg', $this->get_visual_preset());
            $class  = ' default';
        }

        // Render
        $output = $this->include_css($env);
        $output .= '<div class="ludi-progression ludi-summary-mod-view">';
        $output .= '<img id="ludi_progression_img_cm_' . $cm->id . '" class="ludi-progression-image' . $class . '" src="' . $imgurl . '">';
        $output .= '</div>';

        return $output;
    }

    public function render_section_view($env, $section) {
        $advancement = $this->get_user_section_advancement($env, $section);
        $env->set_js_init_data($this->get_short_name(), ['section' => false, 'mod' => true]);

        // Render
        $output = $this->include_css($env);
        $output .= '<div class="ludi-progression ludi-section-view">';
        $output .= $this->render_progression_steps($env, $section);
        $output .= $this->render_progression_advancement($advancement);
        $output .= '</div>';

        return $output;
    }

    public function render_summary_section_view($env, $section) {
        $advancement = $this->get_user_section_advancement($env, $section);
        $max         = $advancement == 100 ? true : false;
        $planeturl   = $this->get_course_planet_url($env, $section->section, $max);
        // Render
        $output = $this->include_css($env);
        $output .= '<div class="ludi-progression ludi-summary-section-view">';
        $output .= '<div class="course-planet">';
        $output .= '<img src="' . $planeturl . '">';
        $output .= '</div>';
        $output .= '<div class="course-progression">';
        $output .= '<span class="advancement">' . $advancement . '<span class="percentage">%</span></span>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render progression steps with background depending of section and advancement
     *
     * @param $env execution_environment
     * @param $section object
     * @return string
     */
    public function render_progression_steps($env, $section) {
        $advancement    = $this->get_user_section_advancement($env, $section);
        $progressionbg  = $this->get_progression_background_url();
        $progressionurl = $this->get_progression_step_url($env, $advancement);
        $planeturl      = $this->get_section_planet_url($env, $section->section);
        $asteroids      = $this->get_asteroids_urls();
        $zindex         = 0;
        $class          = '';

        $output = '<div class="ludi-progression progression-steps-container">';
        $output .= '<div class="progression-steps-background" style="background-image: url(' . $progressionbg . ');">';
        $output .= '<img class="progression-steps-component planet" src="' . $planeturl . '" style="z-index: ' . $zindex . ';">';
        $zindex++;
        if ($advancement == 100) {
            $class             = ' max';
            $progressionmaxurl = $this->get_progression_step_max_url();
            $output            .= '<img class="progression-steps-component progression-max" src="' . $progressionmaxurl . '">';
        }
        $zindex++;
        $output .= '<img class="progression-steps-component rocket ' . $class . '" src="' . $progressionurl . '" style="z-index: ' .
                   $zindex . ';" >';
        $zindex++;
        foreach ($asteroids as $key => $asteroidurl) {
            $output .= '<img class="progression-steps-component asteroids asteroid-' . $zindex . '" src="' . $asteroidurl . '" style="z-index: ' . $zindex . ';" >';
            $zindex++;
        }
        $output .= '</div>'; // progression-steps-background
        $output .= '</div>'; // progression-steps-container
        return $output;
    }

    /**
     * Render progression bar with rocket in it
     *
     * @param $advancement int
     * @param $env execution_environment
     * @return string
     * @throws \ReflectionException
     */
    public function render_progression_bar($advancement, $env) {
        $spriteurl = $this->get_rocket_sprite_url();
        $output    = $this->include_preset_css($env);
        $output    .= '<div class="ludi-progression progression-bar-container">';
        $output    .= '<div class="c100 biggest ludimoodle p' . $advancement . '">';
        $output    .= '<span><img class="progression-bar-component rocket" src="' . $spriteurl . '"></span>';
        $output    .= '<div class="slice">';
        $output    .= '<div class="bar"></div>';
        $output    .= '<div class="fill"></div>';
        $output    .= '</div>'; // slice
        $output    .= '</div>'; // c100
        $output    .= '</div>'; // progression-bar-container
        return $output;
    }

    /**
     * Render progression advancement
     *
     * @param $advancement int
     * @return string
     */
    public function render_progression_advancement($advancement) {
        $output = '<div class="advancement-container">';
        $output .= '<div class="advancement">' . $advancement . '<span class="percentage">%</span></div>';
        $output .= '</div>';
        return $output;
    }

    /**
     * Return course planet url
     *
     * @param $env execution_environment
     * @param $sectionidx int
     * @param bool $max
     * @return string
     */
    public function get_course_planet_url($env, $sectionidx, $max = false) {
        // Planet can have special images when advancement is 100% 
        if ($max && isset($this->get_visual_preset_data()['course-planets-max'])) {
            $visual = (array) $this->get_visual_preset_data()['course-planets-max'];
        } else {
            $visual = (array) $this->get_visual_preset_data()['course-planets'];
        }

        // Attribute to each section a planet
        $sections = $env->get_course_sections();
        $planets  = [];
        $i        = 0;
        foreach ($sections as $section) {
            $i                          = isset($visual[$i]) ? $i : 0;
            $planets[$section->section] = $visual[$i];
            $i++;
        }
        $planet = $planets[$sectionidx];
        $url    = $this->get_preset_image_url($planet, $this->get_visual_preset())->get_path();
        return $url;
    }

    /**
     * Return planet image url depending of sectionidx
     *
     * @param $env execution_environment
     * @param $sectionidx int
     * @return string
     */
    public function get_section_planet_url($env, $sectionidx) {
        $visual = (array) $this->get_visual_preset_data()['section-planets'];
        // Attribute to each section a planet
        $sections = $env->get_course_sections();
        $planets  = [];
        $i        = 0;
        foreach ($sections as $section) {
            $i                          = isset($visual[$i]) ? $i : 0;
            $planets[$section->section] = $visual[$i];
            $i++;
        }
        $planet = $planets[$sectionidx];
        $url    = $this->get_preset_image_url($planet, $this->get_visual_preset())->get_path();
        return $url;
    }

    /**
     * Return progression step url depending of advancement
     *
     * @param $env execution_environment
     * @param $advancement
     * @return string
     */
    public function get_progression_step_url($env, $advancement) {
        $visual       = (array) $this->get_visual_preset_data()['progression-steps'];
        $datamine     = $env->get_data_mine();
        $userid       = $env->get_userid();
        $courseid     = $env->get_course_id();
        $sectionid    = $env->get_section_id();
        $oldsteplevel = $datamine->get_user_section_achievement($userid, $courseid, $sectionid, 'progression-step', 0);
        $progressions = [];
        foreach ($visual as $threshold => $image) {
            $progressions[(int) $threshold] = $image;
        }
        $steps     = array_keys($visual);
        $steplevel = 0;
        while (isset($steps[$steplevel]) && $advancement >= $steps[$steplevel]) {
            $steplevel++;
        }
        $steplevel = $steplevel >= 1 ? $steplevel - 1 : $steplevel;
        $steplevel = $advancement < 0 ? 0 : $steplevel;
        $visualkey = $steps[$steplevel];
        if ($steplevel > $oldsteplevel) {
            $datamine->set_user_section_achievement($userid, $courseid, $sectionid, 'progression-step', $steplevel);
            // animation when you reach a new level
            $env->set_js_init_data($this->get_short_name(), ['animate' => true]);
        }
        $step = $progressions[$visualkey];
        $url  = $this->get_preset_image_url($step, $this->get_visual_preset())->get_path();
        return $url;
    }

    //-------------------------------------------------//
    //          Return url of preset image url         //
    //-------------------------------------------------//
    public function get_progression_step_max_url() {
        $img = $this->get_visual_preset_data()['progression-steps-max'];
        return $this->get_preset_image_url($img, $this->get_visual_preset())->get_path();
    }

    public function get_progression_background_url() {
        $img = $this->get_visual_preset_data()['progression-background'];
        return $this->get_preset_image_url($img, $this->get_visual_preset())->get_path();
    }

    public function get_rocket_sprite_url() {
        $elements = (array) $this->get_visual_preset_data()['mod-elements'];
        $img      = $elements['rocket-sprite'];
        return $this->get_preset_image_url($img, $this->get_visual_preset())->get_path();
    }

    public function get_asteroids_urls() {
        $elements  = (array) $this->get_visual_preset_data()['asteroids'];
        $asteroids = [];
        foreach ($elements as $asteroidfile) {
            $asteroids[] = $this->get_preset_image_url($asteroidfile, $this->get_visual_preset())->get_path();
        }
        return $asteroids;
    }
}