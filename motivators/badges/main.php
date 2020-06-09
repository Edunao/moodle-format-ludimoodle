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
 * Collect badges by completing activities
 *
 * @package    course
 * @subpackage format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     David Bokobza <david.bokobza@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ludimoodle;

require_once $CFG->dirroot . '/course/format/ludimoodle/classes/base_classes/motivator_base.class.php';

class motivator_badges extends motivator_base implements i_motivator {

    public function get_loca_strings($lang = 'en') {
        $strings['en'] = [
                'badge'              => 'Badge',
                'badges' => 'Badges',
                'badges-description' => 'Le badge correspond à un niveau de réussite de type or, bronze, argent. Il est décliné en fonction des différents niveaux atteints.',
                'badges-currentrun'  => 'Suite sans fautes', 'badges-progression-title-default' => 'Badges'
        ];
        $strings['fr'] = [
                'badge'              => 'Badge',
                'badges' => 'Badges',
                'badges-description' => 'Le badge correspond à un niveau de réussite de type or, bronze, argent. Il est décliné en fonction des différents niveaux atteints.',
                'badges-currentrun'  => 'Suite sans fautes', 'badges-progression-title-default' => 'Badges'
        ];
        $visuals       = $this->get_all_presets('visual');
        foreach ($visuals as $visual) {
            $allvisualstr = $this->get_visual_preset_str($visual);
            foreach ($allvisualstr as $lang => $visualstr) {
                foreach ($visualstr as $key => $str) {
                    $strings[$lang][$key] = $str;
                }
            }
        }
        if (isset($strings[$lang]) && !empty($strings[$lang])) {
            return $strings[$lang];
        } else {
            return $strings;
        }
    }

    public static function get_valid_modules() {
        return true;
    }

    public static function get_valid_contexts() {
        return ['cm', 'section'];
    }

    public static function get_required_behavior_preset_attributes() {
        return ['progression-thresholds', 'currentrun-thresholds'];
    }

    public static function get_required_visual_preset_attributes() {
        return [
                'str-en', 'str-fr', 'hardest-badge-image', 'middle-badge-image', 'easiest-badge-image', 'failed-badge-image',
                'locked-badge-image', 'unlocked-badge-image', 'progression-images-sets', 'currentrun-images-sets',
                'progression-images-badges', 'currentrun-images-badges'
        ];
    }

    public static function get_default_behavior_preset() {
        return 'default';
    }

    public static function get_default_visual_preset() {
        return 'bronzesilvergold';
    }

    /*----------------------------------------
         Required render functions
     */

    public function render_summary_mod_view($env, $cm) {
        // Display badge image corresponding of cm advancement only
        $badgeurl = $this->get_cm_badge_url($env, $cm);

        // Render
        $output = $this->include_css($env);
        $output .= '<div class="ludi-badge ludi-summary-mod-view">';
        $output .= '<img src="' . $badgeurl . '">';
        $output .= '</div>'; // ludi-badge ludi-section-view ludi-summary-mod-view
        return $output;
    }

    public function render_mod_view($env, $cm) {
        // Get data
        $section    = $env->get_current_section();
        $sectionidx = $env->get_section_idx();

        // Calculate and set data in achievements
        // 1 - Set section advancement (ex : 70), , calculate and set section badgeslevel (ex : 3)
        $animatesection = $this->set_user_section_advancement($env, $section);
        // 2 - Set currentrun (score ex: 9) and currentrunlevel (badge corresponding to score ex: 3)
        $animatecorrectrun = $this->calculate_section_correct_run($env, $section);
        // 3 - Set cm advancement (ex : 100), calculate and set cm badgeslevel (ex : 3)
        $animatemod = $this->set_user_cm_advancement($env, $cm);

        // animate if any new badge is earned
        $animate = $animatesection || $animatecorrectrun || $animatemod;
        $env->set_js_init_data($this->get_short_name(), ['animate' => $animate]);

        // Get data
        $badgeurl       = $this->get_cm_badge_url($env, $cm);
        $currentrunurl  = $this->get_currentrun_image_url($env, $section);
        $progressionurl = $this->get_progression_image_url($env, $section);

        // Render in 2 parts
        $output = $this->include_css($env);
        $output .= '<div class="ludi-badges ludi-mod-view">';

        // First part : section progression badges
        $output .= '<div class="ludi-three-part section-progression">';
        $output .= '<img src="' . $progressionurl . '">';
        $output .= '<div class="ludi-three-part-title">';
        $output .= $this->get_progression_title($sectionidx);
        $output .= '</div>'; // ludi-three-part-title
        $output .= $this->render_section_progression_bar($env, $section);
        $output .= '</div>'; // ludi-three-part

        // Second part : current run badges
        $output .= '<div class="ludi-three-part currentrun">';
        $output .= '<img src="' . $currentrunurl . '">';
        $output .= '<div class="ludi-three-part-title">';
        $output .= get_string('badges-currentrun', 'format_ludimoodle');
        $output .= '</div>'; // ludi-three-part-title
        $output .= $this->render_currentrun_bar($env, $section);
        $output .= '</div>'; // ludi-three-part

        // Third part : cm progression badges
        $output .= '<div class="ludi-three-part cm-progression">';
        $output .= '<img src="' . $badgeurl . '">';
        $output .= '<div class="ludi-three-part-title">';
        $output .= get_string('badge', 'format_ludimoodle'); // ludi-three-part-title
        $output .= '</div>'; // ludi-three-part-title
        $output .= $this->render_cm_progression_bar($env, $cm);
        $output .= '</div>'; // ludi-three-part

        $output .= '</div>'; // ludi-badges
        return $output;
    }

    public function render_summary_section_view($env, $section) {
        // Get data
        $progressionurl = $this->get_progression_image_url($env, $section);
        $currentrunurl  = $this->get_currentrun_image_url($env, $section);

        // Render in 2 parts
        $output = $this->include_css($env);

        $output .= '<div class="ludi-badges ludi-summary-section-view">';

        // First part : section progression badges
        $output .= '<div class="two-part left-part">';
        $output .= '<div class="title progression-title">';
        $output .= $this->get_progression_title($section->section);
        $output .= '</div>'; // progression-title
        $output .= '<img src="' . $progressionurl . '">';
        $output .= '</div>'; // two-part

        // Second part : current run badges
        $output .= '<div class="two-part right-part">';
        $output .= '<div class="title currentrun-title">';
        $output .= get_string('badges-currentrun', 'format_ludimoodle');
        $output .= '</div>'; // currentrun-title
        $output .= '<img src="' . $currentrunurl . '">';
        $output .= '</div>'; // two-part

        $output .= '</div>';
        return $output;
    }

    public function render_section_view($env, $section) {
        // Get data
        $progressionurl = $this->get_progression_image_url($env, $section);
        $currentrunurl  = $this->get_currentrun_image_url($env, $section);

        // Render in 2 parts
        $output = $this->include_css($env);
        $output .= '<div class="ludi-badges ludi-section-view">';

        // First part : section progression badges
        $output .= '<div class="two-part left-part">';
        $output .= '<img src="' . $progressionurl . '">';
        $output .= '<div class="progression-title">';
        $output .= $this->get_progression_title($section->section);
        $output .= '</div>'; // progression-title
        $output .= '</div>'; // two-part

        // Second part : current run badges
        $output .= '<div class="two-part right-part">';
        $output .= '<img src="' . $currentrunurl . '">';
        $output .= '<div class="currentrun-title">';
        $output .= get_string('badges-currentrun', 'format_ludimoodle');
        $output .= '</div>'; // progression-title
        $output .= $this->render_currentrun_bar($env, $section);
        $output .= '</div>'; // two-part

        $output .= '</div>';
        return $output;
    }

    /**
     * Section progression bar based on currentrun
     *
     * @param $env execution_environment
     * @param $section object
     * @return string
     */
    private function render_currentrun_bar($env, $section) {
        // Get data
        $datamine     = $env->get_data_mine();
        $userid       = $env->get_userid();
        $courseid     = $env->get_course_id();
        $sectionid    = $section->id;
        $sectionidx   = $section->section;
        $thresholds   = $this->get_currentrun_thresholds($env, $sectionidx);
        $maxthreshold = max($thresholds);
        $currentrun   = $datamine->get_user_section_achievement($userid, $courseid, $sectionid, 'currentrun', 0);

        // Render
        $output = $this->include_css($env);
        // Progress bar container
        $output .= '<div class="badge-bar currentrun-bar">';

        // Progress bar content
        if ($currentrun > 0) {
            // Current run can't be higher than max treshold
            $currentrun = min($currentrun, $maxthreshold);
            // Calculate width percentage like : 4/6 * 100 = 66.66%
            $width = $currentrun / $maxthreshold * 100;
            // Add border radius, prevent content from overflowing into the container
            $class = $width >= 98 ? ' radius' : '';
            // Ensure consistency with the threshold steps, they have a slight offset with the bar
            $style = $width == 100 || $width <= 45 ? 'style="width: ' . $width . '%;"' : 'style="width: calc(' . $width . '% - 8px);"';
            // Render internal bar
            $output .= '<div class="badge-internalbar ' . $class . '" ' . $style . '></div>';
        }

        // Show thresholds steps on progress bar
        $prevwidth = 0;
        foreach ($thresholds as $index => $threshold) {
            // Calculate width percentage like : (4/7 * 100 - 0) = 57% for the first,
            // and (6/7 * 100 - 57) = 28% for the second
            $width     = $threshold / $maxthreshold * 100 - $prevwidth;
            $prevwidth = $threshold / $maxthreshold * 100;
            $class     = $currentrun >= $threshold ? ' reached' : '';

            // Render thresholds steps on progress bar
            $output .= '<div class="badge-part part-' . $index . '" style="width:' . $width . '%;">';
            $output .= '<span class="threshold' . $class . '">' . $threshold . '</span>';
            $output .= '<span class="threshold-limit' . $class . '"></span>';
            $output .= '</div>';
        }

        $output .= '</div>'; // badge-bar
        return $output;
    }

    /**
     * Mod progression bar based on advancement
     *
     * @param $env execution_environment
     * @param $cm object
     * @return string
     */
    private function render_cm_progression_bar($env, $cm) {
        $advancement  = $this->get_user_cm_advancement($env, $cm);
        $thresholds   = $this->get_behavior_preset_data()["progression-thresholds"];
        $maxthreshold = max($thresholds);

        $output = '<div class="badge-bar progression-bar">';
        if ($advancement > 0) {
            $width = $advancement;
            // Add border radius, prevent content from overflowing into the container
            $class = $width >= 98 ? ' radius' : '';
            // Ensure consistency with the threshold steps, they have a slight offset with the bar
            $style = $width == 100 || $width <= 45 ? 'style="width: ' . $width . '%;"' : 'style="width: calc(' . $width . '% - 8px);"';
            // Render internal bar
            $output .= '<div class="badge-internalbar ' . $class . '" ' . $style . '></div>';
        }

        // Show thresholds steps on progress bar
        $prevwidth = 0;
        foreach ($thresholds as $index => $threshold) {
            // Calculate width percentage like : (70/100 * 100 - 0) = 70% for the first,
            // and (85/100 * 100 - 70) = 15% for the second
            $width     = $threshold / $maxthreshold * 100 - $prevwidth;
            $prevwidth = $threshold / $maxthreshold * 100;
            $class     = $advancement >= $threshold ? ' reached' : '';

            // Render thresholds steps on progress bar
            $output .= '<div class="badge-part part-' . $index . '" style="width:' . $width . '%;">';
            $output .= '<span class="threshold' . $class . '">' . $threshold . '%</span>';
            $output .= '<span class="threshold-limit' . $class . '"></span>';
            $output .= '</div>'; // badge-part
        }
        $output .= '</div>'; // badge-bar
        return $output;
    }

    /**
     * Section progression bar based on advancement
     *
     * @param $env execution_environment
     * @param $section object
     * @return string
     */
    private function render_section_progression_bar($env, $section) {
        $advancement  = $this->get_user_section_advancement($env, $section);
        $thresholds   = $this->get_behavior_preset_data()["progression-thresholds"];
        $maxthreshold = max($thresholds);

        $output = '<div class="badge-bar progression-bar">';
        if ($advancement > 0) {
            $width = $advancement;
            // Add border radius, prevent content from overflowing into the container
            $class = $width >= 98 ? ' radius' : '';
            // Ensure consistency with the threshold steps, they have a slight offset with the bar
            $style = $width == 100 || $width <= 45 ? 'style="width: ' . $width . '%;"' : 'style="width: calc(' . $width . '% - 8px);"';
            // Render internal bar
            $output .= '<div class="badge-internalbar ' . $class . '" ' . $style . '></div>';
        }

        // Show thresholds steps on progress bar
        $prevwidth = 0;
        foreach ($thresholds as $index => $threshold) {
            // Calculate width percentage like : (70/100 * 100 - 0) = 70% for the first,
            // and (85/100 * 100 - 70) = 15% for the second
            $width     = $threshold / $maxthreshold * 100 - $prevwidth;
            $prevwidth = $threshold / $maxthreshold * 100;
            $class     = $advancement >= $threshold ? ' reached' : '';

            // Render thresholds steps on progress bar
            $output .= '<div class="badge-part part-' . $index . '" style="width:' . $width . '%;">';
            $output .= '<span class="threshold' . $class . '">' . $threshold . '%</span>';
            $output .= '<span class="threshold-limit' . $class . '"></span>';
            $output .= '</div>'; // badge-part
        }

        $output .= '</div>'; // badge-bar
        return $output;
    }

    /**
     * Calculate currentrun score of section
     *
     * @param $env execution_environment
     * @param $section object
     * @return bool
     */
    private function calculate_section_correct_run($env, $section) {
        $animate   = false;
        $attemptid = $env->get_attempt_id();
        // If we are not in a attempt there is nothing new to calculate
        if (!$attemptid > 0) {
            return $animate;
        }
        $datamine  = $env->get_data_mine();
        $userid    = $env->get_userid();
        $courseid  = $env->get_course_id();
        $sectionid = $section->id;
        $oldvalue  = $datamine->get_user_section_achievement($userid, $courseid, $sectionid, 'currentrun', 0);

        // Delegate to datamine to calculate section correct run
        $newvalue = $datamine->calculate_section_correct_run($userid, $courseid, $sectionid);
        $datamine->set_user_section_achievement($userid, $courseid, $sectionid, 'currentrun', $newvalue);

        // calculate and set section current run level depending of section thresholds
        if ($newvalue > $oldvalue) {
            $oldlevel   = $datamine->get_user_section_achievement($userid, $courseid, $sectionid, 'currentrunlevel', 0);
            $level      = 0;
            $sectionidx = $section->section;
            $thresholds = $this->get_currentrun_thresholds($env, $sectionidx);
            while (isset($thresholds[$level]) && $newvalue >= $thresholds[$level]) {
                $level++;
            }
            $datamine->set_user_section_achievement($userid, $courseid, $sectionid, 'currentrunlevel', $level);
            $animate = $level > $oldlevel;
        }
        return $animate;
    }

    /**
     * Get section thresholds depending of section idx
     *
     * @param $env execution_environment
     * @param $sectionidx int
     * @return mixed
     */
    private function get_currentrun_thresholds($env, $sectionidx) {
        $behavior = $this->get_behavior_preset_data()['currentrun-thresholds'];
        $sections = $env->get_course_sections();

        // set foreach sections a current run thresholds array
        $i          = 0;
        $thresholds = [];
        foreach ($sections as $section) {
            $i                             = isset($behavior[$i]) ? $i : 0;
            $thresholds[$section->section] = $behavior[$i];
            $i++;
        }

        // return current run thresholds array
        return $thresholds[$sectionidx];
    }

    /**
     * Get section title depending of section idx
     *
     * @param $env execution_environment
     * @param $sectionidx int
     * @return string
     */
    private function get_progression_title($sectionidx) {
        $stringexists = get_string_manager()->string_exists('badges-' . $this->get_visual_preset() . '-progression-title-' .
                                                            $sectionidx, 'format_ludimoodle');
        if ($stringexists) {
            $title = get_string('badges-' . $this->get_visual_preset() . '-progression-title-' . $sectionidx, 'format_ludimoodle');
        } else {
            $title = get_string('badges-progression-title-default', 'format_ludimoodle');
        }
        return $title;
    }

    /**
     * Get badge set url depending of section advancement
     *
     * @param $env
     * @param $section
     * @param bool $onlybadge
     * @return string
     * @throws \ReflectionException
     * @throws \moodle_exception
     */
    private function get_progression_image_url($env, $section, $onlybadge = false) {
        // Get data
        $datamine  = $env->get_data_mine();
        $userid    = $env->get_userid();
        $courseid  = $env->get_course_id();
        $sectionid = $section->id;
        // Badges images or sets images ?
        if ($onlybadge) {
            $images = $this->get_visual_preset_data()['progression-images-badges'];
        } else {
            $images = $this->get_visual_preset_data()['progression-images-sets'];
        }
        $level = $datamine->get_user_section_achievement($userid, $courseid, $sectionid, 'badgeslevel', 0);
        $image = $images[$level];
        $url   = $this->get_preset_image_url($image, $this->get_visual_preset(), 'progression')->get_path();
        return $url;
    }

    /**
     * Get currentrun url depending of section thresholds and score
     *
     * @param $env execution_environment
     * @param $section object
     * @param $onlybadge   bool - if true set return badge and not badge set
     * @return string
     */
    private function get_currentrun_image_url($env, $section, $onlybadge = false) {
        $datamine  = $env->get_data_mine();
        $userid    = $env->get_userid();
        $courseid  = $env->get_course_id();
        $sectionid = $section->id;
        if ($onlybadge) {
            $images = $this->get_visual_preset_data()['currentrun-images-badges'];
        } else {
            $images = $this->get_visual_preset_data()['currentrun-images-sets'];
        }
        $level = $datamine->get_user_section_achievement($userid, $courseid, $sectionid, 'currentrunlevel', 0);
        $image = $images[$level];
        $url   = $this->get_preset_image_url($image, $this->get_visual_preset(), 'currentrun')->get_path();
        return $url;
    }

    /**
     * Get cm badge url depending of mod advancement
     *
     * @param $env execution_environment
     * @param $cm object
     * @return string
     */
    private function get_cm_badge_url($env, $cm) {
        $datamine = $env->get_data_mine();
        $userid   = $env->get_userid();
        $courseid = $env->get_course_id();
        $level    = $datamine->get_user_mod_achievement($userid, $courseid, $cm->id, 'badgeslevel', 0);
        $url      = $this->get_badge_image_url($level);
        return $url;
    }

    /**
     * Return url of badge from badge level
     *
     * @param $badgelevel
     * @return string
     * @throws \ReflectionException
     * @throws \moodle_exception
     */
    public function get_badge_image_url($badgelevel) {
        $visual = $this->get_visual_preset_data();
        $name   = $this->badgelevel_to_name($badgelevel);
        $image  = $visual[$name];
        $url    = $this->get_preset_image_url($image, $this->get_visual_preset())->get_path();
        return $url;
    }

    /**
     * Return name of badge from level
     *
     * @param $badgelevel
     * @return mixed
     */
    private function badgelevel_to_name($badgelevel) {
        $visual = $this->get_badges_list();
        if (isset($visual[$badgelevel])) {
            $value = $visual[$badgelevel];
        } else {
            $value = $visual[0];
        }
        return $value;
    }

    /**
     * Get badges of section with quantity of each
     *
     * @param $env execution_environment
     * @param $section object
     * @return array
     */
    public function get_section_badges_by_quantity($env, $section) {
        $badges = $this->get_badges_list();
        // initialize count
        foreach ($badges as $key => $value) {
            $badges[$key] = 0;
        }
        $datamine  = $env->get_data_mine();
        $userid    = $env->get_userid();
        $courseid  = $env->get_course_id();
        $cms       = $env->get_cms_info();
        $sectionid = $section->id;
        foreach ($cms as $cm) {
            if ($cm->section != $sectionid) {
                continue;
            }
            $level = $datamine->get_user_mod_achievement($userid, $courseid, $cm->id, 'badgeslevel', false);
            if ($level) {
                $badges[$level]++;
            }
        }
        return $badges;
    }

    /**
     * Return best badge of section
     *
     * @param $env execution_environment
     * @param $section object
     * @return array
     */
    public function get_best_badge_of_section($env, $section) {
        $badges            = $this->get_section_badges_by_quantity($env, $section);
        $bestbadge         = 0;
        $bestbadgequantity = 0;
        foreach ($badges as $badge => $quantity) {
            if ($quantity > 0 && $badge > $bestbadge) {
                $bestbadge         = $badge;
                $bestbadgequantity = $quantity;
            }
        }
        return [$bestbadge => $bestbadgequantity];
    }

    /**
     * array key is the badge level
     * array value is the badge name
     *
     * @return array
     */
    public function get_badges_list() {
        return [
                -2 => 'locked-badge-image', -1 => 'failed-badge-image', 0 => 'unlocked-badge-image', 1 => 'easiest-badge-image',
                2  => 'middle-badge-image', 3 => 'hardest-badge-image'
        ];
    }

    /**
     * Calculate user cm - mod advancement, cm badges level,set values in achievements
     * return true if a new badge is earned, else false
     *
     * @param $env
     * @param $cm
     * @return bool
     */
    public function set_user_cm_advancement($env, $cm) {
        $animate  = false;
        $datamine = $env->get_data_mine();
        $userid   = $env->get_userid();
        $courseid = $env->get_course_id();

        // Calculate cm advancement and set it
        $modadvancement = $datamine->calculate_cm_advancement($userid, $cm->id);
        $datamine->set_user_mod_achievement($userid, $courseid, $cm->id, 'advancement', $modadvancement);

        // Determine and set current badge for user

        // cm is not visible so badge is : 'locked-badge'
        if ($cm->visible == 0) {
            $level = -2;
            $datamine->set_user_mod_achievement($userid, $courseid, $cm->id, 'badgeslevel', $level);
            return $animate;
        }

        // Badge is earned depending of progression-thresholds defining in : behavior/presetname/config.json
        $oldlevel    = $datamine->get_user_mod_achievement($userid, $courseid, $cm->id, 'badgeslevel', 0);
        $thresholds  = $this->get_behavior_preset_data()["progression-thresholds"];
        $gradepass   = $thresholds[0]; // by default : 70%
        $middlegrade = $thresholds[1]; // by default : 85%
        $grademax    = $thresholds[2]; // by default : 100%
        $level       = 0;
        $gradeinfo   = $datamine->get_cm_grade($userid, $cm->id);
        // Calculate badge level
        if (is_object($gradeinfo) && $gradeinfo->grade != null) {
            if ($modadvancement >= $grademax) {
                // hardest-badge
                $level = 3;
            } else if ($modadvancement >= $middlegrade) {
                // middle-badge
                $level = 2;
            } else if ($modadvancement >= $gradepass) {
                // easiest-badge
                $level = 1;
            } else {
                // failed-badge
                $level = -1;
            }
        }
        // Show failure only when quiz is submit
        if ($level < 0 && $gradeinfo->state == 'inprogress') {
            // unlocked-badge
            $level = 0;
        }

        // Animate if there is a new badge
        if ($level > $oldlevel && $level > 0) {
            $animate = true;
        }

        // Set cm badges level
        $datamine->set_user_mod_achievement($userid, $courseid, $cm->id, 'badgeslevel', $level);

        return $animate;
    }

    /**
     * Calculate user section advancement, and section badgeslevel, set values in achievements
     * return true if a new badge is earned, else false
     *
     * @param $env
     * @param $section
     * @return bool
     */
    public function set_user_section_advancement($env, $section) {
        // Get data
        $animate   = false;
        $datamine  = $env->get_data_mine();
        $userid    = $env->get_userid();
        $courseid  = $env->get_course_id();
        $sectionid = $section->id;
        $cms       = $env->get_cms_info();
        $behavior  = $this->get_behavior_preset_data()['progression-thresholds'];
        $oldlevel  = $datamine->get_user_section_achievement($userid, $courseid, $sectionid, 'badgeslevel', 0);

        // Calculate section advancement and set it
        $advancement = $datamine->calculate_section_advancement($userid, $sectionid, $cms);
        $datamine->set_user_section_achievement($userid, $courseid, $sectionid, 'advancement', $advancement);

        // Calculate badge level and set it
        $level = 0;
        while (isset($behavior[$level]) && $advancement >= $behavior[$level]) {
            $level++;
        }
        $datamine->set_user_section_achievement($userid, $courseid, $sectionid, 'badgeslevel', $level);

        // Animate if there is a new badge
        if ($level > $oldlevel && $level > 0) {
            $animate = true;
        }

        return $animate;
    }
}