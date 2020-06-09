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
 * ajax for avatar
 *
 * @package    course
 * @subpackage format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     David Bokobza <david.bokobza@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once '../../../../../config.php';
require_once $CFG->dirroot . '/course/format/ludimoodle/classes/data_mine.class.php';
require_once $CFG->dirroot . '/course/format/ludimoodle/motivators/avatar/main.php';

$courseid = required_param('courseid', PARAM_INT);
$userid   = required_param('userid', PARAM_INT);

// Check capabilities
require_login($courseid);

if ($userid != $USER->id) {
    throw new moodle_exception('invaliduser');
}

$action = required_param('action', PARAM_RAW);
/**
 * Set in db, user course achievements a new record of used item
 */
if ($action == 'change-item') {
    $slotidx            = required_param('slotidx', PARAM_INT);
    $itemidx            = required_param('itemidx', PARAM_INT);
    $visual             = required_param('visual', PARAM_RAW);
    $datamine           = new \format_ludimoodle\data_mine();
    $presets['presets'] = (object) [
            'visual' => $visual,
            'behavior' => ''
    ];
    $avatar             = new \format_ludimoodle\motivator_avatar($presets);

    // if itemidx = 0, that means that the item is deselected
    if ($itemidx == 0) {
        $defaultimage = true;
        $item         = [
                'slotidx' => $slotidx,
                'itemidx' => $itemidx
        ];
        $itemkey      = 0;

        // log item is deselected for experimentation
        $olditemkey = $datamine->get_user_course_achievement($userid, $courseid, 'useditem-' . $slotidx, $itemkey);
        // olditemkey == 0 when user spam click
        if ($olditemkey > 0) {
            $datamine->set_user_course_achievement($userid, $courseid, 'avatar_object_remove', $olditemkey);
        }
    } else {
        $defaultimage = false;
        // get item with slotidx, itemidx
        $item    = $avatar->get_item($slotidx, $itemidx);
        $itemkey = $slotidx * 1000 + $itemidx;

        // log item is selected for experimentation
        $datamine->set_user_course_achievement($userid, $courseid, 'avatar_object_equip', $itemkey);
    }

    // set useditem to user
    $datamine->set_user_course_achievement($userid, $courseid, 'useditem-' . $slotidx, $itemkey);
    $datamine->flush_changes_to_database();

    // return url of new used item or default image if $defaultimage is true
    echo $avatar->get_item_img_url($item, $defaultimage);
}

/**
 * Set in db, user course achievements each time user use his inventory
 */
if ($action == 'log-inventory') {
    // open or close
    $mode     = required_param('mode', PARAM_RAW);
    $datamine = new \format_ludimoodle\data_mine();
    $count    = $datamine->get_user_course_achievement($userid, $courseid, 'avatar_inventory_' . $mode, 0);
    $count++;
    $datamine->set_user_course_achievement($userid, $courseid, 'avatar_inventory_' . $mode, $count);
    $datamine->flush_changes_to_database();
}



