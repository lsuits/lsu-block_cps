<?php

$string['pluginname'] = 'CPS Enrollment';
$string['pluginname_desc'] = 'The CPS (Course Preference System) enrollment module is a pluggable enrollment system that adheres to common university
criterion including Semesters, Courses, Sections tied to coures, and teacher and student enrollment tied to Sections.

The Moodle enrollment module will scan for behaviors defined in _{$a}_. A fully defined behavior will show up in the dropdown below.';

$string['semester_cleanup'] = 'Semester Cleanup';
$string['reprocess_failures'] = 'Reprocess Failures';

$string['reprocess_count'] = 'Found {$a} error(s)';

$string['reprocess'] = 'Reprocess';
$string['reprocess_all'] = $string['reprocess'] . ' All';
$string['reprocess_selected'] = $string['reprocess'] . ' Selected';
$string['reprocess_success'] = 'Reprocessing errors';

$string['delete'] = 'Delete';
$string['delete_all'] = $string['delete'] . ' All';
$string['delete_selected'] = $string['delete'] . ' Selected';
$string['delete_success'] = 'Successfully deleted errors';

$string['no_errors'] = 'Congratulations! You have handled all the enrollment errors.';

$string['cron_run'] = 'Daily Cron';
$string['cron_run_desc'] = 'Enable the daily cron run, or run cron manually.';

$string['running'] = 'Currently Running';
$string['running_desc'] = 'If this is checked then it either means that the process is still running, or the process died unexpectingly. Uncheck this if you think the process should be enabled.

__Note__: One of the easiest ways to know the process has ended is to enable email logs.';

$string['cron_hour'] = 'Starting Hour';
$string['cron_hour_desc'] = 'Start the automatic cron on this hour.';

$string['error_threshold'] = 'Error Threshold';
$string['error_threshold_desc'] = 'The process will only automatically reprocess errors that occurred during the cron run whose numbers are less than or equal to the specified threshold.

__Note__: This setting only applies if _'.$string['cron_run'].'_ is enabled';

$string['error_threshold_log'] = 'There are too many errors to reprocess automatically. Either clear out the error queue through the settings page, or raise the threshold number.';

$string['error_params'] = 'Parameters';
$string['error_when'] = 'Timestamp';

$string['general_settings'] = 'General Settings';
$string['management'] = 'Internal Links';
$string['management_links'] = '
Below are some internal links to manage the enrollment data.

* ['.$string['semester_cleanup'].']({$a->cleanup_url})
* ['.$string['reprocess_failures'].']({$a->failure_url})
';

$string['email_report'] = 'Email Logs';
$string['email_report_desc'] = 'Email CPS execution log to all admins.

__Note__: Any errors will be reported regardless.';

$string['user_settings'] = 'User Creation Settings';
$string['user_email'] = 'E-mail suffix';
$string['user_email_desc'] = 'The created user will have this email domain appended to their username.';
$string['user_confirm'] = 'Confirmed';
$string['user_confirm_desc'] = 'The user will be _confirmed_ upon creation.';
$string['user_city'] = 'City/town';
$string['user_city_desc'] = 'The created user will have this default city assigned to them.';
$string['user_country'] = 'Country';
$string['user_country_desc'] = 'The created user will have this default country assigned to them.';

$string['course_settings'] = 'Course Creation Settings';
$string['course_visible_desc'] = 'Upon creation the course will be visible to students.';
$string['course_shortname_desc'] = 'Generated Shortname for the course';
$string['course_shortname'] = '{year} {name} {department} {session} {course_number} for {fullname}';
$string['course_format_desc'] = 'Creates a course that uses this format as its default.';
$string['course_numsections_desc'] = 'Creates a course with this many sections as its default.';

$string['provider'] = 'Enrollment Provider';
$string['provider_desc'] = 'This enrollment provider will be used to pull enrollment data.';

$string['provider_problems'] = 'Provider Cannot be Instantiated';
$string['provider_problems_desc'] = '
_{$a->pluginname}_ cannot be instantiated with the current settings.

__Problem__: {$a->problem}

This will cause the enrollment plugin to abort in cron. Please address
these errors.

__Note to Developers__: Consider using the `adv_settings` for server side
validation of settings.';

$string['no_provider'] = 'No Enrollment Provider selected.';

$string['provider_settings'] = '{$a} Settings';

$string['provider_cron_problem'] = 'Could not instantiate {$a->pluginname}: {$a->problem}. Check provider configuration.';

$string['enrol_settings'] = 'User Enrollment Settings';
$string['student_role'] = 'Students';
$string['student_role_desc'] = 'CPS students will be enrolled in this Moodle role';
$string['editingteacher_role'] = 'Primary Instructor';
$string['editingteacher_role_desc'] = 'CPS *primary* teachers will be enrolled in this Moodle role';
$string['teacher_role'] = 'Non-Primary Instructor';
$string['teacher_role_desc'] = 'CPS *non-primary* teachers will be enrolled in this Moodle role';

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

$string['lsu_student_data'] = 'Process Student Data';
$string['lsu_student_data_desc'] = 'This will enable processing student data in the `postprocess` section of the LSU provider';

$string['lsu_anonymous_numbers'] = 'Process LAW Numbers';
$string['lsu_anonymous_numbers_desc'] = 'This will enable processing anonymous numbers in the `postprocess` section of the LSU provider';

$string['lsu_degree_candidates'] = 'Process Degree Candidacy';
$string['lsu_degree_candidates_desc'] = 'This will enabled processing degree candidate information in the `postprocess` section of the LSU provider';

// Fake for testing
$string['fake_name'] = 'Fake Source Enrollment Provider';
$string['fake_course_variant'] = 'Course Variations';
$string['fake_course_variant_desc'] = 'These are course department variations.';
$string['fake_section_variant'] = 'Section Variation';
$string['fake_section_variant_desc'] = 'These are the section varation for each course department.';
$string['fake_teacher_variant'] = 'Teacher Variation';
$string['fake_teacher_variant_desc'] = 'Teachers per section (min: 1)';
$string['fake_student_variant'] = 'Student Variation';
$string['fake_student_variant_desc'] = 'Students per section (min: 10)';

$string['fake_invalid_student'] = 'Must have at least one student';
$string['fake_invalid_teacher'] = 'Must have at least one teacher';
$string['fake_invalid_course'] = 'Must have at least one course';
$string['fake_invalid_sections'] = 'Must have at least one section';

$string['fake_linkables'] = 'Fake Provider External Links';
$string['fake_cleanup'] = 'Cleanup';
$string['fake_cleanup_desc'] = 'Warning: this runs truncate on all the CPS tables.';

$string['fake_cleanuprun'] = 'Cleanup on run';
$string['fake_cleanuprun_desc'] = 'Runs _Cleanup_ in provider `postprocess`';

$string['no_semester'] = 'The semester you have selected does not exists.';
$string['no_semesters'] = 'There are no semesters in your system. Consider running the enrollment process.';

$string['drop_semester'] = 'Drop {$a->year} {$a->name} {$a->campus} {$a->session_key} and all associated data';
$string['year'] = 'Year';
$string['campus'] = 'Campus';
$string['session_key'] = 'Session';
$string['sections'] = 'Sections';
$string['in_session'] = 'In Session?';
