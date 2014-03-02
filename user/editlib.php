<?php

function cancel_email_update($userid) {
    unset_user_preference('newemail', $userid);
    unset_user_preference('newemailkey', $userid);
    unset_user_preference('newemailattemptsleft', $userid);
}

function useredit_load_preferences(&$user, $reload=true) {
    global $USER;

    if (!empty($user->id)) {
        if ($reload and $USER->id == $user->id) {
            // reload preferences in case it was changed in other session
            unset($USER->preference);
        }

        if ($preferences = get_user_preferences(null, null, $user->id)) {
            foreach($preferences as $name=>$value) {
                $user->{'preference_'.$name} = $value;
            }
        }
    }
}

function useredit_update_user_preference($usernew) {
    $ua = (array)$usernew;
    foreach($ua as $key=>$value) {
        if (strpos($key, 'preference_') === 0) {
            $name = substr($key, strlen('preference_'));
            set_user_preference($name, $value, $usernew->id);
        }
    }
}

/**
 * Updates the provided users profile picture based upon the expected fields
 * returned from the edit or edit_advanced forms.
 *
 * @global moodle_database $DB
 * @param stdClass $usernew An object that contains some information about the user being updated
 * @param moodleform $userform The form that was submitted to edit the form
 * @return bool True if the user was updated, false if it stayed the same.
 */
function useredit_update_picture(stdClass $usernew, moodleform $userform, $filemanageroptions = array()) {
    global $CFG, $DB;
    require_once("$CFG->libdir/gdlib.php");

    $context = context_user::instance($usernew->id, MUST_EXIST);
    $user = $DB->get_record('user', array('id'=>$usernew->id), 'id, picture', MUST_EXIST);

    $newpicture = $user->picture;
    // Get file_storage to process files.
    $fs = get_file_storage();
    if (!empty($usernew->deletepicture)) {
        // The user has chosen to delete the selected users picture
        $fs->delete_area_files($context->id, 'user', 'icon'); // drop all images in area
        $newpicture = 0;

    } else {
        // Save newly uploaded file, this will avoid context mismatch for newly created users.
        file_save_draft_area_files($usernew->imagefile, $context->id, 'user', 'newicon', 0, $filemanageroptions);
        if (($iconfiles = $fs->get_area_files($context->id, 'user', 'newicon')) && count($iconfiles) == 2) {
            // Get file which was uploaded in draft area
            foreach ($iconfiles as $file) {
                if (!$file->is_directory()) {
                    break;
                }
            }
            // Copy file to temporary location and the send it for processing icon
            if ($iconfile = $file->copy_content_to_temp()) {
                // There is a new image that has been uploaded
                // Process the new image and set the user to make use of it.
                // NOTE: Uploaded images always take over Gravatar
                $newpicture = (int)process_new_icon($context, 'user', 'icon', 0, $iconfile);
                // Delete temporary file
                @unlink($iconfile);
                // Remove uploaded file.
                $fs->delete_area_files($context->id, 'user', 'newicon');
            } else {
                // Something went wrong while creating temp file.
                // Remove uploaded file.
                $fs->delete_area_files($context->id, 'user', 'newicon');
                return false;
            }
        }
    }

    if ($newpicture != $user->picture) {
        $DB->set_field('user', 'picture', $newpicture, array('id' => $user->id));
        return true;
    } else {
        return false;
    }
}

function useredit_update_bounces($user, $usernew) {
    if (!isset($usernew->email)) {
        //locked field
        return;
    }
    if (!isset($user->email) || $user->email !== $usernew->email) {
        set_bounce_count($usernew,true);
        set_send_count($usernew,true);
    }
}

function useredit_update_trackforums($user, $usernew) {
    global $CFG;
    if (!isset($usernew->trackforums)) {
        //locked field
        return;
    }
    if ((!isset($user->trackforums) || ($usernew->trackforums != $user->trackforums)) and !$usernew->trackforums) {
        require_once($CFG->dirroot.'/mod/forum/lib.php');
        forum_tp_delete_read_records($usernew->id);
    }
}

function useredit_update_interests($user, $interests) {
    tag_set('user', $user->id, $interests);
}

function useredit_shared_definition(&$mform, $editoroptions = null, $filemanageroptions = null) {
    global $CFG, $USER, $DB;

    $user = $DB->get_record('user', array('id' => $USER->id));
    useredit_load_preferences($user, false);

    $strrequired = get_string('required');

    // Add the necessary names.
    /* foreach (useredit_get_required_name_fields() as $fullname) { */
    /*     $mform->addElement('text', $fullname,  get_string($fullname),  'maxlength="100" size="30"'); */
    /*     $mform->addRule($fullname, $strrequired, 'required', null, 'client'); */
    /*     $mform->setType($fullname, PARAM_NOTAGS); */
    /* } */

    $mform->addElement('text', 'lastname',  '姓氏',  'maxlength="100" size="30"');
    $mform->addRule('lastname', $strrequired, 'required', null, 'client');
    $mform->setType('lastname', PARAM_NOTAGS);

    $mform->addElement('text', 'firstname',  '名字',  'maxlength="100" size="30"');
    $mform->addRule('firstname', $strrequired, 'required', null, 'client');
    $mform->setType('firstname', PARAM_NOTAGS);

    $mform->addElement('text', 'entry_year', '入学年份', 'maxlength="20" size="25"');
    $mform->addRule('entry_year', $strrequired, 'required', null, 'client');
    $mform->setType('entry_year', PARAM_INT);

    $mform->addElement('text', 'unit_id', '所属班级', 'maxlength="255" size="25"');
    $mform->addRule('unit_id', $strrequired, 'required', null, 'client');
    $mform->setType('unit_id', PARAM_TEXT);


    /* $enabledusernamefields = useredit_get_enabled_name_fields(); */
    /* // Add the enabled additional name fields. */
    /* foreach ($enabledusernamefields as $addname) { */
    /*     $mform->addElement('text', $addname,  get_string($addname), 'maxlength="100" size="30"'); */
    /*     $mform->setType($addname, PARAM_NOTAGS); */
    /* } */

    // Do not show email field if change confirmation is pending
    if (!empty($CFG->emailchangeconfirmation) and !empty($user->preference_newemail)) {
        $notice = get_string('emailchangepending', 'auth', $user);
        $notice .= '<br /><a href="edit.php?cancelemailchange=1&amp;id='.$user->id.'">'
                . get_string('emailchangecancel', 'auth') . '</a>';
        $mform->addElement('static', 'emailpending', get_string('email'), $notice);
    } else {
        $mform->addElement('text', 'email', 'Email', 'maxlength="100" size="30"');
        $mform->setType('email', PARAM_EMAIL);
    }

    /* $choices = array(); */
    /* $choices['0'] = get_string('emaildisplayno'); */
    /* $choices['1'] = get_string('emaildisplayyes'); */
    /* $choices['2'] = get_string('emaildisplaycourse'); */
    /* $mform->addElement('select', 'maildisplay', get_string('emaildisplay'), $choices); */
    /* $mform->setDefault('maildisplay', 2); */

    /* $choices = array(); */
    /* $choices['0'] = get_string('textformat'); */
    /* $choices['1'] = get_string('htmlformat'); */
    /* $mform->addElement('select', 'mailformat', get_string('emailformat'), $choices); */
    /* $mform->setDefault('mailformat', 1); */

    /* if (!empty($CFG->allowusermailcharset)) { */
    /*     $choices = array(); */
    /*     $charsets = get_list_of_charsets(); */
    /*     if (!empty($CFG->sitemailcharset)) { */
    /*         $choices['0'] = get_string('site').' ('.$CFG->sitemailcharset.')'; */
    /*     } else { */
    /*         $choices['0'] = get_string('site').' (UTF-8)'; */
    /*     } */
    /*     $choices = array_merge($choices, $charsets); */
    /*     $mform->addElement('select', 'preference_mailcharset', get_string('emailcharset'), $choices); */
    /* } */

    /* $choices = array(); */
    /* $choices['0'] = get_string('emaildigestoff'); */
    /* $choices['1'] = get_string('emaildigestcomplete'); */
    /* $choices['2'] = get_string('emaildigestsubjects'); */
    /* $mform->addElement('select', 'maildigest', get_string('emaildigest'), $choices); */
    /* $mform->setDefault('maildigest', 0); */
    /* $mform->addHelpButton('maildigest', 'emaildigest'); */

    /* $choices = array(); */
    /* $choices['1'] = get_string('autosubscribeyes'); */
    /* $choices['0'] = get_string('autosubscribeno'); */
    /* $mform->addElement('select', 'autosubscribe', get_string('autosubscribe'), $choices); */
    /* $mform->setDefault('autosubscribe', 1); */

    /* if (!empty($CFG->forum_trackreadposts)) { */
    /*     $choices = array(); */
    /*     $choices['0'] = get_string('trackforumsno'); */
    /*     $choices['1'] = get_string('trackforumsyes'); */
    /*     $mform->addElement('select', 'trackforums', get_string('trackforums'), $choices); */
    /*     $mform->setDefault('trackforums', 0); */
    /* } */

    /* $editors = editors_get_enabled(); */
    /* if (count($editors) > 1) { */
    /*     $choices = array('' => get_string('defaulteditor')); */
    /*     $firsteditor = ''; */
    /*     foreach (array_keys($editors) as $editor) { */
    /*         if (!$firsteditor) { */
    /*             $firsteditor = $editor; */
    /*         } */
    /*         $choices[$editor] = get_string('pluginname', 'editor_' . $editor); */
    /*     } */
    /*     $mform->addElement('select', 'preference_htmleditor', get_string('textediting'), $choices); */
    /*     $mform->setDefault('preference_htmleditor', ''); */
    /* } else { */
    /*     // Empty string means use the first chosen text editor. */
    /*     $mform->addElement('hidden', 'preference_htmleditor'); */
    /*     $mform->setDefault('preference_htmleditor', ''); */
    /*     $mform->setType('preference_htmleditor', PARAM_PLUGIN); */
    /* } */

    $mform->addElement('text', 'url', '个人网站', 'maxlength="255" size="50"');
    $mform->setType('url', PARAM_URL);

    $mform->addElement('select', 'gender', '性别', array(htmlspecialchars('男') => '男', htmlspecialchars('女') => '女'));
    $mform->setDefault('gender', $user->gender);

    $mform->addElement('text', 'birthdate', '出生日期', 'maxlength="50" size="25"');
    $mform->setType('birthdate', PARAM_NOTAGS);

    $mform->addElement('text', 'cellphone', '手机号码', 'maxlength="50" size="25"');
    $mform->setType('cellphone', PARAM_NOTAGS);

    $mform->addElement('text', 'weixin', '微信', 'maxlength="50" size="25"');
    $mform->setType('weixin', PARAM_NOTAGS);

    $mform->addElement('text', 'qq', 'QQ', 'maxlength="50" size="25"');
    $mform->setType('qq', PARAM_NOTAGS);

    $mform->addElement('text', 'street_address', '住址', 'maxlength="50" size="25"');
    $mform->setType('street_address', PARAM_NOTAGS);

    $mform->addElement('text', 'district', '区县', 'maxlength="50" size="25"');
    $mform->setType('district', PARAM_NOTAGS);

    $mform->addElement('text', 'city', '城市', 'maxlength="50" size="20"');
    $mform->setType('city', PARAM_TEXT);
    if (!empty($CFG->defaultcity)) {
        $mform->setDefault('city', $CFG->defaultcity);
    }

    $mform->addElement('text', 'province', '省份', 'maxlength="50" size="20"');
    $mform->setType('province', PARAM_NOTAGS);

    $mform->addElement('text', 'postal_code', '邮编', 'maxlength="50" size="25"');
    $mform->setType('postal_code', PARAM_NOTAGS);

    $student_relations = array(htmlspecialchars('父亲') => '父亲', htmlspecialchars('母亲') => '母亲',
                               htmlspecialchars('继父') => '继父', htmlspecialchars('继母') => '继母',
                               htmlspecialchars('祖父') => '祖父', htmlspecialchars('祖母') => '祖母',
                               htmlspecialchars('外祖父') => '外祖父', htmlspecialchars('外祖母') => '外祖母',
                               htmlspecialchars('其他') => '其他关系');

    $mform->addElement('text', 'parent1_name', '家长姓名-1', 'maxlength="50" size="25"');
    $mform->setType('parent1_name', PARAM_TEXT);

    $mform->addElement('text', 'parent1_cellphone', '家长联系电话-1', 'maxlength="50" size="25"');
    $mform->setType('parent1_cellphone', PARAM_TEXT);
    
    $mform->addElement('text', 'parent1_employer', '家长工作单位-1', 'maxlength="255" size="25"');
    $mform->setType('parent1_employer', PARAM_TEXT);
    
    $mform->addElement('text', 'parent1_email', '家长Email-1', 'maxlength="255" size="25"');
    $mform->setType('parent1_email', PARAM_EMAIL);

    $mform->addElement('select', 'parent1_type', '与学生关系-1', $student_relations);
    $mform->setDefault('parent1_type', $user->parent1_type);

    $mform->addElement('text', 'parent2_name', '家长姓名-2', 'maxlength="50" size="25"');
    $mform->setType('parent2_name', PARAM_TEXT);

    $mform->addElement('text', 'parent2_cellphone', '家长联系电话-2', 'maxlength="50" size="25"');
    $mform->setType('parent2_cellphone', PARAM_TEXT);
    
    $mform->addElement('text', 'parent2_employer', '家长工作单位-2', 'maxlength="255" size="25"');
    $mform->setType('parent2_employer', PARAM_TEXT);
    
    $mform->addElement('text', 'parent2_email', '家长Email-2', 'maxlength="255" size="25"');
    $mform->setType('parent2_email', PARAM_EMAIL);

    $mform->addElement('select', 'parent2_type', '与学生关系-2', $student_relations);
    $mform->setDefault('parent2_type', $user->parent2_type);

    $mform->addElement('text', 'student_id', '学号', 'maxlength="255" size="25"');
    $mform->setType('student_id', PARAM_TEXT);

    $mform->addElement('text', 'ssn_id', '证件号', 'maxlength="255" size="25"');
    $mform->setType('ssn_id', PARAM_TEXT);

    $mform->addElement('text', 'hukou', '户口', 'maxlength="255" size="25"');
    $mform->setType('hukou', PARAM_TEXT);

    // Multi-Calendar Support - see MDL-18375.
    /* $calendartypes = \core_calendar\type_factory::get_list_of_calendar_types(); */
    /* // We do not want to show this option unless there is more than one calendar type to display. */
    /* if (count($calendartypes) > 1) { */
    /*     $mform->addElement('select', 'calendartype', get_string('preferredcalendar', 'calendar'), $calendartypes); */
    /* } */

    /* if (!empty($CFG->allowuserthemes)) { */
    /*     $choices = array(); */
    /*     $choices[''] = get_string('default'); */
    /*     $themes = get_list_of_themes(); */
    /*     foreach ($themes as $key=>$theme) { */
    /*         if (empty($theme->hidefromselector)) { */
    /*             $choices[$key] = get_string('pluginname', 'theme_'.$theme->name); */
    /*         } */
    /*     } */
    /*     $mform->addElement('select', 'theme', get_string('preferredtheme'), $choices); */
    /* } */

    $mform->addElement('editor', 'description_editor', get_string('userdescription'), null, $editoroptions);
    $mform->setType('description_editor', PARAM_CLEANHTML);
    $mform->addHelpButton('description_editor', 'userdescription');

    $mform->addElement('hidden', 'lang', get_string('preferredlanguage'), get_string_manager()->get_list_of_translations());
    $mform->setDefault('lang', $CFG->lang);

    if (empty($USER->newadminuser)) {
        /* if (!empty($CFG->enablegravatar)) { */
        /*     $mform->addElement('html', html_writer::tag('p', get_string('gravatarenabled'))); */
        /* } */

        $mform->addElement('hidden', 'currentpicture', get_string('currentpicture'));

        /* $mform->addElement('checkbox', 'deletepicture', get_string('delete')); */
        /* $mform->setDefault('deletepicture', 0); */

        /* $mform->addElement('filemanager', 'imagefile', get_string('newpicture'), '', $filemanageroptions); */
        /* $mform->addHelpButton('imagefile', 'newpicture'); */

        /* $mform->addElement('text', 'imagealt', get_string('imagealt'), 'maxlength="100" size="30"'); */
        /* $mform->setType('imagealt', PARAM_TEXT); */

    }

    // Display user name fields that are not currenlty enabled here if there are any.
    /* $disabledusernamefields = useredit_get_disabled_name_fields($enabledusernamefields); */
    /* if (count($disabledusernamefields) > 0) { */
    /*     $mform->addElement('header', 'moodle_additional_names', get_string('additionalnames')); */
    /*     foreach ($disabledusernamefields as $allname) { */
    /*         $mform->addElement('text', $allname, get_string($allname), 'maxlength="100" size="30"'); */
    /*         $mform->setType($allname, PARAM_NOTAGS); */
    /*     } */
    /* } */

    /* if (!empty($CFG->usetags) and empty($USER->newadminuser)) { */
    /*     $mform->addElement('header', 'moodle_interests', get_string('interests')); */
    /*     $mform->addElement('tags', 'interests', get_string('interestslist'), array('display' => 'noofficial')); */
    /*     $mform->addHelpButton('interests', 'interestslist'); */
    /* } */

    /// Moodle optional fields
    /* $mform->addElement('header', 'moodle_optional', get_string('optional', 'form')); */
}

/**
 * Return required user name fields for forms.
 *
 * @return array required user name fields in order according to settings.
 */
function useredit_get_required_name_fields() {
    global $CFG;

    // Get the name display format.
    $nameformat = $CFG->fullnamedisplay;

    // Names that are required fields on user forms.
    $necessarynames = array('firstname', 'lastname');
    $languageformat = get_string('fullnamedisplay');

    // Check that the language string and the $nameformat contain the necessary names.
    foreach ($necessarynames as $necessaryname) {
        $pattern = "/$necessaryname\b/";
        if (!preg_match($pattern, $languageformat)) {
            // If the language string has been altered then fall back on the below order.
            $languageformat = 'firstname lastname';
        }
        if (!preg_match($pattern, $nameformat)) {
            // If the nameformat doesn't contain the necessary name fields then use the languageformat.
            $nameformat = $languageformat;
        }
    }

    // Order all of the name fields in the postion they are written in the fullnamedisplay setting.
    $necessarynames = order_in_string($necessarynames, $nameformat);
    return $necessarynames;
}

/**
 * Gets enabled (from fullnameformate setting) user name fields in appropriate order.
 *
 * @return array Enabled user name fields.
 */
function useredit_get_enabled_name_fields() {
    global $CFG;

    // Get all of the other name fields which are not ranked as necessary.
    $additionalusernamefields = array_diff(get_all_user_name_fields(), array('firstname', 'lastname'));
    // Find out which additional name fields are actually being used from the fullnamedisplay setting.
    $enabledadditionalusernames = array();
    foreach ($additionalusernamefields as $enabledname) {
        if (strpos($CFG->fullnamedisplay, $enabledname) !== false) {
            $enabledadditionalusernames[] = $enabledname;
        }
    }

    // Order all of the name fields in the postion they are written in the fullnamedisplay setting.
    $enabledadditionalusernames = order_in_string($enabledadditionalusernames, $CFG->fullnamedisplay);
    return $enabledadditionalusernames;
}

/**
 * Gets user name fields not enabled from the setting fullnamedisplay.
 *
 * @param array $enabledadditionalusernames Current enabled additional user name fields.
 * @return array Disabled user name fields.
 */
function useredit_get_disabled_name_fields($enabledadditionalusernames = null) {
    // If we don't have enabled additional user name information then go and fetch it (try to avoid).
    if (!isset($enabledadditionalusernames)) {
        $enabledadditionalusernames = useredit_get_enabled_name_fields();
    }

    // These are the additional fields that are not currently enabled.
    $nonusednamefields = array_diff(get_all_user_name_fields(),
            array_merge(array('firstname', 'lastname'), $enabledadditionalusernames));
    return $nonusednamefields;
}
