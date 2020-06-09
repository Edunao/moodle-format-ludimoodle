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
 * This motivator is the default motivator when a section can have a motivator but it is not yet defined
 *
 * @package    course
 * @subpackage format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     celine <celine@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ludimoodle;

require_once $CFG->dirroot . '/course/format/ludimoodle/classes/base_classes/motivator_base.class.php';

class motivator_none extends motivator_base implements i_motivator {

    public static function get_valid_modules(){
        return true;
    }

    public static function get_valid_contexts(){
        return ['cm','section'];
    }

    public static function get_required_behavior_preset_attributes(){
        return [];
    }

    public static function get_required_visual_preset_attributes(){
        return [];
    }

    public static function get_default_behavior_preset(){
        return '';
    }

    public static function get_default_visual_preset(){
        return 'questionmark';
    }

    /*----------------------------------------
         Required render functions
     */

    public function render_mod_view($env, $cm){
        $baseimage    = $this->get_preset_image_url('mod.svg', $this->get_visual_preset());

        // Render
        $output = $this->include_css($env);
        $output .= '<div class="ludi-none ludi-mod-view">';
        $output .= '<img class="ludi-none-image " src="' . $baseimage . '" >';
        $output .= '</div>';

        return $output;
    }

    public function render_summary_mod_view($env, $cm){
        $baseimage    = $this->get_preset_image_url('mod.svg', $this->get_visual_preset());

        // Render
        $output = $this->include_css($env);
        $output .= '<div class="ludi-none ludi-summary-mod-view">';
        $output .= '<img class="ludi-none-image " src="' . $baseimage . '" >';
        $output .= '</div>';

        return $output;
    }

    public function render_section_view($env, $section){
        $baseimage    = $this->get_preset_image_url('section.svg', $this->get_visual_preset());

        // Render
        $output = $this->include_css($env);
        $output .= '<div class="ludi-none ludi-section-view">';
        $output .= '<img class="ludi-none-image " src="' . $baseimage . '" >';
        $output .= '</div>';

        return $output;
    }

    public function render_summary_section_view($env, $section){
        $baseimage    = $this->get_preset_image_url('section.svg', $this->get_visual_preset());

        // Render
        $output = $this->include_css($env);
        $output .= '<div class="ludi-none ludi-summary-section-view">';
        $output .= '<img class="ludi-none-image " src="' . $baseimage . '" >';
        $output .= '</div>';

        return $output;
    }
}