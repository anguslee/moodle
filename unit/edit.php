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
 * Allows you to edit a student profile
 *
 * @copyright 
 * @package user
 */

require_once('../config.php');
require_once($CFG->libdir.'/gdlib.php');
require_once($CFG->dirroot.'/unit/edit_form.php');
require_once($CFG->dirroot.'/unit/lib.php');

/* echo $CFG->dirroot.'/unit/edit_form.php'; */

/* error_reporting(E_ALL);  */
/* ini_set("display_errors", 1);  */

$course = optional_param('course', SITEID, PARAM_INT);   // course id (defaults to Site)
$cancelemailchange = optional_param('cancelemailchange', 0, PARAM_INT);   // course id (defaults to Site)


//HTTPS is required in this page when $CFG->loginhttps enabled
$PAGE->https_required();

$unitid = optional_param('id', $USER->unit_id, PARAM_INT);  // unit id
if (!$course = $DB->get_record('course', array('id'=>$course))) {
    print_error('invalidcourseid');
}

if ($course->id != SITEID) {
    require_login($course);
} else if (!isloggedin()) {
    if (empty($SESSION->wantsurl)) {
        $SESSION->wantsurl = $CFG->httpswwwroot.'/user/edit.php';
    }
    redirect(get_login_url());
} else {
    $PAGE->set_context(context_system::instance());
}


if (!$user = $DB->get_record('user', array('id'=>$USER->id))) {
    print_error('invaliduserid');
}

$PAGE->set_url('/unit/edit.php', array('id'=>$unitid));

if (!isloggedin()) {
    if (empty($SESSION->wantsurl)) {
        $SESSION->wantsurl = $CFG->httpswwwroot.'/unit/edit.php';
    }
    redirect(get_login_url());
} else {
    $PAGE->set_context(context_system::instance());
}

// Guest can not edit
if (isguestuser()) {
    print_error('guestnoeditprofile');
}


// The unit profile we are editing
if (!$unit = $DB->get_record('unit', array('id'=>$unitid))) {
    print_error('invalid unit id');
}


// load the appropriate auth plugin
$userauth = get_auth_plugin($user->auth);

if (!$userauth->can_edit_profile()) {
    print_error('noprofileedit', 'auth');
}

if ($editurl = $userauth->edit_profile_url()) {
    // this internal script not used
    redirect($editurl);
}


if ($course->id == SITEID) {
    $coursecontext = context_system::instance();   // SYSTEM context
} else {
    $coursecontext = context_course::instance($course->id);   // Course context
}
$systemcontext   = context_system::instance();
$personalcontext = context_user::instance($user->id);

$PAGE->set_pagelayout('admin');
$PAGE->set_context($personalcontext);

if ($node = $PAGE->navigation->find('myprofile', navigation_node::TYPE_ROOTNODE)) {
    $node->force_open();
}

$unitform = new unit_edit_form($unitid);
$unitform->set_data($unit); 

if ($unitnew = $unitform->get_data()) {
    // save and edit
    // print_r($unitnew);
    unit_update_unit($unitnew);
    redirect("$CFG->wwwroot/unit/unit_profile.php?id=$unit->id");
}


$PAGE->verify_https_required();

echo $OUTPUT->header();
echo $OUTPUT->heading('修改班级: ' . $unit->name);

$unitform->display();
echo $OUTPUT->footer();
