<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 *  batchgroup
 *
 *  This plugin will import group assignments
 *  from a delimited text file. It does not create new user accounts
 *  in Moodle, or enrol users in a course.
 *
 * @author      Peter Beare
 * @copyright   2021 Peter Beare
 * @copyright   based on 'local_userenrols' by Fred Woolard <woolardfa@appstate.edu>(c) 2013 Appalachian State Universtiy, Boone, NC
 * @license     GNU General Public License version 3
 * @package     local_batchgroup
 */

defined('MOODLE_INTERNAL') || die(); //obligatory


$string['pluginname']               = 'Batch Add Users to Groups';
$string['IMPORT_MENU_LONG']         = 'Batch Add Users to Groups';
$string['IMPORT_MENU_SHORT']        = 'Import';
$string['LBL_IMPORT_TITLE']         = 'Import Group List file';
$string['LBL_IMPORT']               = 'Import';
$string['LBL_IDENTITY_OPTIONS']     = 'User Identity';
$string['LBL_GROUP_OPTIONS']        = 'Group Options';
$string['LBL_FILE_OPTIONS']         = 'Import File';
$string['LBL_FILE_OPTIONS_help']    = 'Upload or pick from a repository a delimited data file with group allocation of enrolled users. File should have either a .txt or .csv extension.';
$string['LBL_USER_ID_FIELD']        = 'User field:';
$string['LBL_USER_ID_FIELD_help']   = 'Specify which field in the user record is represented in the first column of the import file.';
$string['LBL_GROUP_help']           = 'Make groups assignments, either based on file input, or a selected group.';
$string['LBL_GROUP_ID']             = 'Use group:';
$string['LBL_GROUP_ID_help']        = 'Choose to use the group name in input file, if supplied, or select an existing group and ignore the input data.';
$string['LBL_GROUP_CREATE']         = 'Create groups:';
$string['LBL_GROUP_CREATE_help']    = 'If groups in import file do not exist, create new ones as needed, otherwise only assign users to groups if the group name specified already exists.';
$string['LBL_NO_GROUP_ID']          = 'Use file data';
$string['VAL_NO_FILES']             = 'No file was selected for import';
$string['VAL_INVALID_SELECTION']    = 'Invalid selection';
$string['VAL_INVALID_FORM_DATA']    = 'Invalid form data submission.';
$string['INF_METACOURSE_WARN']      = '<b>WARNING</b>: You can not import enrolments directly into a metacourse. Instead, make enrolments into one of its child courses.<br /><br />';
$string['INF_IMPORT_SUCCESS']       = 'Import complete';

/*$string['ERR_NO_MANUAL_ENROL']      = "Course must have Manual enrol plugin enabled.";*/
$string['ERR_NO_META_ENROL']        = "Course must have 'Course meta link' enrol plugin enabled.";
$string['ERR_PATTERN_MATCH']        = "Line %u: Unable to parse the line contents '%s'\n";
$string['ERR_INVALID_GROUP_ID']     = "The group id %u is invalid for this course.\n";
$string['ERR_USERID_INVALID']       = "Line %u: Unrecognised user '%s'\n";
$string['ERR_ENROLL_FAILED']        = "Line %u: Unable to create role assignment for userid '%s'\n";
$string['ERR_NOT_ENROLLED_FAILED']  = "Line %u: User '%s' not enrolled \n";
$string['ERR_ENROLL_META']          = "Line %u: No existing enrolment in metacourse for userid '%s'\n";
$string['ERR_CREATE_GROUP']         = "Line %u: Unable to create group '%s'\n";
$string['ERR_GROUP_MEMBER']         = "Line %u: Unable to add user '%s' to group '%s'\n";
$string['ERR_USER_MULTIPLE_RECS']   = "Line %u: User ID value '%s' not unique. Multiple records found\n";
$string['HELP_PAGE_IMPORT']         = 'Batch Allocate users to Groups';
$string['HELP_PAGE_IMPORT_help']    = '
<p>
Allocate users to groups from a delimited text
file. Each of the
users listed in the input file must already have an account set up in the site, and they must already be enrolled onto the target course.<br />
<br />
If a group name is include with any user record (line) then that user will be
added to that group if it exists. You can optionally create new groups if needed.
</p>

<ul>
<li>Each line of the import file represents a single record</li>
<li>Each record should at least contain one field with a userid value, whether it be a username, an e-mail address, or an internal idnumber.</li>
<li>Each record may contain an additional group name field, separated by a comma, semi-colon, or tab character.</li>
<li>Any, or none, of the fields can be quoted, and the group name field will need to be if it contains a semi-colon or comma</li>
<li>Blank lines in the import file will be skipped</li>
<li>Note: If listed users are already in the allocated group, no chamges will be made and no errors reported .</li>
</ul>

<p>
A note about metacourses: this plugin will not import user enrolments into a
metacourse, as the enrolment should be made in one of the child courses. It
will, however, make group assignments, and create groups if needed, when the
userid specified is already associated with the metacourse via a child course
enrolment.
</p>

<h3>Example CSV file format</h3>


<pre>
astudent001,Group_A
astudent002,Group_A
astudent003,Group_B
astudent004,Group_B
</pre>';

/*
* GDPR compliant
*/
$string['privacy:no_data_reason'] = "The Batch Add Users to Groups plugin doesn't store any personal data.";
