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
 * Renderer for outputting the ludimoodle course format.
 *
 * @package    course
 * @subpackage format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     celine <celine@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/format/renderer.php');
require_once($CFG->dirroot . '/course/format/ludimoodle/classes/renderers/renderable/motivators.php');
require_once($CFG->dirroot . '/course/renderer.php');

class format_ludimoodle_renderer extends format_section_renderer_base {

    private $env;

    /**
     * Constructor method, calls the parent constructor
     *
     * @param moodle_page $page
     * @param string $target one of rendering target constants
     */
    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);

        // Since format_ludimoodle_renderer::section_edit_controls() only displays the 'Set current section' control when editing mode is on
        // we need to be sure that the link 'Turn editing mode on' is available for a user who does not have any other managing capability.
        $page->set_other_editing_capability('moodle/course:setcurrentsection');

        $this->env = \format_ludimoodle\execution_environment::get_instance($page);
    }

    /**
     * Renderable definition for course_header function
     *
     * @param format_ludimoodle_motivators $motivators
     * @return bool|string
     * @throws moodle_exception
     */
    protected function render_format_ludimoodle_motivators(format_ludimoodle_motivators $motivators) {
        return $this->render_from_template('format_ludimoodle/motivators', $motivators);
    }

    /**
     * Render one section in section edition page
     */
    public function print_single_section_page($course, $sections, $mods, $modnames, $modnamesused, $displaysection) {
        global $PAGE, $USER;

        $output = '';

        $modinfo   = get_fast_modinfo($course);
        $course    = course_get_format($course)->get_course();
        $isadmin   = is_siteadmin();
        $context   = context_course::instance($course->id);
        $isteacher = \has_capability('format/ludimoodle:monitor', $context);

        // Can we view the section in question?
        if (!($sectioninfo = $modinfo->get_section_info($displaysection)) || !$sectioninfo->uservisible) {
            // This section doesn't exist or is not available for the user.
            // We actually already check this in course/view.php but just in case exit from this function as well.
            print_error('unknowncoursesection', 'error', course_get_url($course), format_string($course->fullname));
        }

        // if section has not motivator show it
        if ((!$isteacher || !$isadmin) && !$PAGE->user_is_editing()) {
            return $output;
        }

        // Start single-section div
        $output .= html_writer::start_tag('div', array('class' => 'single-section'));

        // The requested section page.
        $thissection = $modinfo->get_section_info($displaysection);

        // Title with section navigation links.
        $sectionnavlinks = $this->get_nav_links($course, $modinfo->get_section_info_all(), $displaysection);
        $sectiontitle    = '';
        $sectiontitle    .= html_writer::start_tag('div', array('class' => 'section-navigation navigationtitle'));
        $sectiontitle    .= html_writer::tag('span', $sectionnavlinks['previous'], array('class' => 'mdl-left'));
        $sectiontitle    .= html_writer::tag('span', $sectionnavlinks['next'], array('class' => 'mdl-right'));

        // Title attributes
        $classes = 'sectionname';
        if (!$thissection->visible) {
            $classes .= ' dimmed_text';
        }
        $sectionname  = html_writer::tag('span', $this->section_title_without_link($thissection, $course));
        $sectiontitle .= $this->output->heading($sectionname, 3, $classes);

        $sectiontitle .= html_writer::end_tag('div');
        $output       .= $sectiontitle;

        // Now the list of sections..
        $output .= $this->start_section_list();

        $output .= $this->section_header($thissection, $course, true, $displaysection);

        $output .= $this->courserenderer->course_section_cm_list($course, $thissection, $displaysection);
        $output .= $this->courserenderer->course_section_add_cm_control($course, $displaysection, $displaysection);
        $output .= $this->section_footer();
        $output .= $this->end_section_list();

        // Display section bottom navigation.
        $sectionbottomnav = '';
        $sectionbottomnav .= html_writer::start_tag('div', array('class' => 'section-navigation mdl-bottom'));
        $sectionbottomnav .= html_writer::tag('span', $sectionnavlinks['previous'], array('class' => 'mdl-left'));
        $sectionbottomnav .= html_writer::tag('span', $sectionnavlinks['next'], array('class' => 'mdl-right'));
        $sectionbottomnav .= html_writer::end_tag('div');
        $output           .= $sectionbottomnav;

        // Close single-section div.
        $output .= html_writer::end_tag('div');

        return $output;
    }

    /**
     * Render all sections in course edition page
     */
    public function print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused) {
        global $PAGE;

        $output    = '';
        $modinfo   = get_fast_modinfo($course);
        $course    = course_get_format($course)->get_course();
        $isadmin   = is_siteadmin();
        $context   = context_course::instance($course->id);
        $isteacher = \has_capability('format/ludimoodle:monitor', $context);

        // Title with completion help icon.
        $completioninfo = new completion_info($course);
        $output         .= $completioninfo->display_help_icon();
        $output         .= $this->output->heading($this->page_title(), 2, 'accesshide');

        // Copy activity clipboard..
        $output .= $this->course_activity_clipboard($course, 0);

        // Now the list of sections..
        $output         .= $this->start_section_list();
        $numsections    = course_get_format($course)->get_last_section_number();
        $sectioninfoall = $modinfo->get_section_info_all();

        foreach ($sectioninfoall as $section => $thissection) {

            // Show section only on editing mode
            if ((!$isteacher || !$isadmin) && !$PAGE->user_is_editing()) {
                continue;
            }

            if ($section == 0) {
                // 0-section is displayed a little different then the others
                if ($thissection->summary || !empty($modinfo->sections[0]) || $PAGE->user_is_editing()) {
                    $output .= $this->section_header($thissection, $course, false, 0);
                    $output .= $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                    $output .= $this->courserenderer->course_section_add_cm_control($course, 0, 0);
                    $output .= $this->section_footer();
                }
                continue;
            }

            if ($section > $numsections) {
                // activities inside this section are 'orphaned', this section will be printed as 'stealth' below
                continue;
            }
            // Show the section if the user is permitted to access it, OR if it's not available
            // but there is some available info text which explains the reason & should display,
            // OR it is hidden but the course has a setting to display hidden sections as unavilable.
            $showsection = $thissection->uservisible ||
                           ($thissection->visible && !$thissection->available && !empty($thissection->availableinfo)) ||
                           (!$thissection->visible && !$course->hiddensections);
            if (!$showsection) {
                continue;
            }

            if (!$PAGE->user_is_editing() && $course->coursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                // Display section summary only.
                $output .= $this->section_summary($thissection, $course, null);
            } else {
                $output .= $this->section_header($thissection, $course, false, 0);
                if ($thissection->uservisible) {
                    $output .= $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                    $output .= $this->courserenderer->course_section_add_cm_control($course, $section, 0);
                }
                $output .= $this->section_footer();
            }
        }

        if (has_capability('moodle/course:update', $context)) {
            // Print stealth sections if present.
            foreach ($sectioninfoall as $section => $thissection) {
                if ($section <= $numsections || empty($modinfo->sections[$section])) {
                    // this is not stealth section or it is empty
                    continue;
                }
                $output .= $this->stealth_section_header($section);
                $output .= $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                $output .= $this->stealth_section_footer();
            }

            $output .= $this->end_section_list();

            $output .= $this->change_number_sections($course, 0);
        } else {
            $output .= $this->end_section_list();
        }

        return $output;
    }

    /**
     * Render full course_header depending of the current location (course, section, cm)
     *
     * @return string
     */
    public function ludic_container() {
        $output = $this->env->render();
        return $output;
    }

    /**
     * Generate the starting container html for a list of sections
     *
     * @return string HTML to output.
     */
    protected function start_section_list() {
        return html_writer::start_tag('ul', array('class' => 'sections ludimoodle'));
    }

    /**
     * Generate the closing container html for a list of sections
     *
     * @return string HTML to output.
     */
    protected function end_section_list() {
        return html_writer::end_tag('ul');
    }

    /**
     * Generate the title for this section page
     *
     * @return string the page title
     */
    protected function page_title() {
        return get_string('topicoutline');
    }

    /**
     * Generate the section title, wraps it in a link to the section page if page is to be displayed on a separate page
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section));
    }

    /**
     * Generate the section title to be displayed on the section page, without a link
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title_without_link($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, false));
    }

    protected function section_header($section, $course, $onsectionpage, $sectionreturn = null) {
        global $PAGE;

        $o            = '';
        $sectionstyle = '';
        if ($section->section != 0) {
            // Only in the non-general sections.
            if (!$section->visible) {
                $sectionstyle = ' hidden';
            }
            if (course_get_format($course)->is_section_current($section)) {
                $sectionstyle = ' current';
            }
        }

        $o .= html_writer::start_tag('li', array(
                'id'         => 'section-' . $section->section,
                'class'      => 'section main clearfix' . $sectionstyle, 'role' => 'region',
                'aria-label' => get_section_name($course, $section)
        ));

        // Create a span that contains the section title to be used to create the keyboard section move menu.
        if ($section->section == 0) {
            $o .= html_writer::tag('span', get_section_name($course, $section), array('class' => 'hidden sectionname'));
        } else {
            $o .= html_writer::tag('span', get_section_name($course, $section), array('class' => 'sectionname'));
        }

        $leftcontent = $this->section_left_content($section, $course, $onsectionpage);
        $o           .= html_writer::tag('div', $leftcontent, array('class' => 'left side'));

        $rightcontent = $this->section_right_content($section, $course, $onsectionpage);
        $o            .= html_writer::tag('div', $rightcontent, array('class' => 'right side'));
        $o            .= html_writer::start_tag('div', array('class' => 'content'));

        // When not on a section page, we display the section titles except the general section if null
        $hasnamenotsecpg = (!$onsectionpage && ($section->section != 0 || !is_null($section->name)));

        // When on a section page, we only display the general section title, if title is not the default one
        $hasnamesecpg = ($onsectionpage && ($section->section == 0 && !is_null($section->name)));

        $classes = ' accesshide';
        if ($hasnamenotsecpg || $hasnamesecpg) {
            $classes = '';
        }
        $sectionname = html_writer::tag('span', $this->section_title($section, $course));

        if ($section->section != 0 && !$PAGE->user_is_editing()) {
            $o .= $this->output->heading($sectionname, 3, 'sectionname' . $classes);
        }

        $o .= $this->section_availability($section);

        $o .= html_writer::start_tag('div', array('class' => 'summary'));
        if ($section->uservisible || $section->visible) {
            // Show summary if section is available or has availability restriction information.
            // Do not show summary if section is hidden but we still display it because of course setting
            // "Hidden sections are shown in collapsed form".
            $o .= $this->format_summary_text($section);
        }

        $o .= html_writer::end_tag('div');

        return $o;
    }

    /**
     * Generate the edit control items of a section
     *
     * @param stdClass $course The course entry from DB
     * @param stdClass $section The course_section entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return array of edit control items
     */
    protected function section_edit_control_items($course, $section, $onsectionpage = false) {
        global $PAGE;

        if (!$PAGE->user_is_editing()) {
            return array();
        }

        $coursecontext = context_course::instance($course->id);

        if ($onsectionpage) {
            $url = course_get_url($course, $section->section);
        } else {
            $url = course_get_url($course);
        }
        $url->param('sesskey', sesskey());

        $controls = array();
        if ($section->section && has_capability('moodle/course:setcurrentsection', $coursecontext)) {
            if ($course->marker == $section->section) {  // Show the "light globe" on/off.
                $url->param('marker', 0);
                $highlightoff          = get_string('highlightoff');
                $controls['highlight'] = array(
                        'url'     => $url,
                        "icon"    => 'i/marked',
                        'name'    => $highlightoff,
                        'pixattr' => array('class' => ''),
                        'attr'    => array(
                                'class'       => 'editing_highlight',
                                'data-action' => 'removemarker'
                        )
                );
            } else {
                $url->param('marker', $section->section);
                $highlight             = get_string('highlight');
                $controls['highlight'] = array(
                        'url'     => $url,
                        "icon"    => 'i/marker',
                        'name'    => $highlight,
                        'pixattr' => array('class' => ''),
                        'attr'    => array(
                                'class'       => 'editing_highlight',
                                'data-action' => 'setmarker'
                        )
                );
            }
        }

        $parentcontrols = parent::section_edit_control_items($course, $section, $onsectionpage);

        // If the edit key exists, we are going to insert our controls after it.
        if (array_key_exists("edit", $parentcontrols)) {
            $merged = array();
            // We can't use splice because we are using associative arrays.
            // Step through the array and merge the arrays.
            foreach ($parentcontrols as $key => $action) {
                $merged[$key] = $action;
                if ($key == "edit") {
                    // If we have come to the edit key, merge these controls here.
                    $merged = array_merge($merged, $controls);
                }
            }

            return $merged;
        } else {
            return array_merge($controls, $parentcontrols);
        }
    }

    /**
     * Generate the content to displayed on the right part of a section
     * before course modules are included
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return string HTML to output.
     */
    protected function section_right_content($section, $course, $onsectionpage) {
        $o        = $this->output->spacer();
        $controls = $this->section_edit_control_items($course, $section, $onsectionpage);
        $o        .= $this->section_edit_control_menu($controls, $course, $section);

        return $o;
    }

    /**
     * In a course view or section view, show a message to help student in navigation
     *
     * @return string
     * @throws coding_exception
     */
    public function render_ludi_navigation_help() {
        $output = '';
        if ($this->env->is_user_admin() || $this->env->is_teacher()) {
            return $output;
        }
        $currentlocation = $this->env->get_current_location();
        if ($currentlocation == 'course') {
            $output .= '<h2 class="ludi-how-to-nav course">' . get_string('how-to-nav-in-course', 'format_ludimoodle') . '</h2>';
        } else if ($currentlocation == 'section') {
            $output .= '<h2 class="ludi-how-to-nav section">' . get_string('how-to-nav-in-section', 'format_ludimoodle') . '</h2>';
        }
        return $output;
    }

}
