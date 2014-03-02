<?php

require_once(dirname(__FILE__) . '/../config.php');
require_once($CFG->dirroot . '/my/lib.php');
require_once($CFG->dirroot . '/tag/lib.php');
// require_once($CFG->dirroot . '/user/profile/lib.php');

error_reporting(E_ALL);
ini_set("display_errors", 1);

$unitid = optional_param('id', 0, PARAM_INT);
$edit   = optional_param('edit', null, PARAM_BOOL);    // Turn editing on and off

$PAGE->set_url('/unit/unit_profile.php', array('id'=>$unitid));

if (!empty($CFG->forceloginforprofiles)) {
    require_login();
    if (isguestuser()) {
        $SESSION->wantsurl = $PAGE->url->out(false);
        redirect(get_login_url());
    }
} else if (!empty($CFG->forcelogin)) {
    require_login();
}

$userid = $USER->id;       // Owner of the page

if ((!$user = $DB->get_record('user', array('id' => $userid))) || ($user->deleted)) {
    $PAGE->set_context(context_system::instance());
    echo $OUTPUT->header();
    if (!$user) {
        echo $OUTPUT->notification(get_string('invaliduser', 'error'));
    } else {
        echo $OUTPUT->notification(get_string('userdeleted'));
    }
    echo $OUTPUT->footer();
    die;
}

if (!$unit = $DB->get_record('unit', array('id' => $unitid))) {
    $PAGE->set_context(context_system::instance());
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('invalidunit', 'error'));
    echo $OUTPUT->footer();
    die;
}

$currentuser = ($user->id == $USER->id);
$context = $usercontext = context_user::instance($userid, MUST_EXIST);

// Get the profile page.  Should always return something unless the database is broken.
if (!$currentpage = my_get_page($userid, MY_PAGE_PUBLIC)) {
    print_error('mymoodlesetup');
}

// print_r ($currentpage);

if (!$currentpage->userid) {
    $context = context_system::instance();  // A trick so that we even see non-sticky blocks
}

$PAGE->set_context($context);
$PAGE->set_pagelayout('mypublic');
$PAGE->set_pagetype('user-profile');

// Set up block editing capabilities
if (isguestuser()) {     // Guests can never edit their profile
    $USER->editing = $edit = 0;  // Just in case
    $PAGE->set_blocks_editing_capability('moodle/my:configsyspages');  // unlikely :)
} else {
    if ($currentuser) {
        $PAGE->set_blocks_editing_capability('moodle/user:manageownblocks');
    } else {
        $PAGE->set_blocks_editing_capability('moodle/user:manageblocks');
    }
}

if (has_capability('moodle/user:viewhiddendetails', $context)) {
    $hiddenfields = array();
} else {
    $hiddenfields = array_flip(explode(',', $CFG->hiddenuserfields));
}

if (has_capability('moodle/site:viewuseridentity', $context)) {
    $identityfields = array_flip(explode(',', $CFG->showuseridentity));
} else {
    $identityfields = array();
}

/* // Start setting up the page */
$strpublicprofile = get_string('publicprofile');

$PAGE->blocks->add_region('content');
$PAGE->set_subpage($currentpage->id);
/* $PAGE->set_title(fullname($user).": $strpublicprofile"); */
/* $PAGE->set_heading(fullname($user).": $strpublicprofile"); */

if (!$currentuser) {
    $PAGE->navigation->extend_for_user($user);
    if ($node = $PAGE->settingsnav->get('userviewingsettings'.$user->id)) {
        $node->forceopen = true;
    }
} else if ($node = $PAGE->settingsnav->get('usercurrentsettings', navigation_node::TYPE_CONTAINER)) {
    $node->forceopen = true;
}
if ($node = $PAGE->settingsnav->get('root')) {
    $node->forceopen = false;
}


// Toggle the editing state and switches
/* if ($PAGE->user_allowed_editing()) { */
/*     if ($edit !== null) {             // Editing state was specified */
/*         $USER->editing = $edit;       // Change editing state */
/*         if (!$currentpage->userid && $edit) { */
/*             // If we are viewing a system page as ordinary user, and the user turns */
/*             // editing on, copy the system pages as new user pages, and get the */
/*             // new page record */
/*             if (!$currentpage = my_copy_page($USER->id, MY_PAGE_PUBLIC, 'user-profile')) { */
/*                 print_error('mymoodlesetup'); */
/*             } */
/*             $PAGE->set_context($usercontext); */
/*             $PAGE->set_subpage($currentpage->id); */
/*         } */
/*     } else {                          // Editing state is in session */
/*         if ($currentpage->userid) {   // It's a page we can edit, so load from session */
/*             if (!empty($USER->editing)) { */
/*                 $edit = 1; */
/*             } else { */
/*                 $edit = 0; */
/*             } */
/*         } else {                      // It's a system page and they are not allowed to edit system pages */
/*             $USER->editing = $edit = 0;          // Disable editing completely, just to be safe */
/*         } */
/*     } */

/*     // Add button for editing page */
/*     $params = array('edit' => !$edit); */

/*     if (!$currentpage->userid) { */
/*         // viewing a system page -- let the user customise it */
/*         $editstring = get_string('updatemymoodleon'); */
/*         $params['edit'] = 1; */
/*     } else if (empty($edit)) { */
/*         $editstring = get_string('updatemymoodleon'); */
/*     } else { */
/*         $editstring = get_string('updatemymoodleoff'); */
/*     } */

/*     $url = new moodle_url("$CFG->wwwroot/unit/unit_profile.php", $params); */
/*     $button = $OUTPUT->single_button($url, $editstring); */
/*     $PAGE->set_button($button); */

/* } else { */
    $USER->editing = $edit = 0;
/* } */

// HACK WARNING!  This loads up all this page's blocks in the system context
if ($currentpage->userid == 0) {
    $CFG->blockmanagerclass = 'my_syspage_block_manager';
}

/* // TODO WORK OUT WHERE THE NAV BAR IS! */

echo $OUTPUT->header();
echo '<div class="userprofile">';


/* // Print the standard content of this page, the basic profile info */

echo $OUTPUT->heading($unit->name);

// Print all the little details in a list
echo html_writer::start_tag('dl', array('class'=>'list'));

if ($unit->unit_id) {
    echo html_writer::tag('dt', '班级编号');
    echo html_writer::tag('dd', $unit->unit_id);
}

if ($unit->name) {
    echo html_writer::tag('dt', '班级名称');
    echo html_writer::tag('dd', $unit->name);
}

if ($unit->flag) {
    echo html_writer::tag('dt', '类别');
    echo html_writer::tag('dd', $unit->flag);
}

if ($unit->initial_entry_year) {
    echo html_writer::tag('dt', '起始年度');
    echo html_writer::tag('dd', $unit->initial_entry_year);
}

if ($unit->classroom_location) {
    echo html_writer::tag('dt', '教室位置');
    echo html_writer::tag('dd', $unit->classroom_location);
}

if ($unit->homeroom_teacher) {
    echo html_writer::tag('dt', '班主任');
    echo html_writer::tag('dd', $unit->homeroom_teacher);
}

echo html_writer::end_tag('dl');
echo "</div></div>"; // Closing desriptionbox and userprofilebox.
echo '<div id="region-content" class="block-region"><div class="region-content">';
echo $OUTPUT->blocks_for_region('content');
echo '</div>';

echo $OUTPUT->footer();
