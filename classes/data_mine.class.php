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

class data_mine {
    //---------------------------------------------------------------------------------------------
    // Private data
    private $cache = [];
    private $db;

    private $achievements = [];

    //---------------------------------------------------------------------------------------------
    // Public function
    public function __construct() {
        global $DB;
        $this->db = $DB;
    }

    public function flush_changes_to_database() {
        $this->flush_achievements();
    }

    //---------------------------------------------------------------------------------------------
    // Base getters

    /**
     * Return 1 if cm if completed, 0 if is not, and false if cm doesn't use completion
     *
     * @param $userid
     * @param $cmid
     * @return mixed
     */
    public function get_cm_completion($userid, $cmid) {

        // generate an appropriate unique cache key
        $cachekey = __FUNCTION__ . ':' . $userid . ':' . $cmid;

        // if the item doesn't exist yet in the cache then generate it and store it away
        if (!array_key_exists($cachekey, $this->cache)) {
            $this->cache[$cachekey] = $this->fetch_cm_completion($userid, $cmid);
        }

        // return the result
        return $this->cache[$cachekey];
    }

    /**
     * Return object(grade, grademax) or false if cm doesn't use grade
     *
     * @param $userid
     * @param $cmid
     * @return mixed
     * @throws \dml_exception
     */
    public function get_cm_grade($userid, $cmid) {

        // generate an appropriate unique cache key
        $cachekey = __FUNCTION__ . ':' . $userid . ':' . $cmid;

        // if the item doesn't exist yet in the cache then generate it and store it away
        if (!array_key_exists($cachekey, $this->cache)) {
            $this->cache[$cachekey] = $this->fetch_cm_grade_info($userid, $cmid);
        }

        // return the result
        return $this->cache[$cachekey];
    }

    /**
     * Section Context : get scores for all questions answered by the user in the current section in chronological order
     * @return array of score-fraction
     */
    public function get_section_answer_stats($userid, $courseid, $sectionid){
        // generate an appropriate unique cache key
        $cachekey = __FUNCTION__ . ':' . $userid . ':' . $courseid . '#' . $sectionid;

        // if the item doesn't exist yet in the cache then generate it and store it away
        if (! array_key_exists($cachekey, $this->cache)){
            $this->cache[$cachekey] = $this->fetch_section_answer_stats($userid, $courseid, $sectionid);
        }

        // return the result
        return $this->cache[$cachekey];
    }

    //---------------------------------------------------------------------------------------------
    // Base fetch

    protected function fetch_cm_completion($userid, $cmid) {
        $cminfosql
                = '
            SELECT cm.id, cm.instance, m.name as modname, cm.module, cm.course, cm.completion
            FROM {course_modules} cm
            JOIN {modules} m ON cm.module = m.id
            WHERE cm.id = :cmid 
        ';
        $cm     = $this->db->get_record_sql($cminfosql, array('cmid' => $cmid));

        $functionname = $cm->modname . '_supports';
        if ($cm->completion === 0 ||
            ($functionname(FEATURE_COMPLETION_TRACKS_VIEWS) == false && $functionname(FEATURE_COMPLETION_HAS_RULES) == false)) {
            return false;
        }

        $iscompleted = $this->db->record_exists('course_modules_completion',
                array('userid' => $userid, 'coursemoduleid' => $cmid, 'completionstate' => 1));

        return $iscompleted ? 1 : 0;
    }

    /**
     * @param $userid
     * @param $cmid
     * @return bool|int|mixed|null
     * @throws \dml_exception
     */
    protected function fetch_cm_grade_info($userid, $cmid) {
        global $CFG;

        $cminfosql
                = '
            SELECT cm.id, cm.instance, m.name as modname, cm.module, cm.course
            FROM {course_modules} cm
            JOIN {modules} m ON cm.module = m.id
            WHERE cm.id = :cmid 
        ';

        $cm = $this->db->get_record_sql($cminfosql, array('cmid' => $cmid));

        $functionname = $cm->modname . '_supports';
        if ($functionname(FEATURE_GRADE_HAS_GRADE) == false) {
            return false;
        }

        // Get grade items, if grade items doesn't exist, mod is not graded
        $gradeitemsql = '
            SELECT gi.id, gi.grademax
            FROM {grade_items} gi
            WHERE gi.courseid = :courseid
            AND gi.iteminstance = :instance
            AND gi.itemmodule = :modname
        ';
        $gradeitem = $this->db->get_record_sql($gradeitemsql, array(
                'courseid' => $cm->course, 'instance' => $cm->instance, 'modname' => $cm->modname
        ));

        if (!$gradeitem) {
            return false;
        }

        $gradeinfo = null;

        // Delegate to activity data_mine if exists
        $moddataminepath = $CFG->dirroot . '/course/format/ludimoodle/classes/data_mines/' . $cm->modname . '_data_mine.class.php';
        if (file_exists($moddataminepath)) {
            require_once($moddataminepath);
            $classname   = __NAMESPACE__ . '\\' . $cm->modname . '_data_mine';
            $moddatamine = new $classname();
            if (method_exists($moddatamine, 'fetch_grades')) {
                $grades = $moddatamine->fetch_grades($cm->instance, $userid);
                // Get best grade
                foreach ($grades as $attemptgrade) {
                    if (!$gradeinfo || $attemptgrade->grade > $gradeinfo->grade) {
                        $gradeinfo = $attemptgrade;
                    }
                }
            }
        }

        // Get max grade in grades table
        if ($gradeinfo == null) {
            $gradesql = '
                SELECT MAX(gg.finalgrade) as grade
                FROM {grade_grades} gg 
                WHERE gg.itemid = :gradeitemid
                AND gg.userid = :userid 
            ';
            $gradeinfo = $this->db->get_record_sql($gradesql, array('gradeitemid' => $gradeitem->id, 'userid' => $userid));

            // User has no grade for now
            if (!$gradeinfo || $gradeinfo->grade == null) {
                return 0;
            }
            $gradeinfo->grademax = $gradeitem->grademax;
        }

        return $gradeinfo;
    }

    public function fetch_attempt_answers($attemptid) {
        global $CFG;

        // TODO check cm module type
        require_once($CFG->dirroot . '/course/format/ludimoodle/classes/data_mines/quiz_data_mine.class.php');
        $moddatamine = new quiz_data_mine();
        return $moddatamine->fetch_attempt_answers($attemptid);
    }


    protected function fetch_section_answer_stats($userid, $courseid, $sectionid){
        global $CFG;
        // TODO check cm module type
        require_once($CFG->dirroot . '/course/format/ludimoodle/classes/data_mines/quiz_data_mine.class.php');
        // fetch the latest grade set, calculating new grades as required
        $moddatamine = new quiz_data_mine();
        $ludigrades = $moddatamine->fetch_raw_ludigrades_for_section($userid, $courseid, $sectionid);
        // construct and return the result
        $result = [];
        foreach ($ludigrades as $record){
            $result[] = $record->maxgrade ? ( $record->grade / $record->maxgrade ) : 0;
        }
        return $result;
    }


    public function fetch_attempt_info($attemptid) {
        global $CFG;

        // TODO check cm module type
        require_once($CFG->dirroot . '/course/format/ludimoodle/classes/data_mines/quiz_data_mine.class.php');
        $moddatamine = new quiz_data_mine();
        return $moddatamine->fetch_attempt_info($attemptid);
    }

    //---------------------------------------------------------------------------------------------
    // Calculate

    /**
     * Grade all activity on section
     * @param $userid
     * @param $sectionid
     * @param $cms
     * @return int
     */
    public function calculate_section_advancement($userid, $sectionid, $cms) {
        $gradetotal    = 0;
        $maxgradetotal = 0;
        foreach ($cms as $cm) {
            if ($cm->section != $sectionid) {
                continue;
            }
            $gradeinfo = $this->get_cm_grade($userid, $cm->id);
            if (is_object($gradeinfo) && $gradeinfo->grademax > 0) {
                $gradetotal    += $gradeinfo->grade;
                $maxgradetotal += $gradeinfo->grademax;
            }
        }
        $score = $maxgradetotal > 0 ? ($gradetotal / $maxgradetotal) * 100 : 0;
        return (int) round($score);
    }

    /**
     * Grade one activity
     * @param $userid
     * @param $cmid
     * @return int
     */
    public function calculate_cm_advancement($userid, $cmid) {
        $gradeinfo = $this->get_cm_grade($userid, $cmid);
        if (is_object($gradeinfo) && $gradeinfo->grademax > 0) {
            // grade x/100 (ex : 5/20 => 500/20 => 25)
            $advancement = round($gradeinfo->grade * 100 / $gradeinfo->grademax);
        } else {
            $advancement = 0;
        }
        return $advancement;
    }

    /**
     * Calculate how many answers are good without one fault
     * @param $userid
     * @param $courseid
     * @param $sectionid
     * @return array|int|mixed
     */
    public function calculate_section_correct_run($userid, $courseid, $sectionid) {
        $answers = $this->get_section_answer_stats($userid, $courseid, $sectionid);
        $correctrun = [];
        if (count($answers) == 0) {
            return 0;
        }
        $maxarraykey = max(array_keys($answers));
        $count  = 0;
        foreach ($answers as $key => $grade) {
            if ($grade){
                $count++;
            } else {
                $correctrun[] = $count;
                $count = 0;
            }
            if ($key == $maxarraykey) {
                $correctrun[] = $count;
            }

        }
        $correctrun = count($correctrun) > 0 ? max($correctrun) : 0;
        return $correctrun;
    }

    //---------------------------------------------------------------------------------------------
    // Achievement Store getters

    /**
     * Achievement Store : Getter for course-context achievement
     *
     * @return int|null achievement value from data mine or $defaultvalue if not found in data mine
     */
    public function get_user_course_achievement($userid, $courseid, $achievement, $defaultvalue = null) {
        $prefix = 'C:' . $courseid . ':';
        return $this->get_user_achievement($userid, $prefix, $achievement, $defaultvalue);
    }

    /**
     * Achievement Store : Getter for section-context achievement
     *
     * @return int|null achievement value from data mine or $defaultvalue if not found in data mine
     */
    public function get_user_section_achievement($userid, $courseid, $sectionid, $achievement, $defaultvalue = null) {
        $prefix = 'S:' . $courseid . '#' . $sectionid . ':';
        return $this->get_user_achievement($userid, $prefix, $achievement, $defaultvalue);
    }

    /**
     * Achievement Store : Getter for quiz-context achievement
     *
     * @return int|null achievement value from data mine or $defaultvalue if not found in data mine
     */
    public function get_user_mod_achievement($userid, $courseid, $cmid, $achievement, $defaultvalue = null) {
        $prefix = 'M:' . $courseid . '-' . $cmid . ':';
        return $this->get_user_achievement($userid, $prefix, $achievement, $defaultvalue);
    }



    //---------------------------------------------------------------------------------------------
    // Achievement Store setters

    /**
     * Achievement Store : Setter for course-context achievement set
     *
     * @return void 
     */
    public function set_user_course_achievement($userid, $courseid, $achievement, $value) {
        $prefix = 'C:' . $courseid . ':';
        $this->set_user_achievements($userid, $prefix, $achievement, $value);
    }

    /**
     * Achievement Store : Setter for section-context achievement set
     *
     * @return void 
     */
    public function set_user_section_achievement($userid, $courseid, $sectionid, $achievement, $value) {
        $prefix = 'S:' . $courseid . '#' . $sectionid . ':';
        $this->set_user_achievements($userid, $prefix, $achievement, $value);
    }

    /**
     * Achievement Store : Setter for quiz-context achievement set
     *
     * @return void 
     */
    public function set_user_mod_achievement($userid, $courseid, $cmid, $achievement, $value) {
        $prefix = 'M:' . $courseid . '-' . $cmid . ':';
        $this->set_user_achievements($userid, $prefix, $achievement, $value);
    }

    //---------------------------------------------------------------------------------------------
    // Private Achievement function

    private function get_user_achievement($userid, $prefix, $achievement, $defaultvalue) {
        $storedachievements = $this->get_user_achievements($userid, $prefix);
        $key                = $prefix . $achievement;
        return array_key_exists($key, $storedachievements) ? $storedachievements[$key] : $defaultvalue;
    }

    protected function get_user_achievements($userid, $prefix) {
        // grab the achievements set for the current user, fetching from DB if required
        $userachievements = $this->load_user_achievements($userid);
        $allachievements  = $userachievements->changes + $userachievements->cache;

        // filter the achievements container to only include results matching prefix
        $result = [];
        foreach ($allachievements as $key => $value) {
            if (substr($key, 0, strlen($prefix)) === $prefix) {
                $result[$key] = (int) $value;
            }
        }

        // return the result
        return $result;
    }

    /**
     * Private methods for use by Achievement Store setters
     */
    protected function set_user_achievements($userid, $prefix, $achievement, $value) {
        // grab the achievements set for the current user, fetching from DB if required
        $userachievements = $this->load_user_achievements($userid);

        // store away the achievement in the appropriate array for future use
        $userachievements->changes[$prefix . $achievement] = $value;
    }

    /**
     * Private methods for use by Achievement Store getters & setters
     */
    private function load_user_achievements($userid) {
        // grab the achievements set for the current user, fetching from DB if required
        if (array_key_exists($userid, $this->achievements)) {
            return $this->achievements[$userid];
        }

        // fetch achievements from the database, ordering by id in order to ensure that more recent values overwrite older ones in subsequent processing
        global $DB;
        $dbresult = $DB->get_records('ludimoodle_achievements', ['userid' => $userid], 'id');

        // construct associative array of achievement names to values, storing internally for future reuse
        $userachievements = (object) ['cache' => [], 'changes' => []];
        $achievementcache = &$userachievements->cache;
        foreach ($dbresult as $record) {
            $achievementcache[$record->achievement] = $record->value;
        }

        // store and return the result
        $this->achievements[$userid] = $userachievements;
        return $userachievements;
    }

    /**
     * Achievement Store : Flush any new achievement values to DB
     */
    private function flush_achievements() {
        global $DB;
        $timenow = time();
        $inserts = [];
        foreach ($this->achievements AS $userid => $userachievements) {
            foreach ($userachievements->changes AS $key => $value) {
                // ignore entries that haven't changed
                if (array_key_exists($key, $userachievements->cache) &&
                    $userachievements->cache[$key] == $userachievements->changes[$key]) {
                    continue;
                }

                // ignore entries with null value
                if (is_null($value)) {
                    continue;
                }

                // queue up the insertion for bulk application at the end
                $inserts[] = (object) [
                        'userid' => $userid, 'achievement' => $key, 'value' => $value, 'timestamp' => $timenow,
                ];
            }
            // cleanup the user record to avoid re-injection of the same data on subsequent flushes
            $userachievements->cache   = $userachievements->changes + $userachievements->cache;
            $userachievements->changes = [];
        }

        // if we have new records then flush them to the database
        if ($inserts) {
            $DB->insert_records('ludimoodle_achievements', $inserts);
        }
    }
}