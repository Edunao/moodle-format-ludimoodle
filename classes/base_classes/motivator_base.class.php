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
 * Parent class for motivator
 * Each motivator must inherit this class and implement interface i_motivator
 *
 * @package    course
 * @subpackage format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     celine <celine@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ludimoodle;
defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/course/format/ludimoodle/classes/base_classes/motivator.interface.php';
require_once $CFG->dirroot . '/course/format/ludimoodle/lib/presets_lib.php';


abstract class motivator_base {

    private $instanceconfig;

    /**
     * motivator_base constructor.
     * Set data and presets of motivator
     *
     * @param array $instanceconfig
     */
    public function __construct($instanceconfig = []) {
        if (!isset($instanceconfig['presets'])) {
            $instanceconfig['presets'] = (object) [];
        }
        if (!isset($instanceconfig['data'])) {
            $instanceconfig['data'] = (object) [];
        }
        if (!isset($instanceconfig['presets']->visual)) {
            $instanceconfig['presets']->visual = '';
            if (method_exists($this, 'get_default_visual_preset')) {
                $instanceconfig['presets']->visual = $this->get_default_visual_preset();
            }
        }
        if (!isset($instanceconfig['presets']->behavior)) {
            $instanceconfig['presets']->behavior = '';
            if (method_exists($this, 'get_default_behavior_preset')) {
                $instanceconfig['presets']->behavior = $this->get_default_behavior_preset();
            }
        }
        $this->instanceconfig = $instanceconfig;
    }

    /**
     * Include css file of motivator
     * @param $env
     * @return string
     * @throws \ReflectionException
     */
    public function include_css($env) {
        global $CFG;
        $css    = '';
        $cssurl = $CFG->dirroot . '/course/format/ludimoodle/motivators/' . $this->get_short_name() . '/styles.css';

        // We can't require css like this in format because <head> is already closed
        //$page      = $env->get_page();
        //$page->requires->css($cssurl);

        // if css is not already in page
        if (file_exists($cssurl) && (!in_array($cssurl, $env->cssinitdata))) {
            // mark that this css is in page
            $env->cssinitdata[] = $cssurl;
            $css                = '<style>' . file_get_contents($cssurl) . '</style>';
        }
        return $css;
    }

    /**
     * @return string
     * @throws \ReflectionException
     */
    public function get_class_name() {
        return (new \ReflectionClass($this))->getShortName();
    }

    /**
     * @return string
     * @throws \ReflectionException
     */
    public function get_short_name() {
        return preg_replace('/motivator_/', '', $this->get_class_name());
    }

    /**
     * Image shared among all presets
     * @param $image - name of image (ex : logo.png)
     * @return \moodle_url
     * @throws \ReflectionException
     * @throws \moodle_exception
     */
    public function image_url($image) {
        $pluginname = basename(dirname(dirname(__DIR__)));
        return new \moodle_url("/course/format/$pluginname/motivators/" . $this->get_short_name() . "/pix/$image");
    }

    /**
     * Image specific to a preset
     * @param $image
     * @param $presetname
     * @param null $subdir
     * @return \moodle_url
     * @throws \ReflectionException
     * @throws \moodle_exception
     */
    public function get_preset_image_url($image, $presetname, $subdir = null) {
        $pluginname = basename(dirname(dirname(__DIR__)));
        if ($subdir != null) {
            $image = $subdir . '/' . $image;
        }
        return new \moodle_url("/course/format/$pluginname/motivators/" . $this->get_short_name() . "/presets/visual/" .
                               $presetname . "/assets/$image");
    }

    /**
     * @param $env
     * @return string
     * @throws \ReflectionException
     */
    public function include_preset_css($env) {
        global $CFG;
        $css = '';
        $presetdata = $this->get_visual_preset_data();
        // no css to include
        if (!isset($presetdata['css-file'])) {
            return $css;
        }

        $pluginname = basename(dirname(dirname(__DIR__)));
        $presetname = $this->get_visual_preset();
        $cssurl     = $CFG->dirroot . "/course/format/$pluginname/motivators/" . $this->get_short_name() . "/presets/visual/" .
                      $presetname . "/assets/" . $presetdata['css-file'];
        // if css is not already in page
        if (file_exists($cssurl) && (!in_array($cssurl, $env->cssinitdata))) {
            // mark that this css is in page
            $env->cssinitdata[] = $cssurl;
            $css                = '<style>' . file_get_contents($cssurl) . '</style>';
        }
        return $css;
    }

    /*----------------------------------------
        Presets utils
    */

    /**
     * Return an array with all presets type and value for a motivator
     * @param null $presettype
     * @return array|bool
     */
    public static function get_all_presets($presettype = null) {
        global $CFG;

        $motivatorname = str_replace('format_ludimoodle\motivator_', '', get_called_class());
        $presets       = [];
        $presettypes   = \format_ludimoodle_get_preset_types();

        // Get code presets
        $presetspath = $CFG->dirroot . '/course/format/ludimoodle/motivators/' . $motivatorname . '/presets';
        if (!file_exists($presetspath)) {
            return false;
        }
        $motivatorpresetsdir = scandir($presetspath);
        foreach ($motivatorpresetsdir as $dirname) {
            if (in_array($dirname, array(".", ".."))) {
                continue;
            }

            if (!in_array($dirname, $presettypes)) {
                continue;
            }

            $typepresets = scandir($presetspath . '/' . $dirname);
            foreach ($typepresets as $presetname) {
                if (in_array($presetname, array(".", ".."))) {
                    continue;
                }
                $presets[$dirname][] = $presetname;
            }
        }

        if ($presettype != null && isset($presets[$presettype])) {
            $presets = $presets[$presettype];
        }
        // TODO : Get db presets

        return $presets;
    }

    /**
     * return visual preset data config array
     * @param null $name
     * @return array
     */
    protected function get_visual_preset_data($name = null) {
        global $CFG;
        if (!isset($this->instanceconfig['data']->visual)
            || (!empty($name)
                && isset($this->instanceconfig['data']->visual['presetname'])
                && $this->instanceconfig['data']->visual['presetname'] != $name
            )
        ) {
            if ($name == null) {
                $name = $this->get_visual_preset();
            }
            $motivatorname = str_replace('format_ludimoodle\motivator_', '', get_called_class());
            $presetpath    = $CFG->dirroot . '/course/format/ludimoodle/motivators/' . $motivatorname . '/presets/visual/' .
                             $name;
            if (!file_exists($presetpath . '/config.json')) {
                throw new \moodle_exception('No config.json find for motivator : '.$motivatorname. ' and visual : ' . $name);
            }

            $config = file_get_contents($presetpath . '/config.json');
            $config = (array) json_decode($config);
            $config['presetname'] = $name;

            // Check required config
            $requiredparams = $this->get_required_visual_preset_attributes();
            $configparams   = array_keys($config);

            $missingparams = array_diff($requiredparams, $configparams);
            if (!empty($missingparams)) {
                throw new \moodle_exception('Missing required params "'.print_r($missingparams, true).'" for motivator : '.$motivatorname. ' and visual : ' . $name);
            }
            $this->instanceconfig['data']->visual = $config;
        }
        return $this->instanceconfig['data']->visual;
    }

    protected function get_visual_preset_str($visualpreset) {
        $strings = [];
        $data = $this->get_visual_preset_data($visualpreset);
        foreach ($data as $key => $dataobj) {
            if (strpos($key, 'str-') === 0) {
                $lang = explode('-', $key)[1];
                $strings[$lang] = get_object_vars($dataobj);
            }
        }
        return $strings;
    }

    /**
     * return behavior preset data config array
     * @param null $name
     * @return array
     */
    protected function get_behavior_preset_data($name = null) {
        global $CFG;
        if (!isset($this->instanceconfig['data']->behavior)) {
            if ($name == null) {
                $name = $this->get_behavior_preset();
            }
            $motivatorname = str_replace('format_ludimoodle\motivator_', '', get_called_class());
            $presetpath    = $CFG->dirroot . '/course/format/ludimoodle/motivators/' . $motivatorname . '/presets/behavior/' .
                             $name;
            if (!file_exists($presetpath . '/config.json')) {
                throw new \moodle_exception('No config.json find for motivator : '.$motivatorname. ' and behavior : ' . $name);
            }

            $config = file_get_contents($presetpath . '/config.json');
            $config = (array) json_decode($config);

            // Check required config
            $requiredparams = $this->get_required_behavior_preset_attributes();
            $configparams   = array_keys($config);

            $missingparams = array_diff($requiredparams, $configparams);
            if (!empty($missingparams)) {
                throw new \moodle_exception('Missing required params "'.print_r($missingparams, true).'" for motivator : '.$motivatorname. ' and behavior : ' . $name);
            }
            $this->instanceconfig['data']->behavior = $config;
        }

        return $this->instanceconfig['data']->behavior;
    }

    /**
     * @return object of current presets
     */
    protected function get_instance_presets() {
        return $this->instanceconfig['presets'];
    }

    /**
     * @return array of current visual preset
     */
    protected function get_visual_preset() {
        return $this->get_instance_presets()->visual;
    }

    /**
     * @return array of current visual preset
     */
    protected function get_behavior_preset() {
        return $this->get_instance_presets()->behavior;
    }

    /*----------------------------------------
        Ludimoodle achievements utils
    */

    /**
     * Calculate user section advancement and set it
     * return true if advancement increased else false
     * @param $env execution_environment
     * @param $section
     * @return bool
     */
    public function set_user_section_advancement($env, $section) {
        $datamine  = $env->get_data_mine();
        $userid    = $env->get_userid();
        $courseid  = $env->get_course_id();
        $sectionid = $section->id;
        $cms       = $env->get_cms_info();
        $oldvalue  = $datamine->get_user_section_achievement($userid, $courseid, $sectionid, 'advancement', 0);
        $newvalue  = $datamine->calculate_section_advancement($userid, $sectionid, $cms);
        $datamine->set_user_section_achievement($userid, $courseid, $sectionid, 'advancement', $newvalue);
        $animate = $newvalue > $oldvalue ? true : false;
        return $animate;
    }

    /**
     * Return user section advancement
     * @param $env execution_environment
     * @param $section
     * @return int
     */
    public function get_user_section_advancement($env, $section) {
        $datamine  = $env->get_data_mine();
        $userid    = $env->get_userid();
        $courseid  = $env->get_course_id();
        $sectionid = $section->id;
        return $datamine->get_user_section_achievement($userid, $courseid, $sectionid, 'advancement', 0);
    }

    /**
     * Calculate user cm - mod advancement and set it
     * return true if advancement increased else false
     * @param $env execution_environment
     * @param $cm
     * @return bool
     */
    public function set_user_cm_advancement($env, $cm) {
        $datamine  = $env->get_data_mine();
        $userid    = $env->get_userid();
        $courseid  = $env->get_course_id();
        $oldvalue  = $datamine->get_user_mod_achievement($userid, $courseid, $cm->id, 'advancement', 0);
        $newvalue  = $datamine->calculate_cm_advancement($userid, $cm->id);
        $datamine->set_user_mod_achievement($userid, $courseid,  $cm->id, 'advancement', $newvalue);
        $animate   = $newvalue > $oldvalue ? true : false;
        return $animate;
    }

    /**
     * Return user cm - mod advancement
     * @param $env execution_environment
     * @param $cm
     * @return int
     */
    public function get_user_cm_advancement($env, $cm) {
        $datamine  = $env->get_data_mine();
        $userid    = $env->get_userid();
        $courseid  = $env->get_course_id();
        $cmid      = is_object($cm) ? $cm->id : $cm;
        $advancement  = $datamine->get_user_mod_achievement($userid, $courseid, $cmid, 'advancement', 0);
        return $advancement;
    }
}