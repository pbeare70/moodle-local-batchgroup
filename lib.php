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
 * local_batchgroup
 *
 * This plugin will batch allocate users to groups
 * from a delimited text file (CSV·or·TXT).
 *
 * @author      Peter Beare
 * @copyright   (c) Peter Beare
 * @license     GNU General Public License version 3
 * @package     local_batchgroup
 *
 * Adapted from local_userenrols by Fred Woolard
 * local_batchgroup differs in that it does not provide the option to enrol users.
 */

defined('MOODLE_INTERNAL') || die();

require_once("{$CFG->dirroot}/lib/accesslib.php");
require_once("{$CFG->dirroot}/lib/enrollib.php");
require_once("{$CFG->dirroot}/lib/grouplib.php");
require_once("{$CFG->dirroot}/lib/navigationlib.php");
require_once("{$CFG->dirroot}/group/lib.php");

/**
 * Hook to insert a link in settings navigation menu block
 *
 * @param settings_navigation $navigation
 * @param course_context      $context
 * @return void
 */
function local_batchgroup_extend_settings_navigation(settings_navigation $navigation, $context) {
    global $CFG;

    // If not in a course context, then leave.
    if ($context == null || $context->contextlevel != CONTEXT_COURSE) {
        return;
    }

    // When on front page there is 'frontpagesettings' node, other.
    // courses will have 'courseadmin' node.
    if (null == ($courseadminnode = $navigation->get('courseadmin'))) {
        // Keeps us off the front page.
        return;
    }
    if (null == ($useradminnode = $courseadminnode->get('users'))) {
        return;
    }

    // Add our link.
    $useradminnode->add(
        get_string('IMPORT_MENU_LONG', local_batchgroup_plugin::PLUGIN_NAME),
        new moodle_url("{$CFG->wwwroot}/local/batchgroup/import.php", array('id' => $context->instanceid)),
        navigation_node::TYPE_SETTING,
        get_string('IMPORT_MENU_SHORT', local_batchgroup_plugin::PLUGIN_NAME),
        null, new pix_icon('i/import', 'import'));

}

/**
 * The local plugin class
 */
class local_batchgroup_plugin
{

    /*
     * Class constants
     */

    /**
     * const string    Reduce chance of typos.
     */
    const PLUGIN_NAME     = 'local_batchgroup';

    /**
     * const string    Where we put the uploaded files.
     */
    const PLUGIN_FILEAREA             = 'uploads';

    /**
     * const int       Max size of upload file.
     */
    const MAXFILESIZE     = 51200;

    /**
     * const string    Form id for user_id (key field to match).
     */
    const FORMID_USER_ID_FIELD        = 'user_id';

    /**
     * const string    Form id for group_id (direct assignment).
     */
    const FORMID_GROUP_ID             = 'group_id';

    /**
     * const string    Form id for group_create (if specified group missing).
     */
    const FORMID_GROUP_CREATE         = 'group_create';

    /**
     * const string    Form id for filepicker form element.
     */
    const FORMID_FILES    = 'filepicker';

    /**
     * const string    Form id for metacourse (hidden indicator).
     */
    const FORMID_METACOURSE           = 'metacourse';

    /**
     * const string    Default user_id form value (key field to match).
     */
    const DEFAULT_USER_ID_FIELD       = 'username';

    /*
     * Member vars
     */

    /**
     * @var array
     */
    private static $useridfieldoptions = null;

    /*
     * Methods
     */

    /**
     * Return list of valid options for user record field matching
     *
     * @return array
     */
    public static function get_user_id_field_options() {

        if (self::$useridfieldoptions == null) {
            self::$useridfieldoptions = array(
                'username' => get_string('username'),
                'email'    => get_string('email'),
                'idnumber' => get_string('idnumber')
            );
        }

        return self::$useridfieldoptions;

    }

    /**
     * Make a role assignment in the specified course using the specified role
     * id for the user whose id information is passed in the line data.
     *
     * @param stdClass      $course           Course in which to make the role assignment
     * @param stdClass      $enrol_instance   Enrol instance to use for adding users to course
     * @param string        $identfield       How users are identified in the imported data
     * @param int           $role_id          Id of the role to use in the role assignment
     * @param boolean       $groupassign     Whether or not to assign users to groups
     * @param int           $groupid         Id of group to assign to, 0 indicates use group name from import file
     * @param boolean       $groupcreate     Whether or not to create new groups if needed
     * @param stored_file   $importfile      File in local repository from which to get enrollment and group data
     * @return string             String message with results
     *
     * @uses $DB
     */
    public static function import_file(stdClass $course, $identfield, $groupid, $groupcreate, stored_file $importfile) {
        global $DB;

        // Default return value.
        $result = '';

        // Need one of these in the loop.
        $coursecontext = context_course::instance($course->id);

        // Choose the regex pattern based on the $identfield.
        switch($identfield)
        {
            case 'email':
                $regexpattern = '/^"?\s*([a-z0-9][\w.%-]* @[a-z0-9][a-z0-9.-]{0,61}'; // append long string below.
                $regexpattern .= '[a-z0-9]\.[a-z]{2,6})\s*"?(?:\s*[;,\t]\s*"?\s*([a-z0-9][\w\' .,&-\[\]\{\}\(\)]*))?\s*"?$/Ui';
                break;
            case 'idnumber':
                $regexpattern = '/^"?\s*(\d{1,32})\s*"?(?:\s*[;,\t]\s*"?\s*([a-z0-9][\w\' .,&-\[\]\{\}\(\)]*))?\s*"?$/Ui';
                break;
            default:
                $regexpattern = '/^"?\s*([a-z0-9][\w@.-]*)\s*"?(?:\s*[;,\t]\s*"?\s*([a-z0-9][\w\' .,&-\[\]\{\}\(\)]*))?\s*"?$/Ui';
                break;
        }

        // If doing group assignments, want to know the valid.
        // groups for the course.
        $selectedgroup = null;

        if (false === ($existinggroups = groups_get_all_groups($course->id))) {
            $existinggroups = array();
        }

        if ($groupid > 0) {
            if (array_key_exists($groupid, $existinggroups)) {
                $selectedgroup = $existinggroups[$groupid];
            } else {
                // Error condition.
                return sprintf(get_string('ERR_INVALID_GROUP_ID', self::PLUGIN_NAME), $groupid);
            }
        }

        // Iterate the list of active enrol plugins looking for.
        // the meta course plugin.
        $metacourse = false;
        $enrolsenabled = enrol_get_instances($course->id, true);
        foreach ($enrolsenabled as $enrol) {
            if ($enrol->enrol == 'meta') {
                $metacourse = true;
                break;
            }
        }

        // Open and fetch the file contents.
        $fh = $importfile->get_content_file_handle();
        $linenum = 0;
        while (false !== ($line = fgets($fh))) {
            $linenum++;

            // Clean these up for each iteration.
            unset($userrec, $newgroup, $newgrouping);

            if (!($line = trim($line))) {
                continue;
            }

            // Parse the line, from which we may get one or two.
            // matches since the group name is an optional item.
            // on a line by line basis.
            if (!preg_match($regexpattern, $line, $matches)) {
                $result .= sprintf(get_string('ERR_PATTERN_MATCH', self::PLUGIN_NAME), $linenum, $line);
                continue;
            }
            $identvalue    = $matches[1];
            $groupname     = isset($matches[2]) ? $matches[2] : '';

            // User must already exist, we import enrollments.
            // into courses, not users into the system. Exclude.
            // records marked as deleted. Because idnumber is.
            // not enforced unique, possible multiple records.
            // returned when using that identifying field, so.
            // use ->get_records method to make that detection.
            // and inform user.
            $userrecarray = $DB->get_records('user', array($identfield => addslashes($identvalue), 'deleted' => 0));
            // Should have one and only one record, otherwise.
            // report it and move on to the next.
            $userreccount = count($userrecarray);
            if ($userreccount == 0) {
                // No record found.
                $result .= sprintf(get_string('ERR_USERID_INVALID', self::PLUGIN_NAME), $linenum, $identvalue);
                continue;
            } else if ($userreccount > 1) {
                // Too many records.
                $result .= sprintf(get_string('ERR_USER_MULTIPLE_RECS', self::PLUGIN_NAME), $linenum, $identvalue);
                continue;
            }

            $userrec = array_shift($userrecarray);

            // Fetch all the role assignments this user might have for this course's context.
            $roles = get_user_roles($coursecontext, $userrec->id, false);
            // If a user has a role in this course, then we leave it alone and move on.
            // If they have no role, we add an error to the result output.

            if (!$roles) { // If $roles is false.
                $result .= sprintf(get_string('ERR_NOT_ENROLLED_FAILED', self::PLUGIN_NAME), $linenum, $identvalue);
            }

            // If no group assignments, or group is from file, but no.
            // group found, next line.
            if ($groupid == 0 && empty($groupname)) {
                continue;
            }

            // If no group pre-selected, see if group from import already.
            // created for that course.
            $assigngroupid = 0;
            $assigngroupname = '';
            if ($selectedgroup != null) {

                $assigngroupid   = $selectedgroup->id;
                $assigngroupname = $selectedgroup->name;

            } else {

                // Create groups.
                foreach ($existinggroups as $existinggroup) {
                    if ($existinggroup->name != $groupname) {
                        continue;
                    }
                    $assigngroupid   = $existinggroup->id;
                    $assigngroupname = $existinggroup->name;
                    break;
                }

                // No group by that name.
                if ($assigngroupid == 0) {

                    // Can not create one, next line.
                    if (!$groupcreate) {
                        $result .= sprintf(get_string('ERR_CREATE_GROUP2', self::PLUGIN_NAME), $linenum, $groupname);
                        continue;
                    }

                    // Make a new group for this course.
                    $newgroup = new stdClass();
                    $newgroup->name = addslashes($groupname);
                    $newgroup->courseid = $course->id;
                    if (false === ($assigngroupid = groups_create_group($newgroup))) {
                        $result .= sprintf(get_string('ERR_CREATE_GROUP', self::PLUGIN_NAME), $linenum, $groupname);
                        continue;
                    } else {
                        // Add the new group to our list for the benefit of.
                        // the next contestant. Strip the slashes off the.
                        // name since we do a name comparison earlier when.
                        // trying to find the group in our local cache and.
                        // an escaped semi-colon will cause the test to fail..
                        $newgroup->name   =
                        $assigngroupname = stripslashes($newgroup->name);
                        $newgroup->id = $assigngroupid;
                        $existinggroups[] = $newgroup;
                    }

                }

            }

            // Put the user in the group if not aleady in it.
            if (   !groups_is_member($assigngroupid, $userrec->id)
                && !groups_add_member($assigngroupid, $userrec->id)) {
                $result .= sprintf(get_string('ERR_GROUP_MEMBER', self::PLUGIN_NAME), $linenum, $identvalue, $assigngroupname);
                continue;
            }

            // Any other work....

        } // while fgets.

        fclose($fh);

        return (empty($result)) ? get_string('INF_IMPORT_SUCCESS', self::PLUGIN_NAME)
            : $result .= sprintf(get_string('ERR_CONTINUE_MSG', self::PLUGIN_NAME));

    } // import_file.

} // class.
