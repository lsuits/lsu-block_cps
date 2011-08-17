# CPS Enrollment Provider Plugins

Those wanting to add specific behavior to the CPS enrollment process should create
a *CPS Enrollment Provider*.
.
CPS provides an API through inheritance of the 
[enrollment_provider](https://github.com/lsuits/cps/blob/master/classes/provider.php) abstract class.

A provider must override the following sources:

 * `semester_source`: returns an array of semesters or `cps_semester`s.
 * `course_source`: returns an array of course and sections or `cps_course`s.
 * `teacher_source`: returns an array of teachers or `cps_teacher`s.
 * `student_source`: returns an array of students or `cps_student`s.
 