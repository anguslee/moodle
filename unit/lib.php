<?php

function unit_update_unit(&$unit) {
    global $DB;

    $DB->update_record('unit', $unit);
}