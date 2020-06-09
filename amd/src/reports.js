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
 * Javascript used in reports
 *
 * @package    course
 * @subpackage format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     celine <celine@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function ($) {
    var coursestudents = [];
    return {
        init: function (courseid, students, quizzes) {
            var that = this;
            console.log('init report', courseid, quizzes);
            coursestudents = students;

            // Hide empty sections and tables container
            $('.section-container').each(function() {
                var isempty = true;
                $(this).find('.report-quiz').each(function() {
                    console.log($(this).find('tr.user-*'));
                    if ($(this).find('tbody tr[class^=user-]:not(.user-0)').length > 0){
                        isempty = false;
                    } else {
                        $(this).parent('.report-quiz-container').hide();
                    }
                });
                if (isempty) {
                    $(this).hide();
                }
            });

            // Update dynamic report content
            var interval = window.setInterval(function() {
                var url = M.cfg.wwwroot + '/course/format/ludimoodle/ajax/reports.php';
                var ajaxsince = Math.round(+new Date() / 1000) - 10;

                $.ajax({
                    method: 'GET',
                    url: url,
                    data: {
                        'action': 'updatewipreport',
                        'courseid': courseid,
                        'quizzes': JSON.stringify(quizzes),
                        'since': ajaxsince
                    },
                    dataType: 'json',
                    error: function(jqXHR, error, errorThrown) {
                        clearInterval(interval);
                        console.log('error, refresh stopped', error);
                    },
                    success: function (response) {

                        console.log(ajaxsince + '=>', response);
                        if (response.length > 0) {
                            that.update_monitor_quiz_report_wip(response);
                        }
                    }
                });
            }, 5000);
        },
        /**
         * Update dynamic report content
         * @param newdata
         */
        update_monitor_quiz_report_wip: function(newdata) {
            for (var i = (newdata.length - 1); i >= 0; i--) {
                var stepdata = newdata[i];
                var quiztable = $('table.report-quiz[data-contextid="' + stepdata.contextid + '"]');

                if (!coursestudents[stepdata.userid]) {
                    continue;
                }

                var userrow = quiztable.find('tr.user-' + stepdata.userid);
                if (userrow.length > 0) {
                    // Update if state is better
                    var oldfraction = userrow.find('td[data-questionid="' + stepdata.questionid + '"]').data('fraction');
                    var maxfraction = stepdata.maxfraction;
                    userrow.find('td[data-questionid="' + stepdata.questionid + '"]').data('maxfraction', maxfraction);

                    if(stepdata.fraction === null && stepdata.ludigrade != undefined){
                        stepdata.fraction = maxfraction * stepdata.ludigrade / stepdata.maxmark;
                    }

                    if ((oldfraction === null || stepdata.fraction !== null) &&
                        (oldfraction === null || oldfraction <= stepdata.fraction)) {
                        var icon = this.get_fraction_icon(stepdata.fraction, maxfraction);
                        userrow.find('td[data-questionid="' + stepdata.questionid + '"]').html(icon);
                        userrow.find('td[data-questionid="' + stepdata.questionid + '"]').data('fraction', stepdata.fraction);
                    } else if ((oldfraction === null && stepdata.fraction !== null)) {
                        var icon = this.get_fraction_icon(stepdata.fraction, maxfraction);
                        userrow.find('td[data-questionid="' + stepdata.questionid + '"]').html(icon);
                        userrow.find('td[data-questionid="' + stepdata.questionid + '"]').data('fraction', stepdata.fraction);
                    }

                } else {
                    // Add
                    var user = coursestudents[stepdata.userid];
                    var emptyrow = quiztable.find('tr.user-0');
                    userrow = emptyrow.clone();
                    userrow.attr('class', 'user-' + stepdata.userid);
                    userrow.find('.user-info').html(user.firstname + ' ' + user.lastname);

                    var stateicon = this.get_fraction_icon(stepdata.fraction, stepdata.maxfraction);
                    userrow.find('td[data-questionid="' + stepdata.questionid + '"]').html(stateicon);
                    userrow.find('td[data-questionid="' + stepdata.questionid + '"]').data('fraction', stepdata.fraction);
                    userrow.find('td[data-questionid="' + stepdata.questionid + '"]').data('maxfraction', stepdata.maxfraction);
                    quiztable.append(userrow);
                }

                $('tr.user-' + stepdata.userid + ' td').removeClass('last-updated');
                userrow.find('td[data-questionid="' + stepdata.questionid + '"]').addClass('last-updated');

                // Unhide table and section
                quiztable.parent('.report-quiz-container').show();
                quiztable.parents('.section-container').show();
            }
        },
        /**
         * Get icon : TO DO / OK / NOT OK
         * @param fraction
         * @param maxfraction
         * @return string
         */
        get_fraction_icon: function (fraction, maxfraction) {
            console.log('get : fraction , maxfraction', fraction, maxfraction);
            if (fraction === '' || fraction === null) {
                return M.util.get_string('icon-todo', 'format_ludimoodle');
            } else if (fraction == maxfraction) {
                return M.util.get_string('icon-gradedright', 'format_ludimoodle');
            } else {
                return M.util.get_string('icon-gradedwrong', 'format_ludimoodle');
            }
        }

    };
});