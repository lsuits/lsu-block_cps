<?php

defined('MOODLE_INTERNAL') or die();

if ($ADMIN->fulltree) {
    require_once dirname(__FILE__) . '/cps_enrollment.class.php';

    $plugins = cps_enrollment::list_plugins();

    $_s = cps_enrollment::gen_str();

    $settings->add(new admin_setting_heading('enrol_cps_settings', '',
        $_s('pluginname_desc', cps_enrollment::plugin_base())));

    $settings->add(new admin_setting_configselect('enrol_cps/enrollment_provider',
        $_s('provider'), $_s('provider_desc'), 'lsu', $plugins));

    $settings->add(new admin_setting_heading('enrol_cps_user_settings',
        $_s('user_settings'), ''));

    $settings->add(new admin_setting_configtext('enrol_cps/user_email',
        $_s('user_email'), $_s('user_email_desc'), '@example.com'));

    $settings->add(new admin_setting_configcheckbox('enrol_cps/user_confirm',
        $_s('user_confirm'), $_s('user_confirm_desc'), 1));

    $settings->add(new admin_setting_configtext('enrol_cps/user_city',
        $_s('user_city'), $_s('user_city_desc'), ''));

    $countries = get_string_manager()->get_list_of_countries();
    $settings->add(new admin_setting_configselect('enrol_cps/user_country',
        $_s('user_country'), $_s('user_country_desc'), $CFG->country, $countries));

    $settings->add(new admin_setting_heading('enrol_cps_enrol_settings',
        $_s('enrol_settings'), ''));

    $roles = $DB->get_records_menu('role', null, '', 'id, name');

    foreach (array('editingteacher', 'teacher', 'student') as $shortname) {
        $typeid = $DB->get_field('role', 'id', array('shortname' => $shortname));

        $settings->add(new admin_setting_configselect('enrol_cps/'.$shortname.'_role',
            $_s($shortname.'_role'), $_s($shortname.'_role_desc'), $typeid ,$roles));
    }

    $provider = cps_enrollment::provider_class();

    if ($provider) {
        $reg_settings = $provider::settings();

        $adv_settings = $provider::adv_settings();

        if ($reg_settings or $adv_settings) {
            $plugin_name = $_s($provider::get_name() . '_name');
            $settings->add(new admin_setting_heading('provider_settings',
                $_s('provider_settings', $plugin_name), ''));
        }

        if ($reg_settings) {
            foreach ($reg_settings as $key => $default) {
                $actual_key = $provider::get_name() . '_' . $key;
                $settings->add(new admin_setting_configtext('enrol_cps/'.$actual_key,
                    $_s($actual_key), $_s($actual_key.'_desc', $CFG), $default));
            }
        }

        if ($adv_settings) {
            foreach ($adv_settings as $setting) {
                $settings->add($setting);
            }
        }

        try {
            // Attempting to create the provider
            new $provider();
        } catch (Exception $e) {
            $a = cps_enrollment::translate_error($e);

            $settings->add(new admin_setting_heading('provider_problem',
                $_s('provider_problems'), $_s('provider_problems_desc', $a)));
        }
    }
}
