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
 * All motivators must implements this interface
 *
 * @package    course
 * @subpackage format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     celine <celine@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ludimoodle;
defined('MOODLE_INTERNAL') || die();

interface i_motivator {

    /**
     * Return array of mod name or true if all modules are accepted
     * @return boolean or array
     */
    public static function get_valid_modules();

    /**
     * Return array of context (module, section, course)
     * @return array
     */
    public static function get_valid_contexts();

    /**
     * @return mixed
     */
    public static function get_required_behavior_preset_attributes();

    /**
     * @return mixed
     */
    public static function get_required_visual_preset_attributes();

    /**
     * @return string of default behavior
     */
    public static function get_default_behavior_preset();

    /**
     * @return string of default visual
     */
    public static function get_default_visual_preset();

    /**
     * Render main view on mod page
     * In section page if there is a method 'render_summary_mod_view', it's used instead.
     * @param execution_environment $env
     * @param object $cm
     * @return string
     */
    public function render_mod_view($env, $cm);

    /**
     * Render main view on section page
     * In course page if there is a method 'render_summary_section_view', it's used instead.
     * @param execution_environment $env
     * @param object $section
     * @return string
     */
    public function render_section_view($env, $section);

}