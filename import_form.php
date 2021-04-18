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
 * batchgroup
 *
 * This plugin will import group assignments
 * from a delimited text file. It does not create new user accounts
 * in Moodle, or enrol users in a course.
 *
 * @author      Peter Beare
 * @copyright   (c) Peter Beare
 * @license     GNU General Public License version 3
 * @package     local_batchgroup
 *
 *Adapted from local_userenrols by Fred Woolard
 */

require_once($CFG->libdir.'/formslib.php');



/**
 *Form definition for the plugin
 *
 */
class local_batchgroup_index_form extends moodleform {

    /**
     *Define the form's contents
     * @see moodleform::definition()
     */
    public function definition()
    {

        // Want to know if there are any meta enrol plugin
        // instances in this course.

        $metacourse = $this->_customdata['data']->metacourse;
        $this->_form->addElement('hidden', local_batchgroup_plugin::FORMID_METACOURSE, $metacourse ? '1' : '0');
        $this->_form->setType(local_batchgroup_plugin::FORMID_METACOURSE, PARAM_INT);

        if ($metacourse) {
            $this->_form->addElement('warning', null, null, get_string('INF_METACOURSE_WARN', local_batchgroup_plugin::PLUGIN_NAME));
        }


        $this->_form->addElement('header', 'identity', get_string('LBL_IDENTITY_OPTIONS', local_batchgroup_plugin::PLUGIN_NAME));

        // The userid field name drop down list
        $this->_form->addElement('select', local_batchgroup_plugin::FORMID_USER_ID_FIELD, get_string('LBL_USER_ID_FIELD', local_batchgroup_plugin::PLUGIN_NAME), $this->_customdata['data']->user_id_field_options);
        $this->_form->setDefault(local_batchgroup_plugin::FORMID_USER_ID_FIELD, local_batchgroup_plugin::DEFAULT_USER_ID_FIELD);
        $this->_form->addHelpButton(local_batchgroup_plugin::FORMID_USER_ID_FIELD, 'LBL_USER_ID_FIELD', local_batchgroup_plugin::PLUGIN_NAME);

        // Conditionally based on user capability
        if ($this->_customdata['data']->canmanagegroups) {

            // Process groups
            $this->_form->addElement('header', 'identity', get_string('LBL_GROUP_OPTIONS', local_batchgroup_plugin::PLUGIN_NAME)); //section header

            // Group id selection
            $groups = array(0 => get_string('LBL_NO_GROUP_ID', local_batchgroup_plugin::PLUGIN_NAME));
            foreach (groups_get_all_groups($this->_customdata['data']->course->id) as $key => $grouprecord) {
                $groups[$key] = $grouprecord->name;
            }
            
            
            $this->_form->addElement('select', local_batchgroup_plugin::FORMID_GROUP_ID, get_string('LBL_GROUP_ID', local_batchgroup_plugin::PLUGIN_NAME), $groups);
            $this->_form->setDefault(local_batchgroup_plugin::FORMID_GROUP_ID, 0);
            $this->_form->addHelpButton(local_batchgroup_plugin::FORMID_GROUP_ID, 'LBL_GROUP_ID', local_batchgroup_plugin::PLUGIN_NAME);
            //$this->_form->disabledIf(local_batchgroup_plugin::FORMID_GROUP_ID, local_batchgroup_plugin::FORMID_GROUP, 'eq', '0');

            // Create new if needed
            $this->_form->addElement('selectyesno', local_batchgroup_plugin::FORMID_GROUP_CREATE, get_string('LBL_GROUP_CREATE', local_batchgroup_plugin::PLUGIN_NAME));
            $this->_form->setDefault(local_batchgroup_plugin::FORMID_GROUP_CREATE, 0);
            $this->_form->addHelpButton(local_batchgroup_plugin::FORMID_GROUP_CREATE, 'LBL_GROUP_CREATE', local_batchgroup_plugin::PLUGIN_NAME);
            //$this->_form->disabledIf(local_batchgroup_plugin::FORMID_GROUP_CREATE, local_batchgroup_plugin::FORMID_GROUP,    'eq', '0');
            $this->_form->disabledIf(local_batchgroup_plugin::FORMID_GROUP_CREATE, local_batchgroup_plugin::FORMID_GROUP_ID, 'gt', '0');

        }

        // File picker
        $this->_form->addElement('header', 'identity', get_string('LBL_FILE_OPTIONS', local_batchgroup_plugin::PLUGIN_NAME));

        $this->_form->addElement('filepicker', local_batchgroup_plugin::FORMID_FILES, null, null, $this->_customdata['options']);
        $this->_form->addHelpButton(local_batchgroup_plugin::FORMID_FILES, 'LBL_FILE_OPTIONS', local_batchgroup_plugin::PLUGIN_NAME);
        $this->_form->addRule(local_batchgroup_plugin::FORMID_FILES, null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string('LBL_IMPORT', local_batchgroup_plugin::PLUGIN_NAME));

    } // definition



    /**
     *Validate the submitted form data
     * @see moodleform::validation()
     */
    public function validation($data, $files)
    {
        global $USER;



        $result = array();

        // User record field to match against, has to be
        // one of three defined in the plugin's class
        if (   empty($data[local_batchgroup_plugin::FORMID_USER_ID_FIELD])
            || !array_key_exists($data[local_batchgroup_plugin::FORMID_USER_ID_FIELD], local_batchgroup_plugin::get_user_id_field_options())) {
            $result[local_batchgroup_plugin::FORMID_USER_ID_FIELD] = get_string('invaliduserfield', 'error', $data[local_batchgroup_plugin::FORMID_USER_ID_FIELD]);
        }
        
        $groupid = intval($data[local_batchgroup_plugin::FORMID_GROUP_ID]); //replaced line
        if ($groupid > 0 && !array_key_exists($groupid, groups_get_all_groups($this->_customdata['data']->course->id))) {
            $groupid = 0;
            $result[local_batchgroup_plugin::FORMID_GROUP_ID] = get_string('VAL_INVALID_SELECTION', local_batchgroup_plugin::PLUGIN_NAME);
        }
        
        //see if new groups should be created
        if ($groupid == 0){    
            $groupcreate = empty($data[local_batchgroup_plugin::FORMID_GROUP_CREATE])
                          ? 0 : $data[local_batchgroup_plugin::FORMID_GROUP_CREATE];
        } else { //or not
            $groupcreate = 0;
        }
        if ($groupcreate < 0 or $groupcreate > 1) {
            $result[local_batchgroup_plugin::FORMID_GROUPING] = get_string('VAL_INVALID_SELECTION', local_batchgroup_plugin::PLUGIN_NAME);
        }

        // File is not in the $files var, rather the itemid is in
        // $data, but we can get to it through file api. At this
        // stage, the file should be in the user's draft area
        $areafiles = get_file_storage()->get_area_files(context_user::instance($USER->id)->id, 'user', 'draft', $data[local_batchgroup_plugin::FORMID_FILES], false, false);
        $import_file = array_shift($areafiles);
        if (null == $import_file) {
            $result[local_batchgroup_plugin::FORMID_FILES] = get_string('VAL_NO_FILES', local_batchgroup_plugin::PLUGIN_NAME);
        }

        return $result;

    } // validation


} // class
