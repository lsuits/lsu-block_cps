<?php

defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/blocks/cps/classes/lib.php';

class block_cps_observer {

    /**
     * UES event: Delete all of this dropped UES section's CPS settings
     *
     * @param  \enrol_ues\event\ues_section_dropped  $event
     * @param  int  other['ues_section_id']
     */
    public static function ues_section_dropped(\enrol_ues\event\ues_section_dropped $event) {

        try {
            $sectionid = $event->other['ues_section_id'];

	        foreach (array('unwant', 'split', 'crosslist', 'team_section') as $setting) {
	            $class = 'cps_' . $setting;

	            $class::delete_all(array('sectionid' => $sectionid));
	        }

	        return true;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * UES event: 
     *
     * @param  \enrol_ues\event\ues_teacher_processed  $event
     * @param  int  other['ues_user_id']
     */
    public static function ues_teacher_processed(\enrol_ues\event\ues_teacher_processed $event) {
        
    	try {
    		$ues_teacher = ues_teacher::by_id($event->other['ues_user_id']);

    		// get CPS course number threshold setting
	        $threshold = get_config('block_cps', 'course_threshold');

	        // get the UES course for this UES teacher's UES section
	        $course = $ues_teacher->section()->course();

	        // if course number exceeds the threshold settings, create unwanted entries for this teacher for this these sections
	        if ($course->cou_number >= $threshold) {
	            
                // specify this teacher's sections
	            $whereTeacherSections = array(
	                'userid' => $ues_teacher->userid,
	                'sectionid' => $ues_teacher->sectionid
	            );

                // get any current unwanted entries
	            $unwant = cps_unwant::get($whereTeacherSections);

                // if there are no current entries, create them now
	            if (empty($unwant)) {
	                
                    $unwant = new cps_unwant();
	                $unwant->fill_params($whereTeacherSections);
	                $unwant->save();
	            }
	        }

	        return true;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * UES event: When a user's preferred name is used, remove it from settings
     *
     * @param  \enrol_ues\event\preferred_name_used  $event
     * @param  int  other['ues_user_id']
     */
    public static function preferred_name_used(\enrol_ues\event\preferred_name_used $event) {

        try {
            $userid = $event->other['ues_user_id'];

            $params = array(
                'userid' => $userid,
                'name' => 'user_firstname'
            );
            
            cps_setting::delete_all($params);
            
            return true;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * UES event: When a UES course is severed, if CPS is configured to allow course deletion, find it moodle course and soft delete it if:
     *
     * 1) if this Moodle course has NO resources or posted grades
     * or 2) if this course's primary teacher has UNWANTED this course
     *
     * @param  \enrol_ues\event\ues_course_severed  $event
     * @param  int  other['moodle_course_id']
     */
    public static function ues_course_severed(\enrol_ues\event\ues_course_severed $event) {

        // is CPS configured to delete courses?
        $shouldBeDeleted = (bool) get_config('block_cps', 'course_severed');

        if ( ! $shouldBeDeleted) {
            return true;
        }

        // get this moodle course
        $courseid = $event->other['moodle_course_id'];

        $course = get_course($courseid);

        global $DB;

        // get any moodle resources for this course
        $moodleResources = $DB->get_records('resource', array('course' => $course->id));

        $grade_items_params = array(
            'courseid' => $course->id,
            'itemtype' => 'course'
        );

        // get any moodle grade items for this course
        $moodleGradeItems = $DB->get_record('grade_items', $grade_items_params);

        $grades = function($moodleGradeItems) use ($DB) {

            // if there are no grade items, no more checks are necessary
            if (empty($moodleGradeItems)) {
                return false;
            }

            // specify this grade item
            $whereThisGradeItem = array('itemid' => $moodleGradeItems->id);
            
            // get count of posted grades for this item
            $gradeCount = $DB->count_records('grade_grades', $whereThisGradeItem);

            // if this course has posted grades, return true
            return  ! empty($gradeCount);
        };

        // if there are no moodle resources or posted grades for this course, delete it now
        if (empty($moodleResources) and ! $grades($moodleGradeItems)) {
            
            delete_course($course, false);
            return true;
        }

        // get any UES sections for this moodle course
        $ues_sections = ues_section::from_course($course);

        // if there are no sections, no need to continue
        if (empty($ues_sections)) {
            return true;
        }

        // get the first UES section from the collection
        $ues_section = reset($ues_sections);

        // get the primary teacher for this UES section
        $primary = $ues_section->primary();

        // specify this user and section
        $whereThisUserAndSection = array (
            'userid' => $primary->userid,
            'sectionid' => $ues_section->id
        );

        // if CPS has an UNWANTED entry for this user and section, delete it now
        if (cps_unwant::get($whereThisUserAndSection)) {
            delete_course($course, false);
        }

        return true;
    }

    /**
     * UES event:
     *
     * @param  \enrol_ues\event\ues_student_data_updated  $event
     * @param  int  other['ues_user_id']
     */
    public static function ues_student_data_updated(\enrol_ues\event\ues_student_data_updated $event) {

        try {
            $ues_user = ues_user::by_id($event->other['ues_user_id']);

            if (property_exists($ues_user, 'user_keypadid')) {
                if (empty($ues_user->user_keypadid)) {
                    cps_profile_field_helper::clear_field_data($ues_user, 'user_keypadid');
                }
            }

            cps_profile_field_helper::process($ues_user, 'user_keypadid');

            return true;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * UES event:
     *
     * @param  \enrol_ues\event\ues_anonymous_updated  $event
     * @param  int  other['ues_user_id']
     */
    public static function ues_anonymous_updated(\enrol_ues\event\ues_anonymous_updated $event) {

        try {
            $ues_user = ues_user::by_id($event->other['ues_user_id']);

            if (property_exists($ues_user, 'user_anonymous_number')) {
                if (empty($ues_user->user_anonymous_number)) {
                    cps_profile_field_helper::clear_field_data($ues_user, 'user_anonymous_number');
                }
            }

            cps_profile_field_helper::process($ues_user, 'user_anonymous_number');

            return true;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Simple Restore event
     *
     * @param  \block_simple_restore\event\simple_restore_complete  $event
     * @param  int  other['userid']
     * @param  int  other['restore_to'] 0,1,2
     * @param  int  other['courseid']
     */
    public static function simple_restore_complete(\block_simple_restore\event\simple_restore_complete $event) {

        try {
            global $DB, $CFG, $USER;
            require_once $CFG->dirroot . '/blocks/cps/classes/lib.php';
            
            $sectionid = $event->other['ues_section_id'];
            $restore_to = $event->other['restore_to'];
            $old_course = get_course($event->other['courseid']);

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
                ues::enrollUsers(ues_section::from_course($course));
            }

            return true;

        } catch (Exception $e) {
            return false;
        }
    }

}