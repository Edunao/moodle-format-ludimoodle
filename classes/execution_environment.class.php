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
 *
 *
 * @package    format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     celine <celine@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ludimoodle;

defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/course/format/ludimoodle/classes/data_mine.class.php';

/**
 *  The goal of this class is to provide isolation from the outside world.
 *  It should be possible to implement the different units behind this class as stubs for testing purposes
 */
class execution_environment {

    // Singleton
    public static $instance;

    // Environment properties
    public  $page            = null;
    private $datamine        = null;
    private $courseid        = null;
    private $sectionid       = null;
    private $sectionidx      = null;
    private $sectioninfo     = null;
    private $section         = null;
    private $sections        = null;
    private $cminfo          = null;
    private $cmsinfo         = null;
    private $modinfo         = null;
    private $allmotivators   = null;
    private $user            = null;
    private $currentlocation = null;
    private $motivatoradded  = null;
    private $jsinitdata      = [];
    public  $cssinitdata     = [];
    public  $nonemotivator   = 'none';
    public  $nomotivator     = 'nomotivator';

    /**
     * execution_environment constructor.
     * Initialize motivator
     *
     * @param \moodle_page $page
     */
    public function __construct(\moodle_page $page) {
        global $USER;
        $this->page = $page;
        $this->user = $USER;
        $this->initialize_motivator();
    }

    /**
     * @param \moodle_page $page
     * @return execution_environment
     */
    public static function get_instance(\moodle_page $page) {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self($page);
        }
        return self::$instance;
    }

    //-------------------------------------------------------------------------
    // Moodle context

    /**
     * @return \moodle_page
     */
    public function get_page() {
        return $this->page;
    }

    /**
     * @return int
     */
    public function get_userid() {
        return $this->user->id;
    }

    /**
     * @return int
     */
    public function get_course_id() {
        return $this->courseid === null ? $this->page->course->id : $this->courseid;
    }

    /**
     * @return int
     */
    public function get_cm_id() {
        return isset($this->page->cm->id) ? $this->page->cm->id : 0;
    }

    /**
     * @return int
     */
    public function get_attempt_id() {
        return $this->page->url->param('attempt') ? $this->page->url->param('attempt') : 0;
    }

    /**
     * @return int
     */
    public function get_attempt_page() {
        return $this->page->url->param('page') ? $this->page->url->param('page') : 0;
    }

    /**
     * @return int
     * @throws \dml_exception
     */
    public function get_section_idx() {
        global $DB;
        if ($this->sectionidx === null) {
            if ($this->page->url->param('section') > 0) {
                $this->sectionidx = $this->page->url->param('section');
            } else if (isset($this->page->cm->section)) {
                // we're in an activity that is declaring its section id so we need to lookup the corresponding course-relative index
                $sectionid        = $this->page->cm->section;
                $this->sectionidx = $sectionid ? $DB->get_field('course_sections', 'section', ['id' => $sectionid]) : 0;
            } else {
                $this->sectionidx = -1;
            }
        }

        return $this->sectionidx;
    }

    /**
     * @return int
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_section_id() {
        // if we haven't got a stored section id then try generating one
        if ($this->sectionid === null) {
            $coursesection = optional_param('section', 0, PARAM_INT);
            if ($this->page->pagetype == 'course-view-ludimoodle') {
                // we're on a course view page and the course-relative section number is provided so lookup the real section id
                global $DB;
                $this->sectionid = $coursesection ? $DB->get_field('course_sections', 'id', [
                        'course' => $this->page->course->id, 'section' => $coursesection
                ]) : 0;
            } else if (isset($this->page->cm->section)) {
                // we're in an activity that is declaring its section id so we're in luck
                $this->sectionid = $this->page->cm->section;
            } else {
                // no luck so replace the null with a 0 to avoid wasting times on trying to re-evaluate next time round
                $this->sectionid = $coursesection;
            }
        }
        // return the stored result
        return $this->sectionid;
    }

    /**
     * @return string
     */
    public function get_course_fullname() {
        return $this->page->course->fullname;
    }

    /**
     * @return string
     */
    public function get_section_fullname() {
        $section = $this->get_current_section();
        return $section->name;
    }

    /**
     * @return string
     */
    public function get_cm_fullname() {
        return isset($this->page->cm->name) ? $this->page->cm->name : '';
    }

    /**
     * @return object section_info
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_current_section() {
        if ($this->section == null) {
            $this->section = $this->get_section_info();
        }
        return $this->section;
    }

    /**
     * @return array of course sections with a lot of data
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_course_sections() {
        global $DB;

        if ($this->sections === null) {

            $this->sections = [];
            $modinfo        = $this->get_modinfo();
            $cms            = $this->get_cms_info();

            // Prepare sections list
            $sectionrecords = $DB->get_records_sql('
                SELECT cs.id, cs.section, cs.visible, cs.name, cs.sequence, sc.config as motivconfig, cs.course
                FROM {course_sections} cs 
                LEFT JOIN {ludimoodle_sectionconf} sc ON sc.sectionid = cs.id
                WHERE cs.course = :course
                AND cs.section > 0
                ORDER BY cs.section
            ', array('course' => $this->get_course_id()));

            // Fixup the records to make sure that they are complete
            foreach ($sectionrecords as $section) {
                $section->name     = $section->name != "" ? $section->name : 'Section ' . $section->section;
                $section->sequence = explode(',', $section->sequence);

                $sectioninfo                      = $modinfo->get_section_info($section->section);
                $section->uservisible             = $sectioninfo->uservisible;
                $section->sectioncanhavemotivator = isset($sectioninfo->sectioncanhavemotivator) ?
                        $sectioninfo->sectioncanhavemotivator : true;

                // For the experimentation, a motivator is relative to a user and section
                $motivator          = $this->get_user_section_motivator($section->id);
                $section->motivator = $motivator;
                $section->presets   = $this->get_default_presets_by_motivator($motivator);

                $sequenceidx = 0;
                foreach ($section->sequence as $cmid) {
                    if (isset($cms[$cmid])) {
                        $sequenceidx++;

                        $cm      = $cms[$cmid];
                        $cm->idx = $sequenceidx;

                        // For the experimentation, a motivator is relative to a user and section
                        $cm->motivator = $section->motivator;
                        $cm->presets   = $section->presets;

                        $section->cms[] = $cm;
                    }
                }

                $this->sections[$section->section] = $section;
            }
        }
        return $this->sections;
    }

    /**
     * @return object section info
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_section_info() {

        // if the value of the attribute has already been retrieved then we return it
        if ($this->sectioninfo !== null) {
            return $this->sectioninfo;
        }

        global $DB;

        // Get data
        $sectionid = $this->get_section_id();

        $section = $DB->get_record('course_sections', array('id' => $sectionid));
        if (empty($section->name)) {
            $section->name = get_string('sectionname', 'format_ludimoodle') . $section->section;
        }
        $section->sequence = explode(',', $section->sequence);

        // For the experimentation, a motivator is relative to a user and section
        $motivator          = $this->get_user_section_motivator($sectionid);
        $section->motivator = $motivator;
        $section->presets   = $this->get_default_presets_by_motivator($motivator);

        $this->sectioninfo = $section;
        return $this->sectioninfo;
    }

    /**
     * @return array of object cminfo
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_cms_info() {
        // if the value of the attribute has already been retrieved then we return it
        if ($this->cmsinfo !== null) {
            return $this->cmsinfo;
        }

        global $DB;

        $this->cmsinfo = [];

        $cms = $DB->get_records_sql('
            SELECT cm.id, cm.id as cmid, cm.instance, m.name as modname, cm.section, cm.visible, cc.config as motivconfig
            FROM {course_modules} cm
            JOIN {modules} m ON m.id = cm.module
            LEFT JOIN {ludimoodle_cmconfig} cc ON cc.cmid = cm.id
            WHERE cm.course = ?
            AND cm.deletioninprogress = 0
        ', array($this->get_course_id()));

        $modinfo = $this->get_modinfo();
        foreach ($cms as $cmid => $cminfo) {
            if ($cminfo->visible == 1) {
                $cm                   = $modinfo->get_cm($cmid);
                $cminfo->name         = $cm->name;
                $cminfo->visible      = !$cm->uservisible ? 0 : 1;
                $this->cmsinfo[$cmid] = $cminfo;
            }
        }

        return $this->cmsinfo;
    }

    /**
     * @return object|false : current cminfo with a lot of data
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_cm_info() {
        // if the value of the attribute has already been retrieved then we return it
        if ($this->cminfo !== null) {
            return $this->cminfo;
        }
        global $DB;

        $cmid = $this->get_cm_id();

        $cminfo = $DB->get_record_sql('
                SELECT cm.id, cm.id as cmid, cm.instance, m.name as modname, cm.section, cm.visible, cc.config as motivconfig
                FROM {course_modules} cm
                JOIN {modules} m ON m.id = cm.module
                LEFT JOIN {ludimoodle_cmconfig} cc ON cc.cmid = cm.id
                WHERE cm.id = ?
                AND cm.deletioninprogress = 0
            ', array($cmid));

        if (!$cminfo) {
            $this->cminfo = false;
            return $this->cminfo;
        }

        $modinfo         = $this->get_modinfo();
        $cm              = $modinfo->get_cm($cmid);
        $cminfo->name    = $cm->name;
        $cminfo->visible = !$cm->uservisible ? 0 : 1;

        // In experimentation the cm motivator is taken from section
        $motivator         = $this->get_user_section_motivator();
        $cminfo->motivator = $motivator;
        $cminfo->presets   = $this->get_default_presets_by_motivator($motivator);

        if ($this->cmsinfo !== null) {
            $this->cmsinfo[$cmid] = $cminfo;
        }

        $this->cminfo = $cminfo;

        return $this->cminfo;
    }

    /**
     * @return \course_modinfo
     * @throws \moodle_exception
     */
    public function get_modinfo() {
        global $COURSE;

        if ($this->modinfo === null) {
            $this->modinfo = get_fast_modinfo($COURSE);
        }

        return $this->modinfo;
    }

    /**
     * @return data_mine
     */
    public function get_data_mine() {
        if ($this->datamine === null) {
            $this->datamine = new data_mine();
        }
        return $this->datamine;
    }

    /**
     * Return where the user is in course - course / section / mod
     *
     * @return string
     * @throws \dml_exception
     */
    public function get_current_location() {
        if ($this->currentlocation !== null) {
            return $this->currentlocation;
        }
        $cmid       = $this->get_cm_id();
        $sectionidx = $this->get_section_idx();

        // course page by default
        $currentlocation = 'course';

        if ($cmid > 0) {
            $currentlocation = 'mod';
        } else if ($sectionidx > 0) {
            $currentlocation = 'section';
        }

        $this->currentlocation = $currentlocation;

        return $this->currentlocation;
    }

    /**
     * @return bool
     */
    public function is_user_admin() {
        return is_siteadmin($this->get_userid());
    }

    /**
     * @return bool
     * @throws \coding_exception
     */
    public function is_teacher() {
        $coursecontext = \context_course::instance($this->get_course_id());
        return \has_capability('format/ludimoodle:monitor', $coursecontext);
    }

    /**
     * checks if the current page type is part of an array of page types
     *
     * @param $pagetypes
     * @return bool
     */
    public function is_page_type_in($pagetypes) {
        $currenttype = $this->page->pagetype;
        foreach ($pagetypes as $type) {
            if ($type === $currenttype) {
                return true;
            }
        }
        return false;
    }

    //-------------------------------------------------------------------------
    // Render method

    /**
     * Main render method, render all that is expected to user depending of current location
     *
     * @return string
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function render() {
        // Header
        $output = '<div class="ludimoodle-overlay"></div>';
        $output .= $this->render_nav_buttons();

        // If the current user is not a student, nothing is displayed excepted report buttons
        if ($this->is_user_admin() || $this->is_teacher()) {
            $output .= $this->render_report_buttons();
        } else {

            // Current user is a student so render current view
            $currentlocation = $this->get_current_location();

            switch ($currentlocation) {
                // We are in a section page
                case 'section':
                    $output .= $this->render_section_view();
                    break;

                // We are in a mod page
                case 'mod':
                    $output .= $this->render_mod_view();

                    // For navigation
                    $output .= $this->render_section_view();
                    break;
            }

            // Always display course view for navigation
            $output .= $this->render_course_view();
        }

        return $output;
    }

    /**
     * Return html for module view
     *
     * @return string
     */
    public function render_mod_view() {
        $output          = '';
        $cm              = $this->get_cm_info();
        $section         = $this->get_section_info();
        $motivatorname   = $cm->motivator;
        $motivator       = $this->create_motivator($motivatorname, ['presets' => $cm->presets]);
        $currentlocation = $this->get_current_location();

        // course, section and mod views are returned but only the current view is visible
        $classes = $currentlocation == 'mod' ? 'show ' : '';
        $classes .= 'container-' . $section->motivator . ' ';

        $output .= '<div class="ludi-mod-container ludi-main-container ' . $classes . '" data-type="mod">';
        $output .= '<div class="ludi-mod-view motivator-' . $cm->motivator . '">';
        // Delegate render to motivator renderer
        $output .= $motivator->render_mod_view($this, $cm);
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Return html for current section view (section motivator and each modules motivator)
     *
     * @return string
     */
    public function render_section_view() {
        global $CFG;
        $output = '';

        // Get data
        $section         = $this->get_section_info();
        $cms             = $this->get_cms_info();
        $motivatorname   = $section->motivator;
        $motivator       = $this->create_motivator($motivatorname, ['presets' => $section->presets]);
        $currentlocation = $this->get_current_location();

        // Course, section and mod views are returned but only the current view is visible
        $classes = 'container-' . $motivatorname . ' ';
        $classes .= $currentlocation == 'section' ? 'show ' : '';

        $output .= '<div class="ludi-section-container ludi-main-container ludi-bg ' . $classes . '" data-type="section">';

        $classes = 'ludi-' . $section->motivator . ' ';
        $classes .= $this->motivatoradded ? 'first-access ' : '';

        $output .= '<div class="ludi-details-view ' . $classes . ' ">';

        // Delegate render to motivator renderer
        $output .= '<div class="summary-element ' . $classes . '">';
        $output .= $motivator->render_section_view($this, $section);
        $output .= '</div>';

        // For each section modules, delegate render to motivator renderer
        $output .= '<div class="ludi-grid-view ludi-' . $section->motivator . '">';

        foreach ($section->sequence as $cmid) {
            if (!isset($cms[$cmid])) {
                continue;
            }
            $classes = '';
            $cm      = $cms[$cmid];
            $cmurl   = $CFG->wwwroot . '/mod/' . $cm->modname . '/view.php?id=' . $cm->id;

            if ($cm->visible == 0) {
                $classes .= "mod-hidden ";
            }
            if (!$this->user_has_displayed_cm($cm->id)) {
                $classes .= "never-acceded ";
            }

            // Delegate render to motivator renderer
            $output .= '<div class="mod-motivator grid-element ' . $classes . '">';
            $output .= '<a href="' . $cmurl . '">';
            $output .= '<div class="element-content motivator-' . $motivatorname . '">';

            // Render motivator mod view
            // If there is a method 'render_summary_mod_view', it's used instead.
            if (method_exists($motivator, 'render_summary_mod_view')) {
                $output .= $motivator->render_summary_mod_view($this, $cm);
            } else {
                $output .= $motivator->render_mod_view($this, $cm);
            }

            $output .= '</div>';
            $output .= '<div class="element-title">' . $cm->name . '</div>';
            $output .= '</a>';
            $output .= '</div>';
        }

        $output .= '</div>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Return html for course view
     *
     * @return string
     */
    public function render_course_view() {
        global $CFG;

        $output = '';

        $sections = $this->get_course_sections();

        // Course, section and mod views are returned but only the current view is visible
        $currentlocation = $this->get_current_location();
        $classes         = $currentlocation == 'course' ? 'show ' : '';

        $output .= '<div class="ludi-course-container ludi-main-container ' . $classes . '" data-type="course">';
        $output .= '<div class="ludi-course-view ludi-grid-view">';

        $courseurl = $CFG->wwwroot . '/course/view.php?id=' . $this->get_course_id();

        foreach ($sections as $section) {

            // Don't show hidden section
            if (!$section->visible || !$section->uservisible) {
                continue;
            }

            $sectionurl    = $courseurl . '&section=' . $section->section;
            $motivatorname = $section->motivator;
            $motivator     = $this->create_motivator($motivatorname, ['presets' => $section->presets]);

            $output .= '<div class="grid-element ' . $motivatorname . '">';
            $output .= '<a href="' . $sectionurl . '">';

            $output .= '<div class="element-content">';

            // If there is a method 'render_summary_section_view', it's used instead.
            if (method_exists($motivator, 'render_summary_section_view')) {
                $output .= $motivator->render_summary_section_view($this, $section);
            } else {
                $output .= $motivator->render_section_view($this, $section);
            }

            $output .= '</div>';

            // Add section title
            $output .= '<div class="element-title">' . $section->name . '</div>';

            $output .= '</a>';
            $output .= '</div>';
        }

        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Returns the explanation window of the motivator with the description of the motivator
     *
     * @param $motivator string (name of the motivator)
     * @return string
     * @throws \coding_exception
     */
    private function render_motivator_explanation($motivator) {
        global $OUTPUT;
        $motivatorname = get_string($motivator, 'format_ludimoodle');
        // change after experimentation
        if ($motivator == $this->nomotivator) {
            $title = $motivatorname;
        } else if ($motivator == $this->nonemotivator) {
            $title = get_string('motivator-explanation-title', 'format_ludimoodle');
        } else {
            $title = get_string('motivator-explanation-title', 'format_ludimoodle') . ' : ' . $motivatorname;
        }
        $output = '<div id="motivator-explanation">';
        $output .= '<div id="motivator-explanation-header">';
        $output .= '<div id="motivator-explanation-close">';
        $output .= '<img src="' . $OUTPUT->image_url('button-close', 'format_ludimoodle') . '">';
        $output .= '</div>'; // motivator-explanation-close
        $output .= '</div>'; // motivator-explanation-header
        $output .= '<div id="motivator-explanation-content">';
        $output .= '<h3>' . $title . '</h3>';
        $output .= '<p>' . get_string($motivator . '-description', 'format_ludimoodle') . '</p>';
        $output .= '</div>'; // motivator-explanation-content
        $output .= '</div>'; // motivator-explanation
        return $output;
    }

    /**
     * Render navigation buttons and motivator explanation window
     *
     * @return string
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    private function render_nav_buttons() {
        global $OUTPUT, $CFG;
        $currentlocation   = $this->get_current_location();
        $courseid          = $this->get_course_id();
        $title             = $this->get_course_fullname();
        $sections          = $this->get_course_sections();
        $motivator         = $this->nonemotivator;
        $sectionidx        = 1;
        $cm                = null;
        $buttons['course'] = $CFG->wwwroot . '/course/view.php?id=' . $courseid;

        // Defined the title and the navigation links according to the current location (course, section, mod)
        switch ($currentlocation) {

            // We are in the main course page
            case 'course':
                foreach ($sections as $section) {
                    if ($section->visible == 1) {
                        // By default we retrieve the last visible section in course
                        $sectionidx = $section->section;

                        // In the last visible section we retrieve the first activity
                        $cm = isset($section->cms[0]) ? $section->cms[0] : $cm;
                    }
                }
                break;

            // We are in a section page
            case 'section':
                $motivator  = $this->get_user_section_motivator();
                $title      = $this->get_section_fullname();
                $sectionidx = $this->get_section_idx();
                $section    = $sections[$sectionidx];
                // By default we retrieve the first activity
                $cm = isset($section->cms[0]) ? $section->cms[0] : null;
                break;

            // We are in a mod page
            case 'mod':
                $motivator  = $this->get_user_section_motivator();
                $title      = $this->get_cm_fullname();
                $cm         = $this->get_cm_info();
                $sectionidx = $this->get_section_idx();
                break;

        }

        $buttons['section'] = $CFG->wwwroot . '/course/view.php?id=' . $courseid . '&section=' . $sectionidx;
        if (!empty($cm)) {
            $buttons['mod'] = $CFG->wwwroot . '/mod/' . $cm->modname . '/view.php?id=' . $cm->id;
        }
        $buttons['motivator-explanation'] = '#motivator-explanation';


        $output = $this->render_motivator_explanation($motivator);
        $output .= '<div class="ludi-nav-bar">';
        $output .= '<h4 class="ludi-nav-title">' . $title . '</h4>';
        $output .= '<div class="ludi-navigation">';
        foreach ($buttons as $name => $link) {
            $class = $name;
            if ($currentlocation == $name) {
                $class .= ' active';
            }
            $strname = $name == 'motivator-explanation' ? '' : get_string('nav-' . $name, 'format_ludimoodle');
            $datamotivator = $name == 'motivator-explanation' ? ' data-motivator="'. $motivator .'"' : '';

            $output .= '<a class="nav-buttons ' . $class . '" href="' . $link . '" data-type="' . $name . '"'.$datamotivator.'>';

            $output .= '<img src="' . $OUTPUT->image_url('nav-' . $name, 'format_ludimoodle') . '" title="' . $strname . '" data-type="' . $name . '">';

            if (!empty($strname)) {
                $output .= '<span class="ludi-nav-option-title">' . $strname . '</span>';
            }
            $output .= '</a>';
        }
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render report buttons for teacher and admin in experimentation
     *
     * @return string
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function render_report_buttons() {
        global $CFG;
        $courseid           = $this->get_course_id();
        $reporturl          = $CFG->wwwroot . '/course/format/ludimoodle/reports/quiz_report.php?id=' . $courseid;
        $activereporturl    = $CFG->wwwroot . '/course/format/ludimoodle/reports/quiz_report.php?id=' . $courseid .
                              '&report=active';
        $btnreportstr       = get_string('btn-report', 'format_ludimoodle');
        $btnactivereportstr = get_string('btn-activereport', 'format_ludimoodle');
        $lessonisopen       = optional_param('lessonisopen', null, PARAM_RAW);
        if ($lessonisopen !== null) {
            $config = get_config('format_ludimoodle');
            $cohort = isset($config->lessonisopen) ? $config->lessonisopen : false;
            if (!$lessonisopen && $cohort) {
                $oldvalue = isset($config->mustchoosemotivator) ? $config->mustchoosemotivator : false;
                if (!empty($oldvalue)) {
                    $newvalue = $oldvalue . ',' . $cohort;
                } else {
                    $newvalue = $cohort;
                }
                set_config('mustchoosemotivator', $newvalue, 'format_ludimoodle');
            }
            set_config('lessonisopen', $lessonisopen, 'format_ludimoodle');
        }

        $output = '<div class="ludi-report-buttons-container">';
        $output .= '<div class="ludi-report-buttons">';

        // For experimentation
        // 1 - selector cohort / none if lesson is open
        $output .= $this->experimentation_render_cohorts_selector();

        // For experimentation
        // 2 - open / close lesson - choose motivators
        $output .= $this->experimentation_render_dynamic_buttons();

        // 3 - reports
        $output .= '<div class="buttons-group">';
        $output .= '<p class="teacher-link"><a href="' . $activereporturl . '"><button class="btn">' . $btnactivereportstr .
                   '</button></a></p>';
        $output .= '<p class="teacher-link"><a href="' . $reporturl . '"><button class="btn">' . $btnreportstr .
                   '</button></a></p>';

        if ($this->is_user_admin()) {
            $reseturl = $CFG->wwwroot . '/course/format/ludimoodle/reset_course_achievements.php?id=' . $courseid;
            $resetstr = get_string('btn-reset-achievements', 'format_ludimoodle');
            $output   .= '<p class="teacher-link"><a href="' . $reseturl . '"><button class="btn">' . $resetstr .
                         '</button></a></p>';
        }
        $output .= '</div>';
        $output .= '</div>';
        $output .= '</div>';
        return $output;
    }

    //-------------------------------------------------------------------------
    // Motivators

    /**
     * Set the nomotivator motivator for sections that cannot have a motivator (format setting)
     * Set the motivator on the current section from the value of the nextmotivator profile field
     */
    private function initialize_motivator() {
        // True if a motivator has been added
        $this->motivatoradded = false;

        $currentlocation = $this->get_current_location();

        // If we are on a section or mod page initialize section motivator
        if ($currentlocation == 'section' || $currentlocation == 'mod') {
            $sectionid     = $this->get_section_id();
            $motivator     = $this->get_user_section_motivator($sectionid);
            $nextmotivator = $this->get_user_nextmotivator();

            // For the experimentation, a motivator is relative to a user and section
            // Check if section can have a motivator
            $sectioncanhavemotivator = $this->section_can_have_motivator();

            // Section can have a motivator, if user doesn't have a motivator set motivator value from nextmotivator profile field
            if ($sectioncanhavemotivator && $motivator == $this->nonemotivator && $nextmotivator != $this->nonemotivator) {
                $this->motivatoradded = true;
                $this->set_user_section_motivator($sectionid, $this->text_to_int($nextmotivator));
            }
        }

        // Check on each section if it can have a motivator, if it cannot we add the motivator nomotivator by default
        $sections = $this->get_course_sections();

        foreach ($sections as $section) {
            $motivator = $this->get_user_section_motivator($section->id);
            // This section can't have motivator, so set motivator to nomotivator if you haven't already
            if (!$section->sectioncanhavemotivator && $motivator != $this->nomotivator) {
                $nomotivatorvalue = $this->text_to_int($this->nomotivator);
                $this->set_user_section_motivator($section->id, $nomotivatorvalue);
                $section->motivator = $this->nomotivator;
                $section->presets   = $this->get_default_presets_by_motivator($this->nomotivator);
            }

        }

        // Update sections in cache
        $this->sections = $sections;
    }

    /**
     * Create motivator and return object
     *
     * @param $name
     * @param $instanceconfig
     * @return object
     */
    public function create_motivator($name, $instanceconfig) {
        global $CFG;
        $motivatormainfile = $CFG->dirroot . '/course/format/ludimoodle/motivators/' . $name . '/main.php';

        // If the motivator does not exist, use the none motivator instead
        if (!file_exists($motivatormainfile)) {
            $motivatormainfile = $CFG->dirroot . '/course/format/ludimoodle/motivators/' . $this->nonemotivator . '/main.php';
        }

        require_once $motivatormainfile;
        $classname = __NAMESPACE__ . '\\' . 'motivator_' . $name;
        $motivator = new $classname($instanceconfig);

        return $motivator;
    }

    /**
     * Return user section motivator
     *
     * @param null $sectionid , if sectionid is null use page sectionid instead
     * @return string
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_user_section_motivator($sectionid = null) {
        $sectionid = $sectionid === null ? $this->get_section_id() : $sectionid;
        if (!is_numeric($sectionid) || $sectionid == 0) {
            throw new \moodle_exception('Invalid sectionid');
        }

        $datamine  = $this->get_data_mine();
        $userid    = $this->get_userid();
        $courseid  = $this->get_course_id();
        $nonevalue = $this->text_to_int($this->nonemotivator);
        $motivator = $datamine->get_user_section_achievement($userid, $courseid, $sectionid, 'motivator', $nonevalue);
        $motivator = $this->motivator_key_to_motivator_name($motivator);
        return $motivator;
    }

    /**
     * Set user section motivator
     *
     * @param $sectionid int, if sectionid is null use page sectionid instead
     * @param $motivator int, int value of the name of the motivator
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function set_user_section_motivator($sectionid, $motivator) {
        if (!is_numeric($sectionid) || $sectionid == 0) {
            throw new \moodle_exception('Invalid sectionid');
        }

        $datamine = $this->get_data_mine();
        $userid   = $this->get_userid();
        $courseid = $this->get_course_id();
        $datamine->set_user_section_achievement($userid, $courseid, $sectionid, 'motivator', $motivator);
    }

    /**
     * @return string
     * @throws \dml_exception
     */
    public function get_user_nextmotivator() {
        global $DB;
        $nextmotivator = $DB->get_field_sql('SELECT uid.data
                 FROM {user} AS u
                 JOIN {user_info_data}   AS uid ON u.id = uid.userid
                 JOIN {user_info_field}  AS uif ON uid.fieldid = uif.id
                 WHERE u.id = :userid
                 AND uif.shortname = "nextmotivator"', ['userid' => $this->get_userid()]);

        // initialize a new motivator for the user if he does'nt have it
        if (!$nextmotivator) {
            $fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => 'nextmotivator']);
            $data    = (object) [
                    'userid' => $this->get_userid(), 'fieldid' => $fieldid, 'data' => $this->nonemotivator, 'dataformat' => 0
            ];
            $DB->insert_record('user_info_data', $data);
            $nextmotivator = $this->nonemotivator;
        }

        return $nextmotivator;
    }

    /**
     * Check if a section can have a motivator (defined in section parameter)
     *
     * @return bool
     * @throws \dml_exception
     */
    public function section_can_have_motivator() {
        global $DB;
        $courseid  = $this->get_course_id();
        $sectionid = $this->get_section_id();

        // This value is defined in course format options
        $sectioncanhavemotivator = $DB->get_field_sql('
                SELECT value
                FROM {course_format_options}
                WHERE courseid=:courseid
                  AND format="ludimoodle"
                  AND name="sectioncanhavemotivator"
                  AND sectionid=:sectionid
            ', [
                'courseid' => $courseid, 'sectionid' => $sectionid
        ]);

        // no record found, by default yes
        return $sectioncanhavemotivator === false ? true : (bool) $sectioncanhavemotivator;
    }

    /**
     * @return array of all motivators
     */
    public function get_motivators_list() {
        global $CFG;

        if ($this->allmotivators === null) {
            $motivatorspath = $CFG->dirroot . '/course/format/ludimoodle/motivators';
            $motivatorsdir  = scandir($motivatorspath);

            $motivators = [];
            foreach ($motivatorsdir as $key => $dirname) {
                if (!in_array($dirname, array(
                        ".", "..", "none"
                ))) {

                    if (!file_exists($motivatorspath . '/' . $dirname . '/main.php')) {
                        continue;
                    }
                    require_once $motivatorspath . '/' . $dirname . '/main.php';

                    $classname                             = __NAMESPACE__ . '\\' . 'motivator_' . $dirname;
                    $validcontexts                         = $classname::get_valid_contexts();
                    $motivators[$dirname]['validcontexts'] = $validcontexts;
                    if (in_array('cm', $validcontexts)) {
                        $validcms                         = $classname::get_valid_modules();
                        $motivators[$dirname]['validcms'] = $validcms;
                    } else {
                        $motivators[$dirname]['validcms'] = [];
                    }

                    // Get presets
                    $presets               = $classname::get_all_presets();
                    $defaultvisualpreset   = $classname::get_default_visual_preset();
                    $defaultbehaviorpreset = $classname::get_default_behavior_preset();
                    if ($defaultvisualpreset) {
                        $motivators[$dirname]['defaultpresets']['visual'] = $defaultvisualpreset;
                    }
                    if ($defaultbehaviorpreset) {
                        $motivators[$dirname]['defaultpresets']['behavior'] = $defaultbehaviorpreset;
                    }
                    $motivators[$dirname]['presets'] = $presets;
                    $motivators[$dirname]['iconurl'] = $CFG->wwwroot . "/course/format/ludimoodle/motivators/" . $dirname .
                                                       "/pix/icon.svg";
                }
            }

            $this->allmotivators = $motivators;
        }

        return $this->allmotivators;
    }

    /**
     * Return complete motivator name from a int key (8 CHAR) based on hexadecimal value
     *
     * @param $intkey
     * @return string
     */
    private function motivator_key_to_motivator_name($intkey) {
        if (!is_numeric($intkey) || empty($intkey)) {
            throw new \moodle_exception('Expected to receive an int');
        }
        $strkey = $this->int_to_text($intkey);
        if (strlen($strkey) == 8) {
            // find complete name of motivator
            $motivators = $this->get_motivators_list();
            foreach ($motivators as $motivatorname => $motivator) {
                if ($strkey == substr($motivatorname, 0, 8)) {
                    $strkey = $motivatorname;
                }
            }
        }
        return $strkey;
    }

    /**
     * Return default presets of a motivator
     *
     * @param $motivator
     * @return object
     */
    public function get_default_presets_by_motivator($motivator) {
        $presets    = [];
        $motivators = $this->get_motivators_list();
        if (isset($motivators[$motivator]['defaultpresets']['behavior'])) {
            $presets['behavior'] = $motivators[$motivator]['defaultpresets']['behavior'];
        }
        if (isset($motivators[$motivator]['defaultpresets']['visual'])) {
            $presets['visual'] = $motivators[$motivator]['defaultpresets']['visual'];
        }
        return (object) $presets;
    }

    //-------------------------------------------------------------------------
    // Utils

    /**
     * Set js data for a motivator
     *
     * @param $motivatorname
     * @param $data
     */
    public function set_js_init_data($motivatorname, $data) {
        global $CFG;
        $jsfile = $CFG->dirroot . '/course/format/ludimoodle/motivators/' . $motivatorname . '/motivator.js';
        if (file_exists($jsfile)) {
            if (array_key_exists($motivatorname, $this->jsinitdata)) {
                $this->jsinitdata[$motivatorname] = array_merge_recursive($this->jsinitdata[$motivatorname], $data);
            } else {
                $this->jsinitdata[$motivatorname] = $data;
            }
        }
    }

    /**
     * Get js data (data of all motivator)
     *
     * @return array
     */
    public function get_js_init_data() {
        return $this->jsinitdata;
    }

    /**
     * Check in moodle log if user has already acceded to activity or not
     * À améliorer en utilisant les achievements, (set au premier accès et check après)
     *
     * @param $cmid
     * @return bool
     * @throws \dml_exception
     */
    private function user_has_displayed_cm($cmid) {
        global $DB, $USER;
        $sql
                = "
            SELECT id
            FROM {logstore_standard_log}
            WHERE userid = :userid
            AND contextlevel = :contextlevel
            AND action = :action
            AND origin = :origin
        ";
        if (is_array($cmid)) {
            $incmid = implode("','", $cmid);
            $sql    .= " AND contextinstanceid IN('$incmid')";
        } else {
            $sql .= " AND contextinstanceid = $cmid";
        }

        return $DB->record_exists_sql($sql, [
                'userid' => $USER->id, 'contextlevel' => CONTEXT_MODULE, 'action' => 'viewed', 'origin' => 'web'
        ]);
    }

    /**
     * Convert a text (only 8 first CHAR) to int based on hexadecimal value
     *
     * @param $str
     * @return int
     */
    public function text_to_int($str) {
        $result = 0;
        for ($i = 7; $i >= 0; $i--) {
            $c      = ($i < strlen($str)) ? $str[$i] : "\x0";
            $b      = ord($c);
            $result = ($result << 8) + $b;
        }
        return $result;
    }

    /**
     * Convert a int to text (8 CHAR) from hexadecimal
     *
     * @param $int
     * @return string
     */
    public function int_to_text($int) {
        $result = '';
        $value  = $int;
        for ($i = 0; $i < 8; $i++) {
            $b     = $value % 256;
            $value = $value >> 8;
            if ($b != 0) {
                $c      = chr($b);
                $result .= $c;
            }
        }
        return $result;
    }

    //-------------------------------------------------------------------------
    // Experimentation

    /**
     * Get Liris cohorts for experimentation
     * TODO : delete after experimentation
     *
     * @return array
     */
    public function experimentation_get_cohorts() {
        global $CFG;
        $cohorts = [];
        $suggestionslib = $CFG->dirroot . '/local/ludimoodle_suggestions/lib.php';
        if (file_exists($suggestionslib)) {
            require_once $suggestionslib;
            $cohorts = local_ludimoodle_suggestions_get_cohorts();
        }
        return $cohorts;
    }

    /**
     * Render Liris cohorts selector for experimentation
     * TODO : delete after experimentation
     *
     * @return string
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function experimentation_render_cohorts_selector() {
        global $CFG;
        $cohortnames  = $this->experimentation_get_cohorts();
        $cohort       = optional_param('cohort', null, PARAM_RAW);
        $courseid     = $this->get_course_id();
        $courseurl    = $CFG->wwwroot . '/course/view.php';
        $config       = get_config('format_ludimoodle');
        $lessonisopen = isset($config->lessonisopen) ? $config->lessonisopen : false;
        if ($lessonisopen) {
            $cohort = $lessonisopen;
        }

        $output = '<div id="experimentation_cohorts_selector" class="buttons-group">';
        if (count($cohortnames) > 0 && !$lessonisopen) {
            $output .= "<form action='" . $courseurl . "' method='get' class='margin-top-30 text-center'>";
            $output .= '<input type="hidden" name="id" value="' . $courseid . '">';
            $output .= "<select name='cohort' onchange='this.form.submit();'>";
            foreach ($cohortnames as $key => $cohortoption) {
                if ($key == 0) {
                    $output .= '<option value="">-- Choisissez une cohorte --</option>';
                }
                $selected = '';
                if ($cohort != null && $cohort == $cohortoption) {
                    $selected = ' selected';
                }
                $output .= '<option value="' . $cohortoption . '"  ' . $selected . '>' . $cohortoption . '</option>';
            }
            $output .= "</select>";
            $output .= "</form>";
        } else {
            $output .= '<p class="text-center">' . $cohort . '</p>';
        }
        $output .= '</div>';
        return $output;
    }

    /**
     * Render Liris dynamic buttons for experimentation
     * TODO : delete after experimentation
     *
     * @return string
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function experimentation_render_dynamic_buttons() {
        global $CFG;
        $cohort              = optional_param('cohort', null, PARAM_RAW);
        $config              = get_config('format_ludimoodle');
        $lessonisopen        = isset($config->lessonisopen) ? $config->lessonisopen : false;
        $mustchoosemotivator = isset($config->mustchoosemotivator) ? explode(',', $config->mustchoosemotivator) : false;
        if ($lessonisopen) {
            $cohort = $lessonisopen;
        }
        $courseid  = $this->get_course_id();
        $courseurl = $CFG->wwwroot . '/course/view.php';
        if ($cohort) {
            $decisionurl = $CFG->wwwroot . '/local/ludimoodle_suggestions/index.php?courseid=' . $courseid . '&cohort=' . $cohort;
        } else {
            // Generate suggestions after questionnaire de départ.
            $outputdir = local_ludimoodle_suggestions_get_suggestionsdir();
            $scriptdir = local_ludimoodle_suggestions_get_scriptdir();
            $scriptpath = $scriptdir . 'generate_suggestion.sh';
            if (is_file($scriptpath)) {
                $cmd = '/bin/bash ' . $scriptpath . ' ' . $outputdir;
                $return = shell_exec($cmd);
            }
            $decisionurl = $CFG->wwwroot . '/local/ludimoodle_suggestions/index.php?courseid=' . $courseid;
        }

        $output = '<div id="experimentation_dynamic_buttons" class="buttons-group">';
        if (!empty($mustchoosemotivator) && in_array($cohort, $mustchoosemotivator)) {
            $output .= '<p class="teacher-link"><a href="' . $decisionurl .
                       '"><button class="btn">Recommandations de modifications<br />d’éléments ludiques</button></a></p>';
        } else if ($cohort) {
            $output .= "<form action='" . $courseurl . "' method='get'>";
            $output .= '<input type="hidden" name="id" value="' . $courseid . '">';
            $output .= '<input type="hidden" name="cohort" value="' . $cohort . '">';
            if ($lessonisopen) {
                $output .= '<p class="teacher-link"><button class="btn" type="submit" name="lessonisopen" value="0">Fermer la leçon</button></p>';
            } else {
                $output .= '<p class="teacher-link"><button class="btn" type="submit" name="lessonisopen" value="' . $cohort .
                           '">Ouvrir la leçon</button></p>';
            }
            $output .= "</form>";
        }
        $output .= '</div>';

        return $output;
    }
}