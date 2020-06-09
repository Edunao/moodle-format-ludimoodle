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
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     celine <celine@edunao.com>
 * @package    format_ludimoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
    var progression = {
        init: function(params) {
            console.log('progression init : ', params);
            /**
             * Update progression bar and rocket size
             */
            function updateprogressionsize() {
                var progressionbar = $('.ludi-progression.progression-bar-container');
                if (progressionbar.length > 0) {
                    // Set width from container - max width to 400
                    var width = Math.min(progressionbar.width(), 400);
                    var rocket = $('.ludi-progression.progression-bar-container .c100 img.rocket');
                    // Update progression bar size
                    $('.ludi-progression.progression-bar-container .c100').css("font-size", width + "px");
                    // Show it
                    $('.ludi-progression.progression-bar-container .c100').show();
                    // Update rocket size
                    rocket.width(width * 4);
                    rocket.height(width * 4);
                    // Show it
                    $('.ludi-progression.progression-bar-container .c100 span').addClass('show');
                }
            }
            /**
             *  Update progression step size
             */
            function updateprogressionstepsize() {
                var widthreference = (($(window).width() - 60) / 2) - 25;
                var progressionstep = $('.progression-steps-container');
                if (progressionstep.length > 0) {
                    var width = Math.min(widthreference, 400);
                    var step = $('.progression-steps-container .progression-max');
                    step.width(width * 5);
                    step.height(width * 6);
                    step.addClass('animate');
                }
            }
            // Update size on init
            updateprogressionsize();
            updateprogressionstepsize();
            // Update size on resize screen
            $(window).on('resize', function() {
                updateprogressionsize();
                updateprogressionstepsize();
            });
        }
    };
    return progression;
});

