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
 * Strings for component 'format_ludimoodle', language 'en'ludimoodle
 *
 * @package    format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     Céline Hernandez <celine@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['addsections']                   = 'Add section';
$string['currentsection']                = 'This section';
$string['editsection']                   = 'Edit section';
$string['editsectionname']               = 'Edit section name';
$string['deletesection']                 = 'Delete section';
$string['newsectionname']                = 'New name for section {$a}';
$string['sectionname']                   = 'Section';
$string['pluginname']                    = 'Ludic motivators format';
$string['section0name']                  = 'General';
$string['page-course-view-ludimoodle']   = 'Any course main page in ludic motivators format';
$string['page-course-view-ludimoodle-x'] = 'Any course page in ludic motivators format';
$string['hidefromothers']                = 'Hide section';
$string['showfromothers']                = 'Show section';
$string['privacy:metadata']              = 'The ludic motivators format plugin does not store any personal data.';

// Capabilities
$string['ludimoodle:manageludification'] = 'Manage course ludification';

// Custom fields
$string['nextmotivator-desc']           = 'Permet de définir le prochain motivateur qui sera appliqué à un étudiant';
$string['sectioncanhavemotivator']      = 'If activate this section can have a motivator';
$string['sectioncanhavemotivatorlabel'] = 'This section can have a motivator ?';
$string['sectioncanhavemotivator_help'] = 'This section can have a motivator ?';

// Navigation
$string['nav-course']            = 'Cours';
$string['nav-section']           = 'Leçon';
$string['nav-mod']               = 'Exercice';
$string['how-to-nav-in-course']  = 'Cliquer sur une leçon pour afficher son contenu.';
$string['how-to-nav-in-section'] = 'Cliquer sur un exercice pour afficher son contenu.';

// Motivators
$string['motivator-explanation-title'] = 'Fonctionnement';
$string['none']                        = '';
$string['none-description']            = 'Si tu es dans une leçon tu verras ici le fonctionnement de ton élément ludique.';
// change after experimentation
$string['nomotivator']                 = 'Questionnaire';
$string['nomotivator-description']     = 'Réponds à toutes les questions, puis valide le questionnaire.';

// Reports
$string['btn-reset-achievements'] = 'Reset';
$string['btn-activereport']       = 'Rapport dynamique';
$string['activereport-message']   = 'Données depuis aujourd\'hui minuit, mises à jour en quasi temps réel.';
$string['btn-report']             = 'Rapport complet';
$string['quizreport-title']       = 'Rapport des quiz du cours {$a}';
$string['noquiz']                 = 'Aucun quiz';
$string['noattempt']              = 'Aucune tentative';
$string['header-student']         = 'Élève';
$string['header-questionnumber']  = 'Q.{$a}';
$string['icon-gradedright']       = '<i class="icon fa fa-check text-success"></i>';
$string['icon-gradedwrong']       = '<i class="icon fa fa-times text-error"></i>';
$string['icon-todo']              = '<i class="icon fa fa-question"></i>';

// loadup the string tables for the motivators
require_once dirname(dirname(__DIR__)) . '/lib/presets_lib.php';

$motivators = format_ludimoodle_get_plugin_motivators();
foreach ($motivators as $motivator) {
    require_once dirname(dirname(__DIR__)) . '/motivators/' . $motivator . '/main.php';
    $classname    = '\\format_ludimoodle\\motivator_' . $motivator;
    $objmotivator = new $classname;
    foreach ($objmotivator->get_loca_strings('en') as $key => $value) {
        $string[$key] = $value;
    }
}