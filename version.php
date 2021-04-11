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
 * @copyright   (c) Peter Beare
 * @license     GNU General Public License version 3
 * @package     local_batchgroup
 * 
 * Adapted from local_userenrols by Fred Woolard
 */

defined('MOODLE_INTERNAL') || die();


$plugin             = new stdClass();

<<<<<<< HEAD
$plugin->version    = 2021040502; 
$plugin->requires   = 2017111300;
$plugin->release    = 2021040502;
$plugin->component  = 'local_batchgroup';
$plugin->cron       = 0;
$plugin->maturity   = MATURITY_ALPHA;
=======
    $plugin->version    = 2021040501; 
    $plugin->requires   = 2017111300;
    $plugin->release    = 2021040501;
    $plugin->component  = 'local_batchgroup';
    $plugin->cron       = 0;
    $plugin->maturity   = MATURITY_ALPHA;
>>>>>>> parent of a2c1f74... Error mesages working
