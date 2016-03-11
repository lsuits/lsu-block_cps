<?php

/**
 *
 * @package    block_cps
 * @copyright  2016 Louisiana State University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class base_manipulator {

    /**
     * Response container
     *
     * @var array
     */
    public $response = array();

    /**
     * Adds data to the response container for the given key name
     * 
     * @param  string  $key
     * @param  mixed   $value
     */
    public function addToResponse($key, $value) {

        $this->response[$key] = $value;

    }

}
