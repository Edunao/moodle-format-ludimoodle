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
 * Personalize your avatar by earning equipment through the section
 *
 * @package    course
 * @subpackage format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     David Bokobza <david.bokobza@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ludimoodle;

require_once $CFG->dirroot . '/course/format/ludimoodle/classes/base_classes/motivator_base.class.php';

class motivator_avatar extends motivator_base implements i_motivator {

    private $items        = null;
    private $envforitems  = null;
    private $sectionitems = null;
    private $reacheditems = null;

    public function get_loca_strings($lang = 'en') {
        $strings['en'] =  [
                'avatar' => 'Avatar',
                'avatar-description' => 'Suivez un elfe dans ses péripéties. Chaque bonne réponse aide a débloquer un nouvel objet pour l\'équiper. Aidez-le à étoffer sa collection !',
                'avatar-item' => 'item',
                'avatar-items' => 'items',
                'avatar-inventory' => 'Inventory',
                'avatar-reached-items' => 'Reached items'
        ];
        $strings['fr'] = [
                'avatar' => 'Avatar',
                'avatar-description' => 'Suivez un elfe dans ses péripéties. Chaque bonne réponse aide a débloquer un nouvel objet pour l\'équiper. Aidez-le à étoffer sa collection !',
                'avatar-item' => 'objet',
                'avatar-items' => 'objets',
                'avatar-inventory' => 'Inventaire',
                'avatar-reached-items' => 'Objets débloqués'
        ];
        // get strings in visual config
        $visuals = $this->get_all_presets('visual');
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
        return ['mod-is-completed'];
    }

    public static function get_required_visual_preset_attributes() {
        return ['str-en', 'str-fr', 'slots', 'backgrounds', 'items', 'sets'];
    }

    public static function get_default_behavior_preset() {
        return 'default';
    }

    public static function get_default_visual_preset() {
        return 'childfantasy';
    }

    public function render_mod_view($env, $cm) {
        $section         = $env->get_current_section();
        $newitemreached  = $this->set_user_section_advancement($env, $section);
        $animatemod      = $this->set_user_cm_advancement($env, $cm);
        $env->set_js_init_data($this->get_short_name(), ['animate' => (bool) $newitemreached]);

        // this user has reached x items / y items
        $items             = $this->get_items($env);
        $countitems        = $this->count_items($items);
        $reacheditems      = $this->get_reached_items($env);
        $countreacheditems = $this->count_items($reacheditems);

        $output = $this->include_css($env);

        $output .= '<div class="ludi-left-part">';
        $output .= $this->render_avatar($env, $section);
        $output .= $this->render_inventory($env);
        $output .= '</div>';

        $output .= '<div class="ludi-right-part">';
        $output .= '<div class="ludi-right-part-top">';
        $output .= '<span>' . get_string('avatar-inventory', 'format_ludimoodle') . '</span>';
        $output .= $this->render_bag();
        $output .= $this->render_count_items($countreacheditems, $countitems);
        $output .= '</div>';
        $output .= '<div class="ludi-right-part-separator"></div>';
        $output .= $this->render_section_items($env, $section, $newitemreached);
        $output .= '</div>';
        return $output;
    }

    public function render_summary_mod_view($env, $cm) {
        $behavior = $this->get_behavior_preset_data();
        $presetname = $this->get_visual_preset();
        $advancement = $this->get_user_cm_advancement($env, $cm);
        if (!$cm->visible) {
            $imgurl = $this->get_preset_image_url('mod_locked_default.svg', $presetname);
        } else if ($advancement > $behavior['mod-is-completed']) {
            $imgurl = $this->get_preset_image_url('mod_completed.svg', $presetname);
        } else {
            $imgurl = $this->get_preset_image_url('mod_default.svg', $presetname);
        }
        // Render
        $output = $this->include_css($env);
        $output .= '<div class="ludi-avatar ludi-summary-mod-view">';
        $output .= '<img id="ludi_avatar_img_cm_' . $cm->id . '" class="ludi-avatar-image " src="' . $imgurl . '" data-advancement=' . $advancement . '>';
        $output .= '</div>';

        return $output;
    }

    public function render_section_view($env, $section) {
        $strinventory    = get_string('avatar-inventory',     'format_ludimoodle');
        $strreacheditems = get_string('avatar-reached-items', 'format_ludimoodle');
        $env->set_js_init_data($this->get_short_name(), [
                'visual'   => $this->get_visual_preset(),
                'userid'   => $env->get_userid(),
                'courseid' => $env->get_course_id()
        ]);
        $reacheditems        = $this->get_reached_items($env);
        $sectionreacheditems = $this->get_reached_items($env, $section->section);
        $items               = $this->get_items($env);
        $sectionitems        = $this->get_section_items($env, $section->section);

        // Render left part
        $output = $this->include_css($env);
        $output .= '<div class="ludi-avatar ludi-section-view">';
        $output .= $this->render_avatar($env, $section);
        $output .= '</div>'; // ludi-section-view
        $output .= $this->render_inventory($env);

        // Render right part
        $output .= '<div class="ludi-avatar ludi-right-part-overload">';
        $output .= '<div class="ludi-avatar ludi-right-part">';
        $output .= '<div class="ludi-right-part-top">';
        $output .= '<span>' . $strinventory . '</span>';
        $output .= $this->render_bag();
        $output .= $this->render_count_items($this->count_items($reacheditems), $this->count_items($items));
        $output .= '</div>';
        $output .= '<div class="ludi-right-part-separator"></div>';
        $output .= '<div class="ludi-right-part-bottom">';
        $output .= '<span>' . $strreacheditems . '</span>';
        $output .= $this->render_count_items($this->count_items($sectionreacheditems), $this->count_items($sectionitems));
        $output .= '</div>'; // ludi-right-part-bottom
        $output .= '<div class="ludi-right-part-separator"></div>';
        $output .= '</div>'; // ludi-right-part
        $output .= '</div>'; // ludi-right-part-overload

        return $output;
    }

    public function render_summary_section_view($env, $section) {
        $output = $this->include_css($env);
        $reacheditems = $this->get_reached_items($env, $section->section);
        $items        = $this->get_section_items($env, $section->section);
        // Render
        $output .= '<div class="ludi-avatar ludi-summary-section-view">';
        $output .= $this->render_avatar($env, $section);
        $output .= '<div class="count-items-container">';
        $output .= $this->render_count_items($this->count_items($reacheditems), $this->count_items($items), true);
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render list of reachable items of section with progression
     *
     * @param $env execution_environment
     * @param $section object
     * @param bool $newitem - add an animation if item is new
     * @return string
     * @throws \ReflectionException
     * @throws \moodle_exception
     */
    public function render_section_items($env, $section, $newitem = false) {
        $sectionidx   = $env->get_section_idx();
        $reacheditems = $this->get_reached_items($env, $sectionidx);
        $nextitem     = $this->get_next_item_to_reach_on_section($env, $sectionidx);
        $sectionitems = $this->get_section_items($env, $sectionidx);
        $newitembg    = $this->get_preset_image_url('anim-unlocked-item.gif', $this->get_visual_preset());
        // next items to reach
        $output   = '<div class="section-items">';
        foreach ($sectionitems as $slotidx => $currentsectionitems) {
            foreach ($currentsectionitems as $itemidx => $item) {
                $itemisnotreached = true;
                $itemisnew        = '';
                $progressbar      = '';
                if (isset($reacheditems[$slotidx][$itemidx])) {
                    $itemisnotreached = false;
                    $order            = 0;
                    if ($newitem && $newitem == $item['itemkey']) {
                        $itemisnew = 'background-image: url(' . $newitembg . ');';
                    }
                } else if ($item['itemkey'] == $nextitem['itemkey']) {
                    $advancement = $this->get_user_section_advancement($env, $section);
                    $threshold   = $item['section'][$sectionidx];
                    $percentage  = $threshold > 0 ? (round($advancement / $threshold, 2, PHP_ROUND_HALF_DOWN)) * 100 : 0;
                    $order       = 1;

                    // render progress bar
                    $progressbar .= '<div class="section-item-progress">';
                    $progressbar .= '<div class="section-item-progress-bar" style="width: ' . $percentage . '%";></div>';
                    $progressbar .= '</div>'; // section-item-progress
                } else {
                    $order      = 2;
                }
                $output .= '<div class="section-item" style="order: ' . $order . ';">';

                $output .= '<div class="section-item-icon" style="' . $itemisnew . '">';
                $output .= '<img src="' . $this->get_item_icon_url($item, $itemisnotreached) . '">';
                $output .= '</div>'; // section-item-icon
                $output .= $progressbar;
                $output .= '</div>'; // section-item

            }

        }
        $output .= '</div>';
        return $output;
    }

    /**
     * Render bag
     *
     * @param bool $close - if true bag is close else open
     * @return string
     * @throws \ReflectionException
     * @throws \moodle_exception
     */
    public function render_bag($close = true) {
        $mode = $close ? 'close' : 'open';
        $bagurl = $this->get_preset_image_url('bag_' . $mode . '.svg', $this->get_visual_preset())->get_path();
        //  bag
        $output = '<div class="bag">';
        $output .= '<img src="' . $bagurl . '">';
        $output .= '</div>';
        return $output;
    }

    /**
     * Render a count of items
     *
     * @param $numerator int
     * @param $denumerator int
     * @param bool $label - if true : add a label
     * @return string
     * @throws \coding_exception
     */
    public function render_count_items($numerator, $denumerator, $label = false) {
        $output = '<div class="count-items">';
        $output .= '<span class="numerator">' . $numerator . '</span>';
        $output .= '<span class="fraction">/</span>';
        $output .= '<span class="denumerator">' . $denumerator;
        if ($label) {
            $str = $denumerator > 1 ? 'avatar-items' : 'avatar-item';
            $output .= '<span class="items-label">&nbsp;' . get_string($str, 'format_ludimoodle') . '</span>';
        }

        $output .= '</span></div>';
        return $output;
    }

    /**
     * Render inventory list ordered by category
     *
     * @param $env execution_environment
     * @return string
     * @throws \ReflectionException
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function render_inventory($env) {
        $items        = $this->get_items($env);
        $useditems    = $this->get_used_items($env);
        $reacheditems = $this->get_reached_items($env);
        $bagurl       = $this->get_preset_image_url('bag_open.svg', $this->get_visual_preset())->get_path();

        $output = '<div class="inventory" data-visual="' . $this->get_visual_preset() . '">';
        // inventory-header
        $output .= '<div class="inventory-header">';
        $output .= '<div class="inventory-header-left">';
        $output .= get_string('avatar-inventory', 'format_ludimoodle');
        $output .= '<img src="' . $bagurl . '">';
        $output .= '</div>';

        $output .= '<div class="inventory-header-center">';
        $output .= $this->render_count_items($this->count_items($reacheditems), $this->count_items($items));
        $output .= '</div>';

        $output .= '<div class="inventory-header-right">';
        $output .= '<span class="close-button close-inventory"></span>';
        $output .= '</div>';
        $output .= '</div>'; // inventory-header

        // inventory-content
        $output .= '<div class="inventory-content">';
        foreach ($items as $slotidx => $slotitems) {
            $strslot    = get_string('avatar-'.$this->get_visual_preset().'-item-cat-' . $slotidx, 'format_ludimoodle');

            $output    .= '<div class="inventory-row">';
            $output    .= '<div class="inventory-grid slotname">';
            $output    .= '<span class="dot"></span>' . $strslot;
            $output    .= '</div>';
            $output    .= '</div>';

            $output    .= '<div class="inventory-row" data-slotidx="' . $slotidx . '">';
            $useditem   = $useditems[$slotidx];
            $countitems = count($slotitems);
            $width      = $countitems > 0 ? 100 / $countitems : 10;
            foreach ($slotitems as $itemidx => $item) {
                $isreached = isset($reacheditems[$slotidx]) ? (isset($reacheditems[$slotidx][$itemidx]) ? true : false) : false;
                $isused    = count($useditem) > 0 ? $useditems[$slotidx]['itemidx'] == $itemidx : false;
                $classes   = 'item ';
                $classes   .= $isused ? 'is-used ' : 'not-used ';
                $classes   .= $isreached ? 'is-reached ' : 'not-reached ';
                $output    .= '<div class="inventory-grid" data-itemidx="' . $itemidx . '"  style="width: ' . $width . '%">';
                $output    .= '<img data-itemidx="' . $itemidx . '" class="' . $classes . '" src="' .
                              $this->get_item_icon_url($item, !$isreached) . '">';
                $output    .= '</div>';
            }
            $output .= '</div>';
        }
        $output .= '</div>'; // inventory-content
        $output .= '</div>'; // inventory
        return $output;
    }

    /**
     * Render avatar with background and items
     *
     * @param $env execution_environment
     * @param $section object
     * @return string
     */
    public function render_avatar($env, $section) {
        $output     = '';
        $items      = $this->get_used_items($env);
        $sectionidx = $section->section;
        $bgurl      = $this->get_section_background_url($env, $sectionidx);
        $avatarurl  = $this->get_avatar_url();
        // container
        $output .= '<div class="avatar-container">';

        // background
        $output .= '<div class="avatar-background-image" style="background-image: url(\'' . $bgurl . '\');">';

        // avatar
        $output .= '<img class="avatar-character" src="' . $avatarurl . '" style="z-index:0;">';

        // items
        foreach ($items as $item) {
            if (count($item) == 0) {
                continue;
            }
            $slotidx = $item['slotidx'];
            $itemidx = $item['itemidx'];
            $imgurl  = $this->get_item_img_url($item);
            $output  .= '<img data-slotidx="' . $slotidx . '" data-itemidx="' . $itemidx . '" class="slot-' . $slotidx . '" src="' . $imgurl . '">';
        }
        $output .= '</div>'; // avatar-background-image
        $output .= '</div>'; // avatar-container

        return $output;

    }

    /**
     * @param null $env
     * @return array[slotidx][itemidx] = (array) $item
     */
    public function get_items($env = null) {
        if (($env && $this->envforitems == null) || $this->items == null) {
            $this->items = [];
            $data        = $this->get_visual_preset_data();
            if (!isset($data['items']) || !isset($data['slots']) || !isset($data['sets'])) {
                return $this->items;
            }
            $items      = (array) $data['items'];
            $slots      = (array) $data['slots'];
            $sets       = (array) $data['sets'];

            if ($env) {
                $this->envforitems = true;
                $csections         = $env->get_course_sections();
                $sectionidxs       = array_keys($csections);
            }

            foreach ($items as $itemname => $item) {
                // remove all items which are not in used in sets
                $iteminsets = false;
                foreach ($sets as $set) {
                    $set      = (array) $set;
                    $setitems = (array) $set['items'];
                    foreach ($setitems as $setitem => $setitemobj) {
                        if ($itemname == $setitem) {
                            $iteminsets = true;
                        }
                    }
                }
                if (!$iteminsets || strpos($itemname, '-') === false) {
                    unset($items[$itemname]);
                    continue;
                }
                $item     = (array) $item;
                $iteminfo = explode('-', $itemname);
                $itemidx  = (int) $iteminfo[count($iteminfo) - 1];
                $slotidx  = $slots[$item['slot']]->idx;
                if (!isset($this->items[$slotidx])) {
                    $this->items[$slotidx] = [];
                }
                $item['itemid']  = $itemname;
                $item['itemidx'] = $itemidx;
                $item['slotidx'] = $slotidx;
                $item['itemkey'] = $this->get_itemkey_from_item($item);
                $item['section'] = [];

                // if env so define in which section this item is
                if ($env) {
                    $i = 0;
                    foreach ($sets as $set) {
                        $setitems = (array) $set->items;
                        $sectionidx = isset($sectionidxs[$i]) ? $sectionidxs[$i] : 0;
                        foreach ($setitems as $setitemname => $threshold) {
                            if ($itemname == $setitemname) {
                                if (!isset($item['section'][$sectionidx])) {
                                    $item['section'][$sectionidx] = [];
                                }
                                $item['section'][$sectionidx] = $threshold;
                            }
                        }
                        $i++;
                    }
                }

                $this->items[$slotidx][$itemidx] = $item;
            }
        }
        return $this->items;
    }

    /**
     * return item with specific slotidx and itemidx
     *
     * @param $slotidx int
     * @param null $itemidx - if null : return all items of slot
     * @return array
     */
    public function get_item($slotidx, $itemidx = null) {
        if ($itemidx != null) {
            $items  = $this->get_items();
            $return = isset($items[$slotidx][$itemidx]) ? $items[$slotidx][$itemidx] : [];
        } else {
            $items  = $this->get_items();
            $return = isset($items[$slotidx]) ? $items[$slotidx] : [];
        }
        return $return;
    }

    /**
     * if sectionidx is null, return all items by section
     * else only section items
     *
     * @param null $sectionidx
     * @return array|mixed|null
     */
    public function get_section_items($env, $sectionidx = null) {
        if ($this->sectionitems == null) {
            $items        = $this->get_items($env);
            $sectionitems = [];
            foreach ($items as $slotid => $slotitems) {
                foreach ($slotitems as $item) {
                    // this item is present in sections ...
                    $sectionidxs = [];
                    foreach ($item['section'] as $idx => $threshold) {
                        $sectionidxs[] = $idx;
                    }

                    $slotidx = $item['slotidx'];
                    $itemidx = $item['itemidx'];
                    foreach ($sectionidxs as $idx) {
                        if (isset($item['section'][$idx])) {
                            if (!isset($sectionitems[$idx])) {
                                $sectionitems[$idx] = [];
                            }
                            if (!isset($sectionitems[$idx][$slotidx])) {
                                $sectionitems[$idx][$slotidx] = [];
                            }
                            // add item
                            $sectionitems[$idx][$slotidx][$itemidx] = $item;
                        }
                    }

                }
            }
            $this->sectionitems = $sectionitems;
        }
        $return = $sectionidx == null ? $this->sectionitems :
                (isset($this->sectionitems[$sectionidx]) ? $this->sectionitems[$sectionidx] : []);
        return $return;
    }

    /**
     * get used items of user
     *
     * @param $env
     * @return array
     */
    public function get_used_items($env) {
        $datamine  = $env->get_data_mine();
        $userid    = $env->get_userid();
        $courseid  = $env->get_course_id();
        $data      = $this->get_visual_preset_data();
        $useditems = [];
        $slots     = (array) $data['slots'];
        foreach ($slots as $slotname => $slot) {
            $achievement      = 'useditem-' . $slot->idx;
            $itemkey          = $datamine->get_user_course_achievement($userid, $courseid, $achievement);
            if ($itemkey) {
                $useditems[$slot->idx] = $this->get_item_from_itemkey($itemkey);
            } else {
                $useditems[$slot->idx] = [
                    'slotidx' => $slot->idx,
                    'itemidx' => 0
                ];
            }
        }
        return $useditems;
    }

    /**
     * get section background url
     *
     * @param $sectionidx
     * @return string
     */
    public function get_section_background_url($env, $sectionidx) {
        $data          = $this->get_visual_preset_data();
        $backgrounds   = (array) $data['backgrounds'];
        $sets          = $this->get_sets($env);
        $background = $sets[$sectionidx]->background;
        return $this->get_preset_image_url($backgrounds[$background], $this->get_visual_preset(), 'backgrounds')->get_path();
    }

    /**
     * Return all sets
     *
     * @param $env execution_environment
     * @return array
     */
    public function get_sets($env) {
        $data         = $this->get_visual_preset_data();
        $datasets     = (array) $data['sets'];
        $sets         = [];
        $sectionidxs = array_keys($env->get_course_sections());
        $i = 0;
        // attribute a set to each section
        foreach ($sectionidxs as $sectionidx) {
            // if there is more section than set, reset attribution of set by section
            $i = isset($datasets[$i]) ? $i : 0;
            // attribute a set to the section
            $sets[$sectionidx] = $datasets[$i];
            // next section, other set
            $i++;
        }
        return $sets;
    }

    /**
     * if sectionidx is null, return all reached items
     * else only reached items of section
     *
     * @param $env execution_environment
     * @param null $sectionidx
     * @return array|mixed
     */
    public function get_reached_items($env, $sectionidx = null) {
        if ($this->reacheditems == null) {
            $reacheditems = [];
            $datamine     = $env->get_data_mine();
            $userid       = $env->get_userid();
            $courseid     = $env->get_course_id();
            $sections     = $env->get_course_sections();
            $sectionitems = $this->get_section_items($env);
            foreach ($sectionitems as $idx => $slotitems) {
                if (!isset($sections[$idx])) {
                    continue;
                }
                $section            = $sections[$idx];
                // don't reach items in section without avatar
                $sectionmotivator   = $datamine->get_user_section_achievement($userid, $courseid, $section->id, 'motivator');
                if ($sectionmotivator != $env->text_to_int('avatar')) {
                    continue;
                }
                $reacheditems[$idx] = [];
                $advancement = $datamine->get_user_section_achievement($userid, $courseid, $section->id, 'advancement', 0);
                foreach ($slotitems as $slotidx => $slotitem) {
                    foreach ($slotitem as $itemidx => $item) {
                        if (!isset($item['section'][$idx])) {
                            continue;
                        }
                        $threshold = $item['section'][$idx];
                        if ($advancement >= $threshold) {
                            $slotidx = $item['slotidx'];
                            $itemidx = $item['itemidx'];
                            if (!isset($reacheditems[$idx][$slotidx])) {
                                $reacheditems[$idx][$slotidx] = [];
                            }
                            $reacheditems[$idx][$slotidx][$itemidx] = $item;
                        }
                    }
                }
            }
            $this->reacheditems = $reacheditems;
        }

        $return = [];
        if ($sectionidx == null) {
            foreach ($this->reacheditems as $idx => $slotitems) {
                foreach ($slotitems as $slotidx => $slotitem) {
                    if (!isset($return[$slotidx])) {
                        $return[$slotidx] = [];
                    }
                    foreach ($slotitem as $itemidx => $item) {
                        $return[$slotidx][$itemidx] = $item;
                    }
                }
            }
        } else if (isset($this->reacheditems[$sectionidx])) {
            $return = $this->reacheditems[$sectionidx];
        }
        return $return;
    }

    /**
     * if sectionidx is null, return all missing items
     * else only missing items of section
     *
     * @param $env
     * @param null $sectionidx
     * @return array
     */
    public function get_missing_items($env, $sectionidx = null) {
        $missingitems = [];
        $items        = $this->get_items($env);
        $reacheditems = $this->get_reached_items($env);
        foreach ($items as $slotidx => $slotitems) {
            foreach ($slotitems as $itemidx => $item) {
                if (!isset($reacheditems[$slotidx][$itemidx]) && isset($items[$slotidx][$itemidx])) {
                    if (!isset($missingitems[$slotidx])) {
                        $missingitems[$slotidx] = [];
                    }
                    if ($sectionidx === null || $sectionidx <= 0) {
                        $missingitems[$slotidx][$itemidx] = $items[$slotidx][$itemidx];
                        continue;
                    }
                    foreach ($item['section'] as $idx => $threshold) {
                        if ($sectionidx == $idx) {
                            $missingitems[$slotidx][$itemidx] = $items[$slotidx][$itemidx];
                        }
                    }
                }
            }
        }
        return $missingitems;
    }

    /**
     * Return the next item to reach on a section (with the lower threshold)
     *
     * @param $env
     * @param $sectionidx
     * @return array
     */
    public function get_next_item_to_reach_on_section($env, $sectionidx) {
        $missingitems = $this->get_missing_items_on_section($env, $sectionidx);

        // search in all missing items the item with the lower threshold
        $nextitem    = [];
        foreach ($missingitems as $item) {
            foreach ($item['section'] as $itemsectionidx => $threshold) {
                // first item
                if (!isset($nextitem[$itemsectionidx])) {
                    $nextitem[$itemsectionidx] = $item;
                    continue;
                }

                // this item has a lower threshold ?
                $oldthreshold = $nextitem[$itemsectionidx]['section'][$itemsectionidx];
                if ($threshold < $oldthreshold) {
                    $nextitem[$itemsectionidx] = $item;
                }
            }
        }
        $return = [];
        if (isset($nextitem[$sectionidx])) {
            $return = $nextitem[$sectionidx];
        }
        return $return;
    }

    /**
     * Return all missing items for a sectionidx
     *
     * @param $env
     * @param $sectionidx
     * @return array
     */
    public function get_missing_items_on_section($env, $sectionidx) {
        $sectionitems    = [];
        $missingitems = $this->get_missing_items($env);

        // sort items by section
        foreach ($missingitems as $slotidx => $slotitems) {
            foreach ($slotitems as $itemidx => $item) {
                foreach ($item['section'] as $itemsectionidx => $threshold) {
                    if (!isset($sectionitems[$itemsectionidx])) {
                        $sectionitems[$itemsectionidx] = [];
                    }
                    $sectionitems[$itemsectionidx][] = $item;
                }
            }
        }

        // return section items
        $return = [];
        if (isset($sectionitems[$sectionidx])) {
            $return = $sectionitems[$sectionidx];
        }
        return $return;
    }


    /**
     * Calculate user section advancement and set it
     * return true if advancement increased else false
     *
     * @param $env execution_environment
     * @param $section object
     * @return bool
     */
    public function set_user_section_advancement($env, $section) {
        $itemkey    = false;
        $datamine   = $env->get_data_mine();
        $userid     = $env->get_userid();
        $courseid   = $env->get_course_id();
        $sectionid  = $section->id;
        $sectionidx = $env->get_section_idx();
        $cms        = $env->get_cms_info();
        $oldvalue   = $this->get_user_section_advancement($env, $section);
        $newvalue   = $datamine->calculate_section_advancement($userid, $sectionid, $cms);
        if ($newvalue >= $oldvalue) {
            $missingitems = $this->get_missing_items_on_section($env, $sectionidx);
            foreach ($missingitems as $missingitem) {
                if ($missingitem['section'][$sectionidx] <= $newvalue) {
                    $itemkey = $missingitem['itemkey'];
                    // add new reached item in achievements
                    $datamine->set_user_section_achievement($userid, $courseid, $sectionid, 'reacheditem', $itemkey);
                }
            }
            $datamine->set_user_section_achievement($userid, $courseid, $sectionid, 'advancement', $newvalue);
        }
        // be sure to reinitialize reached items because new items are reached
        $this->reacheditems = null;
        return $itemkey;
    }

    /**
     * An itemkey allows to store an item in the form of an integer
     * An itemkey value is a simple combination of his slotidx * 1000 + itemidx
     * @param $item
     * @return int
     */
    private function get_itemkey_from_item($item) {
        if (!isset($item['slotidx']) || !isset($item['itemidx'])) {
            return 0;
        }
        $itemkey = ((int) $item['slotidx'] * 1000) + (int) $item['itemidx'];
        return $itemkey;
    }

    /**
     * An itemkey allows to store an item in the form of an integer
     * Search in item list an item with slotidx and itemidx corresponding to itemkey and return it
     * @param $itemkey
     * @return array
     */
    private function get_item_from_itemkey($itemkey) {
        if (!is_int($itemkey)) {
            return [];
        }
        $items   = $this->get_items();
        $slotidx = (int) floor($itemkey / 1000);
        $itemidx = $itemkey % 1000;
        $item    = isset($items[$slotidx][$itemidx]) ? $items[$slotidx][$itemidx] : [];
        return $item;
    }

    /**
     * Get image url
     * @param $item
     * @param bool $forcedefault
     * @return string
     */
    public function get_item_img_url($item, $forcedefault = false) {
        global $CFG;
        $itemurl = '';
        if (isset($item['image'])) {
            $itemurl = $this->get_preset_image_url($item['image'], $this->get_visual_preset(), 'items/images');
        }
        if (is_object($itemurl)) {
            $filename = $CFG->dirroot . $itemurl->get_path();
        } else {
            $filename = '';
            $forcedefault = true;
        }
        if (!file_exists($filename) || $forcedefault) {
            $itemurl = $this->get_preset_image_url('item_img_default.svg', $this->get_visual_preset());
        }
        $itemurl = $itemurl->get_path();
        return $itemurl;
    }

    /**
     * Get icon url
     * @param $item
     * @param bool $forcedefault
     * @return string
     */
    public function get_item_icon_url($item, $forcedefault = false) {
        global $CFG;
        $itemurl = '';
        if (isset($item['icon'])) {
            $itemurl = $this->get_preset_image_url($item['icon'], $this->get_visual_preset(), 'items/icons');
        }
        if (is_object($itemurl)) {
            $filename = $CFG->dirroot . $itemurl->get_path();
        } else {
            $filename = '';
            $forcedefault = true;
        }
        if (!file_exists($filename) || $forcedefault) {
            $itemurl = $this->get_preset_image_url('item_icon_default.svg', $this->get_visual_preset());
        }
        $itemurl = $itemurl->get_path();
        return $itemurl;
    }

    /**
     * Get base avatar url
     *
     * @return string
     * @throws \ReflectionException
     * @throws \moodle_exception
     */
    public function get_avatar_url() {
        $itemurl = $this->get_preset_image_url('avatar.svg', $this->get_visual_preset())->get_path();
        return $itemurl;
    }

    /***
     * return count of items
     * you can't just do a count() because items array is a multidimensional array
     *
     * @param $items
     * @return int
     */
    private function count_items($items) {
        $count = 0;
        foreach ($items as $slotitems) {
            $count += count($slotitems);
        }
        return $count;
    }
}