<?php
/**
 * Admin page for managing degree list.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$id     = optional_param('id', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/studentprofile/admin_degrees.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('managedegrees', 'local_studentprofile'));
$PAGE->set_heading(get_string('managedegrees', 'local_studentprofile'));

if ($action === 'delete' && $id > 0) {
    require_sesskey();
    $DB->delete_records('local_studentprofile_degrees', ['id' => $id]);
    redirect(
        new moodle_url('/local/studentprofile/admin_degrees.php'),
        get_string('degreedeleted', 'local_studentprofile'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$form = new \local_studentprofile\form\degree_form();

if ($action === 'edit' && $id > 0) {
    $record = $DB->get_record('local_studentprofile_degrees', ['id' => $id], '*', MUST_EXIST);
    $form->set_data($record);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/studentprofile/admin_degrees.php'));
} else if ($data = $form->get_data()) {
    require_sesskey();
    $allowed_types = ['UG', 'PG'];
    $degreetype = clean_param($data->degreetype, PARAM_ALPHA);
    if (!in_array($degreetype, $allowed_types)) {
        throw new moodle_exception('errorinvaliddegree', 'local_studentprofile');
    }
    $now = time();
    $record = new stdClass();
    $record->degreename   = clean_param(trim($data->degreename), PARAM_TEXT);
    $record->degreetype   = $degreetype;
    $record->sortorder    = (int)$data->sortorder;
    $record->timemodified = $now;

    if (!empty($data->id)) {
        $record->id = (int)$data->id;
        $DB->update_record('local_studentprofile_degrees', $record);
    } else {
        $record->timecreated = $now;
        $DB->insert_record('local_studentprofile_degrees', $record);
    }
    redirect(
        new moodle_url('/local/studentprofile/admin_degrees.php'),
        get_string('degreesaved', 'local_studentprofile'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();

$backurl = new moodle_url('/admin/settings.php', ['section' => 'local_studentprofile']);
echo \html_writer::start_div('mb-3');
echo \html_writer::link($backurl, '← Back to Settings', ['class' => 'btn btn-secondary']);
echo \html_writer::end_div();

$degrees = $DB->get_records('local_studentprofile_degrees', null, 'sortorder ASC, degreename ASC');
$addurl  = new moodle_url('/local/studentprofile/admin_degrees.php');

echo html_writer::tag('p', html_writer::link($addurl, get_string('adddegree', 'local_studentprofile'), ['class' => 'btn btn-primary']));

if (!empty($degrees)) {
    $table = new html_table();
    $table->head = [
        get_string('degreename', 'local_studentprofile'),
        get_string('degreetype', 'local_studentprofile'),
        get_string('sortorder', 'local_studentprofile'),
        get_string('edit'),
        get_string('delete'),
    ];
    $table->attributes['class'] = 'admintable generaltable';

    foreach ($degrees as $d) {
        $editurl   = new moodle_url('/local/studentprofile/admin_degrees.php', ['action' => 'edit', 'id' => $d->id, 'sesskey' => sesskey()]);
        $deleteurl = new moodle_url('/local/studentprofile/admin_degrees.php', ['action' => 'delete', 'id' => $d->id, 'sesskey' => sesskey()]);
        $typestr   = ($d->degreetype === 'PG') ? get_string('ugpg_pg', 'local_studentprofile') : get_string('ugpg_ug', 'local_studentprofile');
        $deletelink = html_writer::link(
            $deleteurl,
            get_string('delete'),
            ['class' => 'btn btn-sm btn-danger', 'onclick' => 'return confirm(' . json_encode(get_string('confirmdelete', 'local_studentprofile')) . ')']
        );
        $table->data[] = [
            s($d->degreename),
            s($typestr),
            (int)$d->sortorder,
            html_writer::link($editurl, get_string('edit'), ['class' => 'btn btn-sm btn-secondary']),
            $deletelink,
        ];
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('norecordsfound', 'moodle'), 'info');
}

if ($action === 'edit' || $action === '') {
    echo html_writer::tag('h3', $id > 0 ? get_string('editdegree', 'local_studentprofile') : get_string('adddegree', 'local_studentprofile'));
    $form->display();
}

echo $OUTPUT->footer();
