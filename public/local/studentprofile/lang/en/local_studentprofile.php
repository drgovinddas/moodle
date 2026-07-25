<?php
/**
 * Language strings for local_studentprofile.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// General.
$string['pluginname'] = 'Student Profile Completion';
$string['completeprofile'] = 'Complete Profile';
$string['profilecompletionrequired'] = 'Profile Completion Required';
$string['profilecompletiondesc'] = 'Please complete your profile information to continue accessing the dashboard.';

// Student name fields.
$string['firstname'] = 'First Name';
$string['firstname_help'] = 'Your first name as per official documents.';
$string['middlename'] = 'Middle Name';
$string['middlename_help'] = 'Your middle name (optional).';
$string['lastname'] = 'Last Name';
$string['lastname_help'] = 'Your last name/surname as per official documents.';
$string['rollno'] = 'Roll Number';
$string['rollno_help'] = 'Your assigned roll number (optional). If updated, it will require administrator approval.';

// College fields.
$string['collegename'] = 'College Name';
$string['collegename_help'] = 'Select your college from the list, or choose "Other" to enter a custom name.';
$string['collegeshortname'] = 'Short Name';
$string['collegeshortname_help'] = 'Short abbreviation of the college name (e.g. MIT, IIT-B).';
$string['collegelongname'] = 'Long Name';
$string['collegelongname_help'] = 'The full formal/official name of the college.';
$string['customcollegename'] = 'Custom College Name';
$string['customcollegename_help'] = 'Enter the full name of your college if not listed above.';
$string['othercollege'] = 'Other (enter manually)';

// Degree fields.
$string['degree'] = 'Degree';
$string['degree_help'] = 'The degree you are currently pursuing.';
$string['degreetype'] = 'Degree Type';
$string['degreetype_help'] = 'Select whether your degree is Undergraduate (UG) or Postgraduate (PG).';
$string['ugpg_ug'] = 'UG (Undergraduate)';
$string['ugpg_pg'] = 'PG (Postgraduate)';

// Admission year.
$string['admissionyear'] = 'Admission Year';
$string['admissionyear_help'] = 'The year you were admitted to the college.';

// Draft / auto-save.
$string['draftsaved'] = 'Draft saved';
$string['draftresumelink'] = 'Resume Profile Completion';
$string['draftresumebanner'] = 'You have an incomplete profile. <a href="{$a}">Click here to resume</a>.';

// Admin settings.
$string['settings'] = 'Student Profile Settings';
$string['enablecompletion'] = 'Enable Mandatory Completion';
$string['enablecompletion_desc'] = 'If enabled, users will be forced to complete their profile upon their first login.';
$string['admissionyears'] = 'Admission Year Range';
$string['admissionyears_desc'] = 'Enter the start and end year separated by a hyphen, e.g., 2010-2030.';
$string['managestudentprofiles'] = 'Manage Student Profiles';
$string['managestudentprofiles_desc'] = 'View and manage student profile details, including roll numbers.';
$string['importprofiles'] = 'Import Profiles (CSV)';
$string['importprofiles_desc'] = 'Upload a CSV file to bulk import or update student profiles. The primary matching column is email. Required columns: email, firstname, lastname, college_shortname.';
$string['exportprofiles'] = 'Export Profiles (CSV)';
$string['editprofile'] = 'Edit Profile';
$string['managecolleges'] = 'Manage Colleges';
$string['managecolleges_desc'] = 'Add, edit, or delete colleges available in the student profile form.';
$string['syncnmc'] = 'Sync Colleges from NMC';
$string['syncnmc_success'] = 'Successfully synced from NMC! Inserted {$a->inserted} new colleges and updated {$a->updated} existing colleges.';
$string['syncnmc_error'] = 'Failed to fetch or parse data from NMC. Please try again later.';
$string['managedegrees'] = 'Manage Degrees';
$string['managedegrees_desc'] = 'Add, edit, or delete degree options available in the student profile form.';

// Admin college page.
$string['addcollege'] = 'Add College';
$string['editcollege'] = 'Edit College';
$string['deletecollege'] = 'Delete College';
$string['confirmdelete'] = 'Are you sure you want to delete this entry?';
$string['sortorder'] = 'Sort Order';
$string['collegesaved'] = 'College saved successfully.';
$string['collegedeleted'] = 'College deleted.';

// Admin degree page.
$string['adddegree'] = 'Add Degree';
$string['editdegree'] = 'Edit Degree';
$string['deletedegree'] = 'Delete Degree';
$string['degreename'] = 'Degree Name';
$string['degreesaved'] = 'Degree saved successfully.';
$string['degreedeleted'] = 'Degree deleted.';

// Filter strings.
$string['filtercollege'] = 'Filter by College';
$string['filterdegree'] = 'Filter by Degree';
$string['filteradmissionyear'] = 'Filter by Admission Year';
$string['filterdegreetype'] = 'Filter by Degree Type';

// Event strings.
$string['eventprofilecompleted'] = 'Student profile completed';

// Error strings.
$string['errorinvalidcollege'] = 'Invalid college selected.';
$string['errorinvaliddegree'] = 'Invalid degree selected.';
$string['errorinvalidyear'] = 'Invalid admission year selected.';
$string['errorsavedraft'] = 'Unable to save draft. Please try again.';

// Privacy.
$string['privacy:metadata:tableexplanation'] = 'Stores the student profile completion data including college details, name, admission year, degree, and timestamps.';
$string['privacy:metadata:userid'] = 'The ID of the user whose profile data is stored.';
$string['privacy:metadata:collegename'] = 'The college name of the user.';
$string['privacy:metadata:fullname'] = 'The full name of the user.';
$string['privacy:metadata:firstname'] = 'The first name of the user.';
$string['privacy:metadata:middlename'] = 'The middle name of the user.';
$string['privacy:metadata:lastname'] = 'The last name of the user.';
$string['privacy:metadata:shortname'] = 'The college short name.';
$string['privacy:metadata:longname'] = 'The college long name.';
$string['privacy:metadata:admissionyear'] = 'The admission year of the user.';
$string['privacy:metadata:degree'] = 'The degree of the user.';
$string['privacy:metadata:degreetype'] = 'Whether the degree is UG or PG.';
$string['privacy:metadata:timecreated'] = 'The time when the profile was completed.';
$string['privacy:metadata:timemodified'] = 'The time when the profile was last modified.';

// Rules Engine
$string['managerules'] = 'Manage Course Assignment Rules';
$string['managerulesdesc'] = 'Automatically assign courses to students based on profile criteria upon completion.';
$string['addnewrule'] = 'Add New Rule';
$string['runrulesengine'] = 'Run Rules Engine on All Profiles';
$string['priority'] = 'Priority';
$string['rulename'] = 'Rule Name';
$string['stopprocessing'] = 'Stop Processing';
$string['stopprocessing_help'] = 'If checked, further rules will not be evaluated if this rule matches.';
$string['courses'] = 'Courses to Assign';
$string['status'] = 'Status';
$string['conditions'] = 'Conditions';
$string['editrule'] = 'Edit Rule';
