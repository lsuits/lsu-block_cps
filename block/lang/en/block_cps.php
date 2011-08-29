<?php

$string['pluginname'] = 'Course Preferences';
$string['pluginname_desc'] = 'The Course Preference block allows instructors to
control craetion and enrollment behavior. These are system wide defaults for
those who do not actually set any data.';

$string['course_severed'] = 'Delete upon Severage';
$string['course_severed_desc'] = 'A course is severed if the Moodle course will
not longer be handled by the enrollment module, or if enrollment equals zero.';

$string['enabled'] = 'Enabled';
$string['enabled_desc'] = 'If disabled, the setting will be hidden from the
instructor and the preexisting settings will **not** be executed.';

$string['nonprimary'] = 'Allow Non-Primaries';
$string['nonprimary_desc'] = 'If checked, then Non-Primaries will be able to
configure the CPS settings.';

// Error Strings
$string['not_enabled'] = 'CPS Setting <strong>{$a}</strong> is not enabled.';
$string['not_teacher'] = 'You are not enrolled or set to be enrolled in any course.
If you believe that you should be, please contact the Moodle administrator for
immediate assistance.';
$string['no_section'] = 'You do not own any section in the capable role. If you believe that you do, please contact the Moodle administrator for immediate assistance.';

// Setting names
$string['creation'] = 'Creation / Enrollment';

$string['create_days'] = 'Days before Creation';
$string['create_days_desc'] = 'The number of days before sections are created.';

$string['enroll_days'] = 'Days before Enrollment';
$string['enroll_days_desc'] = 'The number of days before **created** sections
are enrolled.';

$string['unwant'] = 'Unwanted';
$string['material'] = 'Materials Course';
$string['split'] = 'Splliting';

$string['shortname_desc'] = 'Split course creation uses these defaults.';
$string['split_shortname'] = '{year} {name} {department} {course_number}
{shell_name} for {fullname}';

$string['crosslist'] = 'Cross-listing';
$string['crosslist_shortname'] = '{year} {name} {shell_name} for {fullname}';

$string['team_request'] = 'Team Teach Requests';
$string['team_request_shortname'] = '{year} {name} {shell_name} for {fullnames}';
