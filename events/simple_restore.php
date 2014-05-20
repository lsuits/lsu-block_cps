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
 * @package    block_cps
 * @copyright  2014 Louisiana State University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class cps_simple_restore_handler {
    public static function simple_restore_complete($params) {
        global $DB, $CFG, $USER;
        require_once $CFG->dirroot . '/blocks/cps/classes/lib.php';

        extract($params);
        $restore_to = $course_settings['restore_to'];
        $old_course = $course_settings['course'];

        $skip = array(
            'id', 'category', 'sortorder',
            'sectioncache', 'modinfo', 'newsitems'
        );

        $course = $DB->get_record('course', array('id' => $old_course->id));

        $reset_grades = cps_setting::get(array(
            'name' => 'user_grade_restore',
            'userid' => $USER->id
        ));

        // Defaults to reset grade items
        if (empty($reset_grades)) {
            $reset_grades = new stdClass;
            $reset_grades->value = 1;
        }

        // Maintain the correct config
        foreach (get_object_vars($old_course) as $key => $value) {
            if (in_array($key, $skip)) {
                continue;
            }

            $course->$key = $value;
        }

        $DB->update_record('course', $course);

        if ($reset_grades->value == 1) {
            require_once $CFG->libdir . '/gradelib.php';

            $items = grade_item::fetch_all(array('courseid' => $course->id));
            foreach ($items as $item) {
                $item->plusfactor = 0.00000;
                $item->multfactor = 1.00000;
                $item->update();
            }

            grade_regrade_final_grades($course->id);
        }

        // This is an import, ignore
        if ($restore_to == 1) {
            return true;
        }

        $keep_enrollments = (bool) get_config('simple_restore', 'keep_roles_and_enrolments');
        $keep_groups = (bool) get_config('simple_restore', 'keep_groups_and_groupings');

        // No need to re-enroll
        if ($keep_groups and $keep_enrollments) {
            $enrol_instances = $DB->get_records('enrol', array(
                'courseid' => $old_course->id,
                'enrol' => 'ues'
            ));

            // Cleanup old instances
            $ues = enrol_get_plugin('ues');

            foreach (array_slice($enrol_instances, 1) as $instance) {
                $ues->delete_instance($instance);
            }

        } else {
            $sections = ues_section::from_course($course);

            // Nothing to do
            if (empty($sections)) {
                return true;
            }

            // Rebuild enrollment
            ues::enroll_users(ues_section::from_course($course));
        }

        return true;
    }
}
