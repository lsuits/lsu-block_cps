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

}
