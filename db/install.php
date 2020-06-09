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
 * Install function
 *
 * @package    course
 * @subpackage format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     David Bokobza <david.bokobza@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/profile/definelib.php');
require_once($CFG->dirroot . '/course/format/ludimoodle/lib/presets_lib.php');

function xmldb_format_ludimoodle_install() {
    global $DB;

    // Define user profile field : nextmotivator
    if (!$DB->record_exists('user_info_field', ['shortname' => 'nextmotivator'])) {
        $motivatormenu = '';
        $motivators = format_ludimoodle_get_plugin_motivators();
        foreach ($motivators as $motivator) {
            $motivatormenu .= $motivator . "\r\n";
        }

        // Create a new profile field.
        $nextmotivator                    = new \stdClass();
        $nextmotivator->datatype          = 'menu';
        $nextmotivator->shortname         = 'nextmotivator';
        $nextmotivator->name              = 'nextmotivator';
        $nextmotivator->description       = get_string('nextmotivator-desc', 'format_ludimoodle');
        $nextmotivator->descriptionformat = 1;
        $nextmotivator->categoryid        = 1;
        $nextmotivator->required          = 0;
        $nextmotivator->locked            = 0;
        $nextmotivator->visible           = 0;
        $nextmotivator->forceunique       = 0;
        $nextmotivator->signup            = 0;
        $nextmotivator->defaultdata       = '';
        $nextmotivator->param1            = trim($motivatormenu);
        $profil                           = new \profile_define_base();
        $profil->define_save($nextmotivator);
    }

    return true;
}
