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

$string['no_courses'] = 'You do have have any courses with at least two active
sections.';

$string['err_enrol_days'] = 'Enrollments days cannot be >= Create Days.';
$string['err_number'] = 'Days entered must be greater than 0.';

$string['err_select'] = 'The selected course does not exist.';
$string['err_split_number'] = 'The selected course does not have two sections.';
$string['err_select_one'] = 'You must select a course to continue.';

$string['err_same_semester'] = 'You must select courses in the same semester. 
You first selected {$a->year} {$a->name}';
$string['err_not_enough'] = 'You must select at least two courses.';

// Setting names
$string['default_settings'] = 'Default Settings';
$string['creation'] = 'Creation / Enrollment';

$string['create_days'] = 'Days before Creation';
$string['create_days_desc'] = 'The number of days before sections are created.';

$string['enroll_days'] = 'Days before Enrollment';
$string['enroll_days_desc'] = 'The number of days before **created** sections
are enrolled.';

$string['default_create_days'] = 'Days before classes to create courses';
$string['default_enroll_days'] = 'Days before classes to enroll students';

$string['unwant'] = 'Unwanted';

$string['material'] = 'Materials Course';
$string['creating_materials'] = 'Create master courses';

$string['split'] = 'Splitting';

$string['split_select'] = 'Select a course';
$string['split_shells'] = 'Course Shells';
$string['split_decide'] = 'Separate Sections';
$string['split_confirm'] = 'Review';
$string['split_update'] = 'Update';

$string['split_how_many'] = 'How many separate course shells would you like to have created?';
$string['next'] = 'Next';
$string['back'] = 'Back';

$string['split_processed'] = 'Split Courses Processed';
$string['split_thank_you'] = 'Your split selections have been processed. Conintue
to head back to the split home screen.';

$string['chosen'] = 'Please review your selections.';
$string['available_sections'] = 'Your Sections:';
$string['move_left'] = '<';
$string['move_right'] = '>';
$string['split_option_taken'] = 'Split option taken';
$string['split_updating'] = 'Updating your split selections';
$string['split_undo'] = 'Undo these courses?';
$string['split_reshell'] = 'Reassign the number of shells?';
$string['split_rearrange'] = 'Rearrange sections?';

$string['customize_name'] = 'Customize name';

$string['shortname_desc'] = 'Split course creation uses these defaults.';
$string['split_shortname'] = '{year} {name} {department} {course_number}
{shell_name} for {fullname}';

$string['crosslist'] = 'Cross-listing';
$string['crosslist_shortname'] = '{year} {name} {shell_name} for {fullname}';
$string['crosslist_you_have'] = 'You have selected to cross-list';

$string['crosslist_select'] = 'Select Courses to be Cross-listed';
$string['crosslist_shells'] = 'Course Shells';
$string['crosslist_decide'] = 'Separate Sections';

$string['team_request'] = 'Team Teach Requests';
$string['team_request_shortname'] = '{year} {name} {shell_name} for {fullnames}';
