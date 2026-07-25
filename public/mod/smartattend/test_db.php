<?php
define('CLI_SCRIPT', true);
require('/home/lms_medi_wiki/public_html/config.php');
global $DB;
$records = $DB->get_records('smartattend_sessions', null, 'starttime DESC', 'id, name, starttime', 0, 20);
foreach($records as $rec) {
    echo $rec->name . "\n";
}
