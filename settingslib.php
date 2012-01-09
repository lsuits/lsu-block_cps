<?php

// To be added in settings.php only
class setting_processor {
    public static function creation($settings, $_s) {
        $days = array_combine(range(1, 120), range(1, 120));

        $settings->add(new admin_setting_configselect('block_cps/create_days',
            $_s('create_days'), $_s('create_days_desc'), 30, $days));

        $settings->add(new admin_setting_configselect('block_cps/enroll_days',
            $_s('enroll_days'), $_s('enroll_days_desc'), 14, $days));
    }

    public static function unwant($settings) {
    }

    public static function material($settings, $_s) {
        self::nonprimary('material', $settings, $_s, 1);
        self::shortname('material', $settings, $_s);
    }

    public static function split($settings, $_s) {
        self::shortname('split', $settings, $_s);
    }

    public static function crosslist($settings, $_s) {
        self::shortname('crosslist', $settings, $_s);
    }

    public static function team_request($settings, $_s) {
        self::nonprimary('team_request', $settings, $_s, 0);
        self::shortname('team_request', $settings, $_s);

        $settings->add(new admin_setting_configtext('block_cps/team_request_limit', $_s('team_request_limit'), $_s('team_request_limit_desc'), 10));
    }

    private static function shortname($setting, $settings, $_s) {
        $settings->add(new admin_setting_configtext('block_cps/'.$setting.'_shortname',
            get_string('shortname'), $_s('shortname_desc'),
            $_s($setting.'_shortname')));
    }

    private static function nonprimary($setting, $settings, $_s, $default = 0) {
        $settings->add(new admin_setting_configcheckbox(
            'block_cps/'.$setting.'_nonprimary', $_s('nonprimary'),
            $_s('nonprimary_desc'), $default));
    }
}

