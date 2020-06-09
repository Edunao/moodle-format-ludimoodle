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
 * Main js file of format_ludimoodle
 *
 * @package    course
 * @subpackage format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     celine <celine@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'jqueryui'], function ($, ui) {
    var uniqueidx  = 0;
    var animate    = false;
    var courseid   = null;
    var userid     = null;
    var sectionid  = null;
    var sectionidx = null;
    var ludicMotivators = {

        /**
         * Always called in a format_ludimoodle page
         */
        init: function (params) {
            console.log('init ludic motivators format');
            console.log('params', params);
            ludicMotivators.courseid  = params.courseid;
            ludicMotivators.userid    = params.userid;
            ludicMotivators.sectionid = params.sectionid;
            ludicMotivators.cmid      = params.cmid;

            // Close nav drawer by default when you enter in a format ludimoodle page
            ludicMotivators.close_nav_drawer();

            // Update navigation links with last section/quiz
            ludicMotivators.update_navigation();

            // Hide next and previous page buttons in quiz and disabled nav link if all page questions aren't checked
            ludicMotivators.update_quiz_navigation();

            // Show motivator explanation window on first access
            ludicMotivators.init_motivator_explanation();
        },
        /**
         *
         *
         * @param motivatorsjsdata
         */
        init_motivator: function (motivatorsjsdata) {
            console.log("init_motivator", motivatorsjsdata);

            // Update course header background with image of current motivator
            ludicMotivators.update_background();

            for (var motivatorname in motivatorsjsdata) {
                let motivatordata = motivatorsjsdata[motivatorname];

                // Motivator needs to be animate ?
                if (animate === false && motivatordata.animate) {
                    animate = true;
                    // Animate it
                    ludicMotivators.animate_course_header();
                }
                // Require corresponding js of motivator
                require(['format_ludimoodle/' + motivatorname], function (motivator) {
                    motivator.init(motivatordata, uniqueidx);
                    uniqueidx++;
                });
            }

        },
        /**
         * Show motivator explanation window on first access
         */
        init_motivator_explanation: function() {
            var motivatorexplanation = $('a[href^="#motivator-explanation"]');
            var motivator = motivatorexplanation.data('motivator');

            // Show motivator explanation window on click in nav button
            motivatorexplanation.on('click', function (e) {
                e.preventDefault();
                // Log motivator explanation click
                ludicMotivators.add_achievement('section', 'game_element_info_text_show', 'count');
                $('.ludimoodle-overlay').show(400);
                $('#motivator-explanation').show(400);

                // Hide motivator explanation window on click in close button or outside of window
                $('#motivator-explanation-close, .ludimoodle-overlay').unbind("click");
                $('#motivator-explanation-close, .ludimoodle-overlay').on('click', function (e) {
                    ludicMotivators.add_achievement('section', 'game_element_info_text_dismiss', 'count');
                    $('.ludimoodle-overlay').hide(400);
                    $('#motivator-explanation').hide(400);
                });
            });
            // Always displayed on first access
            if ($('.ludi-section-container.show .ludi-details-view').hasClass('first-access')) {
                motivatorexplanation.click();
            }


        },
        /**
         * Add ludimoodle achievement record in db
         * @param location
         * @param achievement
         * @param value
         */
        add_achievement: function(location, achievement, value) {
            console.log(this);
            var data = {
                userid:      ludicMotivators.userid,
                courseid:    ludicMotivators.courseid,
                sectionid:   ludicMotivators.sectionid,
                cmid:        ludicMotivators.cmid,
                location:    location,
                achievement: achievement,
                value:       value
            };

            // Be sure to have required params
            if (location === 'section' && data.sectionid <= 0 ||
                location === 'mod' && data.cmid <= 0
            ) {
                console.log('missing data ', data);
                return false;
            }

            $.ajax({
                url: M.cfg.wwwroot + '/course/format/ludimoodle/ajax/add_achievement.php',
                method: 'POST',
                data: data,
                success: function() {
                    console.log('achievement added', data);
                },
                error: function(error) {
                    console.log('error ', data, error);
                }
            });
        },
        /**
         * Close nav drawer
         */
        close_nav_drawer: function () {
            // button selector match only when nav drawer is visible
            var button = $('nav button[aria-expanded="true"][aria-controls="nav-drawer"]');
            if (button.length) {
                button.click();
            }
        },
        /**
         * Update navigation links with last section/quiz
         */
        update_navigation: function () {

            // Update course link
            if (window.location.href.indexOf('course/view.php') != -1) {
                var currentcourse = ludicMotivators.get_url_param('id', null);
                if (currentcourse !== null) {
                    sessionStorage.setItem('currentcourseid', currentcourse);
                }
            }
            var lastcourse = sessionStorage.getItem('currentcourseid');
            if (lastcourse !== null) {
                var courseurl = $('.ludimoodle-container .ludi-navigation .nav-buttons.course').attr('href');
                if (courseurl !== undefined) {
                    var prevcourseurlid = ludicMotivators.get_url_param('id', courseurl);
                    courseurl = courseurl.replace('?id=' + prevcourseurlid, '?id=' + lastcourse);
                    $('.ludimoodle-container .ludi-navigation .nav-buttons.course').attr('href', courseurl);
                }
            }

            // Update section link
            var currentsection = ludicMotivators.get_url_param('section', null);
            if (currentsection !== null) {
                sessionStorage.setItem('currentsectionidx', currentsection);
            }
            var lastsection = sessionStorage.getItem('currentsectionidx');
            if (lastsection !== null) {
                var sectionurl = $('.ludimoodle-container .ludi-navigation .nav-buttons.section').attr('href');
                if (sectionurl !== undefined) {
                    var prevsectionid = ludicMotivators.get_url_param('section', sectionurl);
                    sectionurl = sectionurl.replace('&section=' + prevsectionid, '&section=' + lastsection);
                    $('.ludimoodle-container .ludi-navigation .nav-buttons.section').attr('href', sectionurl);
                }
            }

            // Update mod link
            if (window.location.href.indexOf('mod/') != -1) {
                sessionStorage.setItem('currentmodurl', window.location.href);
            }
            var lastmodurl = sessionStorage.getItem('currentmodurl');
            if (lastmodurl !== null) {
                $('.ludimoodle-container .ludi-navigation .nav-buttons.mod').attr('href', lastmodurl);
            }

            // Update content of ludimoodle block with navigation if possible
            $('.ludi-navigation .nav-buttons').on('click', function (e) {
                var choosentype = $(this).data('type');
                var blocktodisplay = $('.ludi-main-container[data-type="'  + choosentype + '"]');
                if (blocktodisplay.length > 0) {
                    e.preventDefault();
                    // add achievements to trace user
                    ludicMotivators.add_achievement(choosentype, choosentype + '-view', 'count');

                    // Set url on browser
                    window.history.pushState("", "", $(this).attr("href"));
                    $('.ludi-navigation .nav-buttons').removeClass('active');
                    $(this).addClass('active');
                    $('.ludi-main-container').removeClass('show');
                    blocktodisplay.addClass('show');
                }
            });
        },
        /**
         * Return value of url param
         *
         * @param name
         * @param url
         * @returns {*}
         */
        get_url_param: function (name, url) {
            if (url === null) {
                url = window.location.href;
            }
            var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(url);
            if (results === null) {
                return null;
            }
            return decodeURI(results[1]) || 0;
        },
        /**
         * Hide next and previous page buttons in quiz and disabled nav link if all page questions aren't checked
         */
        update_quiz_navigation: function () {
            if ($('#page-mod-quiz-attempt').length > 0 || $('#page-mod-quiz-review').length > 0) {
                var nbimmque = $('form .que.immediatefeedback ').length;
                // Hide "Next page" button if not all questions are submitted
                if (($('#responseform .que .im-controls .submit.btn').length > 0)) {
                    $('input[type="submit"].mod_quiz-next-nav').hide();
                    $('input[type="submit"].mod_quiz-prev-nav').hide();
                    //hide finish attemp button
                    $('#mod_quiz_navblock .othernav .endtestlink').hide();
                    //disable link on quiz navigation
                    $('#mod_quiz_navblock .content .multipages .qnbutton.notyetanswered, ' +
                        '#mod_quiz_navblock .content .multipages .qnbutton.invalidanswer ')
                        .css('pointer-events', 'none')
                        .css('cursor', 'default');

                } else if (nbimmque > 0) {
                    //Remove "Finish attempt" button if immediat feedback question
                    $('#mod_quiz_navblock .othernav .endtestlink').hide();
                    //disable link on quiz navigation
                    $('#mod_quiz_navblock .content .multipages .qnbutton.notyetanswered, ' +
                        '#mod_quiz_navblock .content .multipages .qnbutton.invalidanswer ')
                        .css('pointer-events', 'none')
                        .css('cursor', 'default');
                }

                if (($('#responseform .que .im-controls .submit.btn').length == 0)) {
                    $('input[type="submit"].mod_quiz-next-nav').show();
                }

            }

            $('#page-mod-quiz-summary .submitbtns .controls button[type="submit"]').on('click', function(e){
                e.stopImmediatePropagation();
            });
        },
        /**
         * Update background with image of motivator
         */
        update_background: function () {
            if ($('.ludi-section-container').hasClass('ludi-bg')) {
                var bgtype = $('.ludi-section-container').attr("class").match(/container-[\w-]*\b/)[0];
                //remove background for add it to top of course header
                $('.ludi-section-container').removeClass('ludi-bg');
                $('body.format-ludimoodle #page-header .card > .card-body').addClass('ludi-bg');
                $('body.format-ludimoodle #page-header .card > .card-body').addClass(bgtype);
            }
        },
        /**
         * Animate ludimoodle course header
         */
        animate_course_header: function () {
            setTimeout(function () {
                // scroll to top because animation is on course header
                $('html, body').scrollTop(0);
                // add bg background
                $('body').prepend('<div id="background-animation"></div>').fadeIn();
                // animate course header
                $('#course-header').addClass('shake');

                // animation is finish, remove bg and class
                setTimeout(function () {
                    $('#background-animation').fadeOut();
                    $('#course-header').removeClass('shake');
                }, 1000);
            }, 500);

        }

    };
    return ludicMotivators;
});