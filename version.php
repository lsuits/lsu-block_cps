<?php

/**
 * @package   block_cps
 * @copyright 2016, Louisiana State University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'block_cps';
$plugin->version = 2016040800;
$plugin->requires = 2015111600;
$plugin->release = 'v3.0.0';

$plugin->dependencies = array(
    'enrol_ues' => 2016040800,
);