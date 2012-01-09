<?php

$string['pluginname'] = 'Course Preferences';
$string['pluginname_desc'] = 'The Course Preference block allows instructors to
control creation and enrollment behavior. These are system wide defaults for
those who do not actually set any data.';

$string['course_threshold'] = 'Course Number Threshold';
$string['course_threshold_desc'] = 'Sections belonging to a course number that
is greater than or equal to the specified number, will not be initially created.
CPS will create unwanted entries for these sections so the instructor can opted
in teaching online.';

$string['course_severed'] = 'Delete upon Severage';
$string['course_severed_desc'] = 'A course is severed if the Moodle course will
no longer be handled by the enrollment module, or if enrollment equals zero.';

$string['enabled'] = 'Enabled';
$string['enabled_desc'] = 'If disabled, the setting will be hidden from the
instructor. A Moodle admin who is logged in as the instructor will still be able to
see and manipulate the disabled setting.';

$string['nonprimary'] = 'Allow Non-Primaries';
$string['nonprimary_desc'] = 'If checked, then Non-Primaries will be able to
configure the CPS settings.';

$string['department'] = 'Department';
$string['cou_number'] = 'Course Number';

$string['material_shortname'] = 'Master Course {department} {course_number} for {fullname}';

$string['team_request_limit'] = 'Number of Requests';
$string['team_request_limit_desc'] = 'This is the the maximum number of requests a primary instructor can make (minimum of 1).';

// Error Strings
$string['not_enabled'] = 'CPS Setting <strong>{$a}</strong> is not enabled.';
$string['not_teacher'] = 'You are not enrolled or set to be enrolled in any course.
If you believe that you should be, please contact the Moodle administrator for
immediate assistance.';
$string['no_section'] = 'You do not own any section in the capable role. If you
 believe that you do, please contact the Moodle administrator for immediate assistance.';

$string['no_courses'] = 'You do not have any courses with at least two active
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

$string['select'] = 'Select a course';
$string['shells'] = 'Course Shells';
$string['decide'] = 'Separate Sections';
$string['confirm'] = 'Review';
$string['update'] = 'Update';
$string['loading'] = 'Applying';

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
$string['split_shortname'] = '{year} {name} {department} {course_number} {shell_name} for {fullname}';

$string['crosslist'] = 'Cross-listing';
$string['crosslist_shortname'] = '{year} {name} {shell_name} for {fullname}';
$string['crosslist_you_have'] = 'You have selected to cross-list';

$string['crosslist_option_taken'] = 'Cross-list option taken';
$string['crosslist_no_option'] = '(No option taken)';

$string['crosslist_updating'] = 'Updating your cross-list selections';
$string['crosslisted'] = 'is cross-listed into <strong>{$a->shell_name}</strong>';

$string['crosslist_processed'] = 'Cross-list Courses Processed';

$string['crosslist_thank_you'] = 'Your cross-list courses have been processed. Continue
to head back to the cross-list home screen.';

$string['crosslist_select'] = 'Select courses to Cross-list';

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

$string['team_section_note'] = 'You must wait until the owner of this request
has created shells to work in.';

$string['team_section_no_permission'] = 'You do not have permission to change
this section. You can only move the sections you own.';

$string['team_section_finished'] = 'Team Sections Processed';
$string['team_section_processed'] = 'The Team Teach Sections have been processed.
Continue to head back to the Team Section home.';

$string['team_section_option'] = '(Team Section option taken)';

$string['team_request_shells'] = 'Course Requests';
$string['query'] = 'Query a Course';
$string['request'] = 'Select Instructor';
$string['review'] = 'Review Requests';
$string['team_request_finish'] = 'Request Sent';
$string['team_request_update'] = 'Updating';
$string['team_request_confirm'] = 'Confirm Actions';

$string['manage'] = 'Manage Requests';

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

$string['team_request_shortname'] = '{year} {name} {shell_name}';

$string['team_request_approved_subject'] = 'Moodle Team-Teaching Request Accepted';
$string['team_request_approved_body'] = '
{$a->requester},

{$a->requestee} has accepted your invitation to team-teach your {$a->course}
with his/her {$a->other_course} course.  All instructors and students of
{$a->other_course} will be enrolled within your {$a->course} course.';

$string['team_request_invite_subject'] = 'Moodle Team-Teaching Request';
$string['team_request_invite_body'] = '
{$a->requestee},

{$a->requester} has invited you and your students from your {$a->other_course}
course to participate in a team-taught course with his/her {$a->course}
course. If you accept this invitation, you and your students will be added
and you will be made a non-primary instructor.

Please click the following link to accept or reject {$a->requester}\'s request:
{$a->link}';

$string['team_request_reject_subject'] = 'Moodle Team-Teaching Request Rejected';
$string['team_request_reject_body'] = '
{$a->requester},

{$a->requestee} has rejected your invitation to team-teach your {$a->course}
course with his/her {$a->other_course} course.';

$string['team_request_revoke_subject'] = 'Moodle Team-Teaching Request Revoked';
$string['team_request_revoke_body'] = '
{$a->requestee},

{$a->requester} has revoked the invitation to team-teach your {$a->other_course}
course with his/her {$a->course} course. All instructors and students from
your {$a->other_course} course will be unenrolled from {$a->course}.';

$string['settings_loading'] = '{$a} - Applying Changes';
$string['please_wait'] = 'Your settings are being applied. Please be patient as the process completes.';

$string['application_errors'] = 'The following error occurred while applying the settings: {$a}';
