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
 * Log events from js with achievements
 *
 * @package    course
 * @subpackage format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     David Bokobza <david.bokobza@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once '../../../../config.php';
require_once $CFG->dirroot . '/course/format/ludimoodle/classes/data_mine.class.php';
require_once $CFG->dirroot . '/course/format/ludimoodle/motivators/avatar/main.php';

$courseid    = required_param('courseid', PARAM_INT);
$userid      = required_param('userid', PARAM_INT);
$achievement = required_param('achievement', PARAM_RAW);
$value       = required_param('value', PARAM_RAW);
$location    = required_param('location', PARAM_RAW);

// Check capabilities
require_login($courseid);

if ($userid != $USER->id) {
    throw new moodle_exception('invaliduser');
}

if (!in_array($location, ['course', 'section', 'mod'])) {
    throw new moodle_exception('invalid location');
}

// Ensure value is numeric
if ($value != 'count') {
    if (!is_numeric($value)) {
        $env = \format_ludimoodle\execution_environment::get_instance($PAGE);
        $value = $env->text_to_int($value);
    }
    $value = (int) $value;
}

$datamine = new \format_ludimoodle\data_mine();

/**
 * Set in db, a new user course achievement record
 */
if ($location == 'course') {
    if ($value == 'count') {
        $count = $datamine->get_user_course_achievement($userid, $courseid, $achievement, 0);
        $count++;
        $value = $count;
    }
    $datamine->set_user_course_achievement($userid, $courseid, $achievement, $value);
}

/**
 * Set in db, a new user section achievement record
 */
if ($location == 'section') {
    $sectionid = required_param('sectionid', PARAM_INT);
    if ($value == 'count') {
        $count = $datamine->get_user_section_achievement($userid, $courseid, $sectionid, $achievement, 0);
        $count++;
        $value = $count;
    }
    $datamine->set_user_section_achievement($userid, $courseid, $sectionid, $achievement, $value);
}

/**
 * Set in db, a new user section achievement record
 */
if ($location == 'mod') {
    $cmid = required_param('cmid', PARAM_INT);
    if ($value == 'count') {
        $count = $datamine->get_user_mod_achievement($userid, $courseid, $cmid, $achievement, 0);
        $count++;
        $value = $count;
    }
    $datamine->set_user_mod_achievement($userid, $courseid, $cmid, $achievement, $value);
}

$datamine->flush_changes_to_database();