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
 * Events declarations.
 * 
 * @package    block_cps
 * @copyright  2014, Louisiana State University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$gen_mapper = function ($module) {
    return function ($event) use ($module) {
        return array(
            'handlerfile' => '/blocks/cps/events/'.$module.'.php',
            'handlerfunction' => array('cps_' . $module . '_handler', $event),
            'schedule' => 'instant'
        );
    };
};

$modules_events = array(
    'ues' => array(
        'ues_primary_change',
        'ues_teacher_process',
        'ues_teacher_release',
        'ues_section_process',
        'ues_section_drop',
        'ues_semester_drop',
        'ues_course_create',
        'ues_course_severed',
        'ues_lsu_student_data_updated',
        'ues_xml_student_data_updated',
        'ues_lsu_anonymous_updated',
        'ues_xml_anonymous_updated',
        'ues_group_emptied',
        'user_updated',
        'preferred_name_legitimized'
    ),
    'simple_restore' => array(
        'simple_restore_complete'
    ),
    'ues_meta_viewer' => array(
        'ues_user_data_ui_keys',
        'ues_user_data_ui_element'
    ),
    'ues_people' => array(
        'ues_people_outputs'
    )
);

$handlers = array();

foreach ($modules_events as $module => $events) {
    $mapper = $gen_mapper($module);

    $handlers += array_combine($events, array_map($mapper, $events));
}
