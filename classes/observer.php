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

        $course = get_course($courseid)

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

}