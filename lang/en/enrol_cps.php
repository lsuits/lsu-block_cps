<?php

$string['pluginname'] = 'CPS Enollment';
$string['pluginname_desc'] = 'The CPS (Course Preference System) enrollment module is a pluggable enrollment system that adheres to common university
criterion including Semesters, Courses, Sections tied to coures, and teacher and student enrollment tied to Sections.

The Moodle enrollment module will scan for behaviors defined in *{$a}*. A fully defined behavior will show up in the dropdown below.';

$string['user_settings'] = 'User Creation Settings';
$string['user_email'] = 'E-mail suffix';
$string['user_email_desc'] = 'The created user will have this email domain appended to their username.';
$string['user_confirm'] = 'Confirmed';
$string['user_confirm_desc'] = 'The user will be "confirmed" upon creation.';
$string['user_city'] = 'City/town';
$string['user_city_desc'] = 'The created user will have this default city assigned to them.';
$string['user_country'] = 'Country';
$string['user_country_desc'] = 'The created user will have this default country assigned to them.';

$string['provider'] = 'Enrollment Provider';
$string['provider_desc'] = 'This enrollment provider will be used to pull enrollment data.';

$string['provider_problems'] = 'Provider Cannot be Instantiated';
$string['provider_problems_desc'] = '
*{$a->pluginname}* cannot be instantiated with the current settings.

**Problem**: {$a->problem}

This will cause the enrollment plugin to abort in cron. Please address
these errors.

**Note to Developers**: Consider using the `adv_settings` for server side
validation of settings.';

$string['no_provider'] = 'No Enrollment Provider selected.';

$string['provider_settings'] = '{$a} Settings';

$string['provider_cron_problem'] = 'Could not instantiate {$a->pluginname}: {$a->problem}. Check provider configuration.';

/** Behavior Strings go here */
$string['lsu_name'] = 'LSU Enrollment Provider';

$string['lsu_credential_location'] = 'Credential Location';
$string['lsu_credential_location_desc'] = 'For security purposes, the login credentials for the LSU web service is stored on a local secure server. This is the complete url to access the credentials.';

$string['lsu_wsdl_location'] = 'SOAP WSDL';
$string['lsu_wsdl_location_desc'] = 'This is the wsdl used in SOAP requests to LSU\'s Data Access Service. The Moodle data directory *{$a->dataroot}* is assumed as the path base.';

$string['lsu_bad_file'] = 'Provide a *.wsdl* file';
$string['lsu_no_file'] = 'The WSDL does not exists in wsdl_location';
$string['lsu_bad_url'] = 'Provide a valid url (define either a http or https protocol)';
$string['lsu_bad_resp'] = 'Invalid credentials in credential location request';
