<?php

/**
 *
 * @package    block_cps
 * @copyright  2016 Louisiana State University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $CFG;

require_once $CFG->dirroot . '/blocks/cps/classes/manipulators/base_manipulator.php';
require_once $CFG->dirroot . '/blocks/cps/classes/lib.php';

class enrol_ues_manipulator extends base_manipulator {

    /**
     * Handles a UES section being processed
     * 
     * @param  array  $data[$ues_section]
     * @return array  $response
     */
    public function ues_section_processed($data) {

        $ues_section = $data['ues_section'];

        // get primary UES teacher of this UES section
        $ues_primary = $ues_section->primary();

        // if no "primary" teacher exists, grab the first teacher (we know at least one teacher exists)
        // @TODO - is the choice of teacher arbitrary
        if ( ! $ues_primary) {
            $ues_primary = current($ues_section->teachers());
        }

        // get any UNWANTED preferences for this UES section
        $sectionUnwanted = cps_unwant::get(array(
            'userid' => $ues_primary->userid,
            'sectionid' => $ues_section->id
        ));

        // if UNWANTED, update the UES section's status to pending so it will be ignored, and return
        if ($sectionUnwanted) {
            $ues_section->status = ues::PENDING;
            
            $this->addToResponse('ues_section', $ues_section);

            return $this->response;
        }

        // get any CREATION preferences for this UES section
        $creationSettings = cps_creation::get(array(
            'userid' => $ues_primary->userid,
            'semesterid' => $ues_section->semesterid,
            'courseid' => $ues_section->courseid
        ));

        // if no CREATION settings exist, create default settings now
        if ( ! $creationSettings) {
            $creationSettings = new cps_creation();
            $creationSettings->create_days = get_config('block_cps', 'create_days');
            $creationSettings->enroll_days = get_config('block_cps', 'enroll_days');
        }

        // get UES semester of this UES section
        $ues_semester = $ues_section->semester();
        
        $classes_start = $ues_semester->classes_start;
        
        $diff = $classes_start - time();

        $diff_days = ($diff / 60 / 60 / 24);

        if ($diff_days > $creationSettings->create_days) {
            $ues_section->status = ues::PENDING;
            
            $this->addToResponse('ues_section', $ues_section);

            return $this->response;
        }

        if ($diff_days > $creationSettings->enroll_days) {
            ues_student::reset_status($ues_section, ues::PENDING, ues::PROCESSED);
        }

        foreach (array('split', 'crosslist', 'team_section') as $setting) {
            $class = 'cps_'.$setting;
            $applied = $class::get(array('sectionid' => $ues_section->id));

            if ($applied) {
                $ues_section->idnumber = $applied->new_idnumber();
            }
        }

        $this->addToResponse('ues_section', $ues_section);

        return $this->response;
    }

    /**
     * Handles a UES teacher being released
     *
     * Delete all of this released UES teacher's CPS settings, creations, and team requests
     * 
     * @param  array  $data[$ues_teacher]
     * @return array  $response
     */
    public function ues_teacher_released($data) {
        
        $ues_teacher = $data['ues_teacher'];

        // get currently persisted instance of this teacher, if it exists
        $params = array(
            'userid' => $ues_teacher->userid,
            'sectionid' => $ues_teacher->sectionid,
            'status' => ues::PROCESSED
        );

        $persistedSelf = ues_teacher::get($params);

        // Check for promotion or demotion
        if ($persistedSelf) {
            
            $wasPromoted = $persistedSelf->primary_flag == 1;
            $wasDemoted = $persistedSelf->primary_flag == 0;

        } else {
            $wasPromoted = $wasDemoted = false;
        }

        $allSectionSettings = array('unwant', 'split', 'crosslist');

        if ($wasPromoted) {
            
            // if this UES teacher was promoted, all CPS settings are still intact, no need to continue
            $this->addToResponse('ues_teacher', $ues_teacher);

            return $this->response;

        } else if ($wasDemoted) {
            
            // Demotion means split and crosslist behavior must be effected
            unset($allSectionSettings[0]);
        }

        // specify this teacher
        $whereThisTeacher = array('userid' => $ues_teacher->userid);

        $bySuccessfulDelete = function($in, $setting) use ($whereThisTeacher, $ues_teacher) {
            
            $class = 'cps_' . $setting;
            
            return $in && $class::delete_all($whereThisTeacher + array(
                'sectionid' => $ues_teacher->sectionid
            ));
        };

        // iterate through split and crosslist settings, removing them and returning result of successful deletion
        $success = array_reduce($allSectionSettings, $bySuccessfulDelete, true);
        
        // specify this section's course and semester
        $whereThisSemesterAndCourse = array(
            'courseid' => $ues_teacher->section()->courseid,
            'semesterid' => $ues_teacher->section()->semesterid
        );

        // attempt to delete all CPS creations and team requests, return result of successful complete deletion, including result of settings deletion
        $successfulCompleteDeletion = (
            cps_creation::delete_all($whereThisTeacher + $whereThisSemesterAndCourse) and
            cps_team_request::delete_all($whereThisTeacher + $whereThisSemesterAndCourse) and
            $success
        );

        $this->addToResponse('ues_teacher', $ues_teacher);

        return $this->response;
    }

    /**
     * Handles a UES section's primary being changed by clearing and reprocessing enrollment
     * 
     * @param  array  $data[$ues_section]
     * @param  array  $data[$old_primary]
     * @param  array  $data[$new_primary]
     * @return array  $response
     */
    public function ues_primary_changed($data) {

        $ues_section = $data['ues_section'];

        // Empty enrollment / idnumber
        ues::unenrollUsersBySections(array($ues_section));

        // Safe keeping
        $ues_section->idnumber = '';
        $ues_section->status = ues::PROCESSED;
        $ues_section->save();

        // Set to re-enroll
        ues_student::reset_status($ues_section, ues::PROCESSED);
        ues_teacher::reset_status($ues_section, ues::PROCESSED);

        $this->addToResponse('ues_section', $ues_section);
        $this->addToResponse('old_primary', $old_primary);
        $this->addToResponse('new_primary', $new_primary);

        return $this->response;
    }

    /**
     * Handles renaming a given moodle course based on CPS settings, in addition, handles any user-specific creation settings
     * 
     * @param  array  $data[$moodle_course]
     * @return array  $response
     */
    public function ues_course_created($data) {
        
        $moodle_course = $data['moodle_course'];

        // get all UES sections from this moodle course
        $ues_sections = ues_section::from_course($moodle_course);

        // there are no UES sections for this course, no manipulation necessary
        if (empty($ues_sections)) {
            $this->addToResponse('moodle_course', $moodle_course);

            return $this->response;
        }

        // get the first UES section from this collection
        $ues_section = reset($ues_sections);

        // get the primary UES teacher for this section
        $ues_primary = $ues_section->primary();

        // if there is no primary, no manipulation necessary
        if (empty($ues_primary)) {
            $this->addToResponse('moodle_course', $moodle_course);

            return $this->response;
        }

        $ues_semester = $ues_section->semester();
        
        $sessionKey = $ues_semester->get_session_key();

        $ues_course = $ues_section->course();

        // specify this owner's section's settings
        $whereThisUserAndSection = array(
            'userid' => $ues_primary->userid,
            'sectionid' => $ues_section->id
        );

        // remember moodle course's names
        $fullname = $moodle_course->fullname;
        $shortname = $moodle_course->shortname;

        $a = new stdClass;

        // get course split entries, if any
        $courseSplit = cps_split::get($whereThisUserAndSection);
        
        // if this course is to be split, set attributes to format name
        if ($courseSplit) {
            $a->year = $ues_semester->year;
            $a->name = $ues_semester->name;
            $a->session = $sessionKey;
            $a->department = $ues_course->department;
            $a->course_number = $ues_course->cou_number;
            $a->shell_name = $courseSplit->shell_name;
            $a->fullname = fullname($ues_primary->user());

            $string_key = 'split_shortname';
        }

        // get course crosslist entries, if any
        $courseCrosslist = cps_crosslist::get($whereThisUserAndSection);
        
        // if this course is to be crosslisted, set attributes to format name
        if ($courseCrosslist) {
            $a->year = $ues_semester->year;
            $a->name = $ues_semester->name;
            $a->session = $sessionKey;
            $a->shell_name = $courseCrosslist->shell_name;
            $a->fullname = fullname($ues_primary->user());

            $string_key = 'crosslist_shortname';
        }

        // get course team teach entries, if any
        $courseTeamTeach = cps_team_section::get(array('sectionid' => $ues_section->id));

        // if this course has team teach preferences set, set attributes to format name
        if ($courseTeamTeach) {
            $a->year = $ues_semester->year;
            $a->name = $ues_semester->name;
            $a->session = $sessionKey;
            $a->shell_name = $courseTeamTeach->shell_name;

            $string_key = 'team_request_shortname';
        }

        // if we need to manipulate this course's name
        if (isset($string_key)) {
            
            // get specific pattern based on action taken
            $pattern = get_config('block_cps', $string_key);

            $fullname = ues::format_string($pattern, $a);
            $shortname = ues::format_string($pattern, $a);
        }

        // set moodle course name to updated name, or original name by default
        $moodle_course->fullname = $fullname;
        $moodle_course->shortname = $shortname;

        // if this course is a "fresh" creation and not yet persisted
        if (empty($moodle_course->id)) {

            // get all CPS creation-related settings for this user
            $createSettings = cps_setting::get_all(ues::where()
                ->userid->equal($ues_primary->userid)
                ->name->starts_with('creation_')
            );

            // apply any creation settings set
            foreach ($createSettings as $setting) {
                $key = str_replace('creation_', '', $setting->name);

                $moodle_course->$key = $setting->value;
            }
        }

        $this->addToResponse('moodle_course', $moodle_course);

        return $this->response;
    }

    /**
     * When a moodle user is updated within UES, the user's firstname is updated based on CPS settings, if any
     * 
     * @param  array  $data[$moodle_user]
     * @return array  $response
     */
    public function user_updated($data) {
        
        $moodle_user = $data['moodle_user'];

        // get this user's preferred first name, if any, from CPS settings
        $preferredFirstName = cps_setting::get(array(
            'userid' => $moodle_user->id,
            'name' => 'user_firstname'
        ));

        // if user has no preference set, no need for updating
        if (empty($preferredFirstName)) {
            $this->addToResponse('moodle_user', $moodle_user);
            
            return $this->response;
        }

        // if the currently set first name matches the preferred name, no need for updating
        if ($moodle_user->firstname == $preferredFirstName->value) {
            $this->addToResponse('moodle_user', $moodle_user);
            
            return $this->response;
        }

        // update the user's name
        global $DB;
        $moodle_user->firstname = $preferredFirstName->value;
        $updatedMoodleUser = $DB->update_record('user', $moodle_user);

        $this->addToResponse('moodle_user', $updatedMoodleUser);

        return $this->response;
    }

}
