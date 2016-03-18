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

class block_ues_people_manipulator extends base_manipulator {

    /**
     * Adds meta data to UES people data
     * 
     * @param  array  $data[$output]
     * @return array  $response
     */
    public function add_meta_data_to_output($data) {

        $output = $data['output'];

        // get all meta data keys to be added to output, in the required order
        $metaData = array(
            'sec_number', 
            'credit_hours', 
            'user_degree', 
            'user_ferpa',
            'user_keypadid', 
            'user_college', 
            'user_major', 
            'user_year',
            'user_reg_status'
        );

        $_s = ues::gen_str('block_cps');

        // add meta data to output
        foreach ($metaData as $meta) {
            
            // @TODO - what is going on here?
            if ( ! isset($output[$meta])) {
                $output[$meta] = new cps_people_element($meta, $_s($meta));
            }
            
            unset($output[$meta]);
            
            $output[$meta] = new cps_people_element($meta, $_s($meta));
        }

        $this->addToResponse('output', $output);

        return $this->response;
    }

    /**
     * Adds audit data to UES people data
     * 
     * @param  array  $data[$course]
     * @param  array  $data[$output]
     * @return array  $response
     */
    public function add_audit_data_to_output($data) {

        $output = $data['output'];

        // get UES sections for this course
        $sections = ues_section::from_course($data['course']);

        // If one of them contains LAW, then display student_audit
        $is_law = false;
        foreach ($sections as $section) {
            if ($is_law) break;

            $is_law = $section->course()->department == 'LAW';
        }

        if ($is_law) {
            $output['student_audit'] = new post_grades_audit_people();
        }

        $this->addToResponse('output', $output);

        return $this->response;
    }

}
