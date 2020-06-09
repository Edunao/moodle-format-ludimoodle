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
 * @author     Céline Hernandez (celine@edunao.com)
 * @package    format_ludimoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


define(['jquery'], function ($) {
    var acceleration_indicator = {

        init: function (params, globaluniqueidx) {
            console.log('Acceleration indication init: ', params);

            if (params.summarydata === undefined) {
                this.init_attempt(params.moddata, globaluniqueidx);
            }
        },
        init_attempt: function(params, globaluniqueidx){
            console.log('Acceleration indication Attempts init', params);
            var that = this;

            // animate timer
            if(params.endtime != undefined && params.inprocess != undefined) {
                if(params.endtime > 0 || params.inprocess === false){
                    // timer is not animate
                    $('.current-time').addClass('done');
                } else {
                    window.setInterval(function () {
                        var value = $('.current-time').text();

                        value = value.replace(/(\d+):(\d+)/, function (match, minutes, secondes) {
                            secondes = (parseInt(secondes) + 1) % 60;
                            minutes = parseInt(minutes) + (secondes == 0 ? 1 : 0);
                            return that.pad(minutes, 2) + ":" + that.pad(secondes, 2);
                        });

                        $('.current-time').text(value);
                    }, 1000);
                }
            }


        },
        pad: function(str, max) {
            str = str.toString();
            return str.length < max ? this.pad("0" + str, max) : str;
        }
    };

    return acceleration_indicator;
});