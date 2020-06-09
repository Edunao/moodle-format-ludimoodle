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
 * Render course header
 *
 * @package    course
 * @subpackage format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     celine <celine@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->dirroot . '/course/format/ludimoodle/classes/execution_environment.class.php');


class format_ludimoodle_motivators implements renderable {

    public $output;

    public function __construct($courseid) {
        global $PAGE;

        $env = \format_ludimoodle\execution_environment::get_instance($PAGE);

        // Content
        $renderer = $PAGE->get_renderer('format_ludimoodle');
        $this->output =  $renderer->ludic_container();

        // Add js (js is not required in editing mode)
        if (!$PAGE->user_is_editing()) {
            $params = [
                'courseid' => $env->get_course_id(),
                'sectionid' => $env->get_section_id(),
                'cmid' => $env->get_cm_id(),
                'userid'   => $env->get_userid()
            ];
            $PAGE->requires->js_call_amd('format_ludimoodle/ludimoodle', 'init', ['params' => $params]);
            $jsdata = $env->get_js_init_data();
            if (!empty($jsdata)) {
                $PAGE->requires->js_call_amd('format_ludimoodle/ludimoodle', 'init_motivator', ['motivatordata' => $jsdata]);
            }
        }

        // flush any environment changes
        $env->get_data_mine()->flush_changes_to_database();
    }

}

