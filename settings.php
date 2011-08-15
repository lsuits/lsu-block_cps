<?php

defined('MOODLE_INTERNAL') or die();

if ($ADMIN->fulltree) {
    require_once dirname(__FILE__) . '/lib.php';

    $plugins = enrol_cps_plugin::list_plugins();

    $_s = enrol_cps_plugin::gen_str();

    $settings->add(new admin_setting_heading('enrol_cps_settings', '', $_s('pluginname_desc', enrol_cps_plugin::plugin_base())));

    $settings->add(new admin_setting_configselect('enrol_cps/enrollment_provider',
        $_s('provider'), $_s('provider_desc'), 'lsu', $plugins));

    $provider = enrol_cps_plugin::provider_class();

    if ($provider) {
        $reg_settings = $provider::settings();

        $adv_settings = $provider::adv_settings();

        if ($reg_settings or $adv_settings) {
            $plugin_name = $_s($provider::get_name() . '_name');
            $settings->add(new admin_setting_heading('provider_settings', $_s('provider_settings', $plugin_name), ''));
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
            $provider_name = $provider::get_name();
            $problem = $_s($provider_name . '_' . $e->getMessage());

            $a = new stdClass;
            $a->pluginname = $_s($provider_name.'_name');
            $a->problem = $problem;

            $settings->add(new admin_setting_heading('provider_problem',
                $_s('provider_problems'), $_s('provider_problems_desc', $a)));
        }
    }
}
