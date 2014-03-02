<?php

error_reporting(E_ALL); 
ini_set("display_errors", 1); 


function unit_edit_shared_definition(&$mform, $unit_id) {
    global $CFG, $USER, $DB;

    $unit = $DB->get_record('unit', array('id' => $unit_id));

    $strrequired = get_string('required');

    $mform->addElement('text', 'unit_id',  '班级编号',  'maxlength="100" size="30"');
    $mform->addRule('unit_id', $strrequired, 'required', null, 'client');
    $mform->setType('unit_id', PARAM_NOTAGS);

    $mform->addElement('text', 'name',  '班级名称',  'maxlength="100" size="30"');
    $mform->addRule('name', $strrequired, 'required', null, 'client');
    $mform->setType('name', PARAM_NOTAGS);

    $mform->addElement('select', 'flag', '类别', array(htmlspecialchars('文科') => '文科班',
                                                       htmlspecialchars('理科') => '理科班',
                                                       htmlspecialchars('N/A') => 'N/A'));
    $mform->addRule('flag', $strrequired, 'required', null, 'client');
    
    $mform->addElement('text', 'initial_entry_year', '起始年度', 'maxlength="20" size="25"');
    $mform->addRule('initial_entry_year', $strrequired, 'required', null, 'client');
    $mform->setType('initial_entry_year', PARAM_INT);

    $mform->addElement('text', 'classroom_location', '教室位置', 'maxlength="255" size="25"');
    $mform->setType('classroom_location', PARAM_TEXT);
}