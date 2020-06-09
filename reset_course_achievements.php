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
 * @package    course
 * @subpackage format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     celine <celine@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once '../../../config.php';

$id = required_param('id', PARAM_INT); // course id
$confirmed = optional_param('confirmed', 0, PARAM_INT);

if(!is_siteadmin()){
    print_error('Vous devez être administrateur de la plateforme');
}

$course = get_course($id);
$context = context_course::instance($course->id);

$PAGE->set_pagelayout('incourse');
$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/course/format/ludimoodle/reset_course_achievements.php', array('id' => $id)));
$PAGE->set_title('Réinitialiser ' . $course->fullname);

if($confirmed && confirm_sesskey()){

    $test = $DB->execute('
        DELETE FROM {ludimoodle_achievements}
        WHERE achievement LIKE "%S'.$course->id.'%"
    ');

    echo $OUTPUT->header();
    echo '<p>Suppression terminée</p>';
    $returnurl = $CFG->wwwroot . '/course/view.php?id='.$id;
    echo '<a href="'.$returnurl.'"><button class="btn">Retour</button></a>';
    echo $OUTPUT->footer();
    die();
}

echo $OUTPUT->header();

echo '<p>Êtes-vous sûr de vouloir supprimer toutes les données stockées par les motivateurs dans ce cours ? 
<br> Pour une action complète, <strong>vous devez réinitialiser le cours avant de réaliser cette action</strong>.
<br> <strong>Cette action est irréversible !</strong> </p> <br><br>';


$returnurl = $CFG->wwwroot . '/course/view.php?id='.$id;
echo '<a href="'.$returnurl.'"><button class="btn">Annuler</button></a>';
$confirmedurl = $CFG->wwwroot . '/course/format/ludimoodle/reset_course_achievements.php?id='. $id . '&confirmed=1&sesskey=' .  sesskey();
echo '<a href="'.$confirmedurl.'"><button class="btn btn-primary">Valider</button></a>';

echo $OUTPUT->footer();