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
 * Lib for presets motivators
 *
 * @package    course
 * @subpackage format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     celine <celine@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function format_ludimoodle_get_plugin_motivators() {
    global $CFG;

    $motivatorsdir = scandir($CFG->dirroot . '/course/format/ludimoodle/motivators');

    foreach ($motivatorsdir as $key => $dirname) {
        if (in_array($dirname, array(".", "..", "none", "nomotivator"))) {
            unset($motivatorsdir[$key]);
        }
    }

    return $motivatorsdir;
}

function format_ludimoodle_get_preset_types(){
    return ['visual', 'behavior'];
}
