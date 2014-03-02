<?php

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->dirroot.'/lib/formslib.php');
require_once('./editlib.php');

class unit_edit_form extends moodleform {
    function definition() {
        unit_edit_shared_definition($this->_form, $this->_id);
        $this->add_action_buttons(false, get_string('updatemyprofile'));
    }

    function unit_edit_form($unit_id) {
        parent::__construct();
        $this->_id = $unit_id;
    }

    var $_id;
};

?>
