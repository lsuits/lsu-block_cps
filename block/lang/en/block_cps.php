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

$string['department'] = 'Department';
$string['cou_number'] = 'Course Number';

$string['material_shortname'] = 'Master Course {department} {course_number} for {fullname}';

// Error Strings
$string['not_enabled'] = 'CPS Setting <strong>{$a}</strong> is not enabled.';
$string['not_teacher'] = 'You are not enrolled or set to be enrolled in any course.
If you believe that you should be, please contact the Moodle administrator for
immediate assistance.';
$string['no_section'] = 'You do not own any section in the capable role. If you
 believe that you do, please contact the Moodle administrator for immediate assistance.';

$string['no_courses'] = 'You do have have any courses with at least two active
sections.';

$string['err_enrol_days'] = 'Enrollments days cannot be >= Create Days.';
$string['err_number'] = 'Days entered must be greater than 0.';

$string['err_manage_one'] = 'You must select at least one request.';

$string['err_select'] = 'The selected course does not exist.';
$string['err_split_number'] = 'The selected course does not have two sections.';
$string['err_select_one'] = 'You must select a course to continue.';

$string['err_same_semester'] = 'You must select courses in the same semester.
You first selected {$a->year} {$a->name}';
$string['err_not_enough'] = 'You must select at least two courses.';
$string['err_one_shell'] = 'Each shell must have two sections.';

$string['err_team_query'] = 'Please provide both fields.';
$string['err_team_query_course'] = 'Course does not exists:
{$a->department} {$a->cou_number}';

$string['err_team_query_sections'] = '{$a->year} {$a->name} {$a->department}
{$a->cou_number} does not have sections';

$string['err_select_teacher'] = 'You must select at least one Instructor';

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

$string['crosslist_option_taken'] = 'Cross-list option taken';
$string['crosslist_no_option'] = '(No option taken)';

$string['crosslist_updating'] = 'Updating your cross-list selections';
$string['crosslisted'] = 'is cross-listed into <strong>{$a->shell_name}</strong>';

$string['crosslist_select'] = 'Select Courses to be Cross-listed';
$string['crosslist_shells'] = 'Course Shells';
$string['crosslist_decide'] = 'Separate Sections';
$string['crosslist_confirm'] = 'Review';
$string['crosslist_update'] = 'Update';

$string['crosslist_processed'] = 'Cross-list Courses Processed';

$string['crosslist_thank_you'] = 'Your cross-list courses have been processed. Continue
to head back to the cross-list home screen.';

// Team Requests
$string['team_request'] = 'Team Teach Requests';

$string['team_query_for'] = 'Query a course: {$a->year} {$a->name}';

$string['team_teachers'] = 'Select one or more Instructors';

$string['review_selection'] = 'Please reivew your selections';

$string['team_note'] = '<strong>Note</strong>';
$string['team_going_email'] = 'The instructors you have selected will receive
an email from you, inviting them to team teach. You can revoke team teach
privileges at any time.';

$string['team_how_many'] = 'How many courses will you combine?';

$string['team_request_option'] = 'Team Teach option taken';

$string['team_section'] = 'Course Sections';

$string['team_continue_build'] = 'Continue to configure sections.';

$string['team_section_select'] = 'Sections';
$string['team_section_shells'] = 'Course Shells';
$string['team_section_decide'] = 'Separate Sections';
$string['team_section_confirm'] = 'Review';
$string['team_section_update'] = 'Update';

$string['team_section_note'] = 'You must wait until the owner of this request
has created shells to work in.';

$string['team_section_no_permission'] = 'You do not have permission to change
this section. You can only move the sections you own.';

$string['team_section_finished'] = 'Team Sections Processed';
$string['team_section_processed'] = 'The Team Teach Sections have been processed.
Continue to head back to the Team Section home.';

$string['team_section_option'] = '(Team Section option taken)';

$string['team_request_select'] = 'Select a Course';
$string['team_request_shells'] = 'Course Requests';
$string['team_request_query'] = 'Query a Course';
$string['team_request_request'] = 'Select Instructor';
$string['team_request_review'] = 'Review Requests';
$string['team_request_finish'] = 'Request Sent';
$string['team_request_update'] = 'Updating';
$string['team_request_confirm'] = 'Confirm Actions';

$string['team_request_manage'] = 'Manage Requests';

$string['team_following'] = 'The current requests';
$string['team_approved'] = 'Approved';
$string['team_not_approved'] = 'Not Approved';
$string['team_current'] = 'Manage invites to current courses';
$string['team_add_course'] = 'Make additional requests';
$string['team_manage_requests'] = 'Manage Requests';
$string['team_manage_sections'] = 'Manage Sections';

$string['team_to_approve'] = 'Requests to Approve';
$string['team_to_revoke'] = 'Requests to Cancel';

$string['team_revoke'] = 'Revoke';
$string['team_approve'] = 'Approve';
$string['team_do_nothing'] = 'Do Nothing';
$string['team_deny'] = 'Deny';
$string['team_cancel'] = 'Cancel';
$string['team_actions'] = 'Actions';
$string['team_requested_courses'] = 'Requested Courses';

$string['team_reshell'] = 'How many courses to add?';

$string['team_request_thank_you'] = 'The Team Teach requests have been processed
and sent. Continue to head back to the team teach home.';

$string['team_with'] = 'to be team taught with...';

$string['team_request_shortname'] = '{year} {name} {shell_name} for {fullnames}';
