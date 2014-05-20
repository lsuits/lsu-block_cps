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
defined('MOODLE_INTERNAL') or die();

if ($ADMIN->fulltree) {
    require_once $CFG->dirroot . '/enrol/ues/publiclib.php';
    require_once $CFG->dirroot . '/blocks/cps/settingslib.php';

    // using the public lib for string generation
    $_s = ues::gen_str('block_cps');
    $_m = ues::gen_str('moodle');

    $settings->add(new admin_setting_heading('block_cps_settings', '',
        $_s('pluginname_desc')));

    $settings->add(new admin_setting_configcheckbox('block_cps/course_severed',
        $_s('course_severed'), $_s('course_severed_desc'), 0));

    $settings->add(new admin_setting_configtext('block_cps/course_threshold',
        $_s('course_threshold'), $_s('course_threshold_desc'), '8000'));

    $field_cats = $DB->get_records_menu('user_info_category', null, '', 'id, name');

    if (!empty($field_cats)) {
        $first = key($field_cats);

        $settings->add(new admin_setting_configselect('block_cps/user_field_catid',
            $_s('user_field_category'), $_s('user_field_category_desc'), $first, $field_cats));
    }

    $cps_settings = array(
        'setting', 'creation', 'unwant', 'material',
        'split', 'crosslist', 'team_request'
    );

    foreach ($cps_settings as $setting) {
        $settings->add(new admin_setting_heading('block_cps_'.$setting.'_settings',
            $_s($setting), ''));

        $settings->add(new admin_setting_configcheckbox('block_cps/'.$setting,
            $_s('enabled'), $_s('enabled_desc'), 1));

        setting_processor::$setting($settings, $_s);
    }
}

