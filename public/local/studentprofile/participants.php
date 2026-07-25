<?php
/**
 * Custom participants list page with advanced student profile filters.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/notes/lib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');

$courseid = required_param('id', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);
$frontpagectx = context_course::instance(SITEID);

require_login($course);
course_require_view_participants($context);


// Use /user/index.php as the canonical URL so that:
//  (a) the 'Participants' tab in the course secondary nav renders as active, and
//  (b) render_participants_tertiary_nav() selects 'Enrolled users' correctly.
// The hook in classes/hook/after_config.php intercepts /user/index.php and
// redirects to this page, so no user will ever land on /user/index.php directly.
$PAGE->set_url('/user/index.php', [
    'id'      => $course->id,
    'page'    => $page,
    'perpage' => $perpage,
]);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title("$course->shortname: " . get_string('participants'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagetype('course-view-participants');
$PAGE->add_body_class('path-user');

echo $OUTPUT->header();

$tableuniqueid = "user-index-participants-{$course->id}";
$table = new \local_studentprofile\table\participants($tableuniqueid);

// Enrolment manager — provides the 'Enrol users' button.
$manager = new course_enrolment_manager($PAGE, $course);
$enrolrenderer = $PAGE->get_renderer('core_enrol');
$enrolbuttons = $manager->get_manual_enrol_buttons();
$enrolbuttonsout = '';
foreach ($enrolbuttons as $enrolbutton) {
    $enrolbuttonsout .= $enrolrenderer->render($enrolbutton);
}

// Render the tertiary nav: 'Enrolled users' heading + 'Enrol users' button.
echo $OUTPUT->render_participants_tertiary_nav($course, html_writer::div($enrolbuttonsout, '', [
    'data-region'         => 'wrapper',
    'data-table-uniqueid' => $tableuniqueid,
]));

$filterset = new \local_studentprofile\table\participants_filterset();
$filterset->add_filter(new \core_table\local\filter\integer_filter('courseid', \core_table\local\filter\filter::JOINTYPE_DEFAULT, [(int)$course->id]));
$table->set_filterset($filterset);

// Render the user filters.
$filterrenderable = new \local_studentprofile\output\participants_filter($context, $tableuniqueid);
$templatecontext = $filterrenderable->export_for_template($OUTPUT);
echo $OUTPUT->render_from_template('core_user/participantsfilter', $templatecontext);

echo html_writer::start_div('userlist');
echo html_writer::start_tag('form', [
    'action'             => (new moodle_url('/user/action_redir.php'))->out(false),
    'method'             => 'post',
    'id'                 => 'participantsform',
    'data-course-id'     => $course->id,
    'data-table-unique-id' => $tableuniqueid,
]);
echo '<div>';
echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
echo '<input type="hidden" name="id" value="' . $course->id . '" />';
echo html_writer::tag(
    'p',
    get_string('countparticipantsfound', 'core_user', $table->totalrows),
    ['data-region' => 'participant-count']
);
$table->out($perpage, true);

// Bulk actions — 'With selected users... Choose...' block.
$bulkoptions = (object) [
    'uniqueid' => $tableuniqueid,
];

echo '<br /><div class="buttons"><div class="d-flex flex-wrap align-items-center">';
echo html_writer::start_tag('div', ['class' => 'btn-group']);
if ($table->get_page_size() < $table->totalrows) {
    $label = get_string('selectalluserswithcount', 'moodle', $table->totalrows);
    echo html_writer::empty_tag('input', [
        'type'                 => 'button',
        'id'                   => 'checkall',
        'class'                => 'btn btn-secondary',
        'value'                => $label,
        'data-target-page-size' => TABLE_SHOW_ALL_PAGE_SIZE,
    ]);
}
echo html_writer::end_tag('div');

$displaylist = [];
if (!empty($CFG->messaging) && has_all_capabilities(['moodle/site:sendmessage', 'moodle/course:bulkmessaging'], $context)) {
    $displaylist['#messageselect'] = get_string('messageselectadd');
}
if (!empty($CFG->enablenotes) && has_capability('moodle/notes:manage', $context) && $context->id != $frontpagectx->id) {
    $displaylist['#addgroupnote'] = get_string('addnewnote', 'notes');
}

$downloadoptions = [];
$formats = core_plugin_manager::instance()->get_plugins_of_type('dataformat');
foreach ($formats as $format) {
    if ($format->is_enabled()) {
        $params = ['operation' => 'download_participants', 'dataformat' => $format->name];
        $url = new moodle_url('bulkchange.php', $params);
        $downloadoptions[$url->out(false)] = get_string('dataformat', $format->component);
    }
}
if (!empty($downloadoptions)) {
    $displaylist[] = [get_string('downloadas', 'table') => $downloadoptions];
}

if ($context->id != $frontpagectx->id) {
    $instances = $manager->get_enrolment_instances();
    $plugins   = $manager->get_enrolment_plugins(false);
    foreach ($instances as $instance) {
        if (!isset($plugins[$instance->enrol])) {
            continue;
        }
        $plugin = $plugins[$instance->enrol];
        $bulkoperations = $plugin->get_bulk_operations($manager);
        $pluginoptions = [];
        foreach ($bulkoperations as $key => $bulkoperation) {
            $params = ['plugin' => $plugin->get_name(), 'operation' => $key];
            $url = new moodle_url('bulkchange.php', $params);
            $pluginoptions[$url->out(false)] = $bulkoperation->get_title();
        }
        if (!empty($pluginoptions)) {
            $name = get_string('pluginname', 'enrol_' . $plugin->get_name());
            $displaylist[] = [$name => $pluginoptions];
        }
    }
}

$selectactionparams = [
    'id'             => 'formactionid',
    'class'          => 'ms-2',
    'data-action'    => 'toggle',
    'data-togglegroup' => 'participants-table',
    'data-toggle'    => 'action',
    'disabled'       => 'disabled',
];
$label  = html_writer::tag('label', get_string('withselectedusers'), ['for' => 'formactionid', 'class' => 'col-form-label d-inline']);
$select = html_writer::select($displaylist, 'formaction', '', ['' => 'choosedots'], $selectactionparams);
echo html_writer::tag('div', $label . $select, ['class' => 'd-flex flex-wrap align-items-md-center gap-2 mb-3']);

echo '<input type="hidden" name="id" value="' . $course->id . '" />';
echo '<div class="d-none" data-region="state-help-icon">' . $OUTPUT->help_icon('publishstate', 'notes') . '</div>';
echo '</div></div></div>';

$bulkoptions->noteStateNames = note_get_state_names();

echo '</form>';
$PAGE->requires->js_call_amd('core_user/participants', 'init', [$bulkoptions]);
echo '</div>'; // userlist

// Bottom 'Enrol users' button (re-generated to avoid duplicate IDs).
$enrolrenderer = $PAGE->get_renderer('core_enrol');
$enrolbuttons  = $manager->get_manual_enrol_buttons();
$enrolbottomout = '';
foreach ($enrolbuttons as $enrolbutton) {
    $enrolbottomout .= $enrolrenderer->render($enrolbutton);
}
echo html_writer::div($enrolbottomout, 'd-flex justify-content-end', [
    'data-region'         => 'wrapper',
    'data-table-uniqueid' => $tableuniqueid,
]);

echo $OUTPUT->footer();

