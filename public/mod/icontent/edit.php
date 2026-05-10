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
 * Edit icontent page.
 *
 * @package    mod_icontent
 * @copyright  2005-2016 Leo Santos {@link http://facebook.com/leorenisJC}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_icontent\local\icontent_info;

require(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once(dirname(__FILE__) . '/edit_form.php');

$cmid = required_param('cmid', PARAM_INT);  // Content Course Module ID.
$pageid = optional_param('id', 0, PARAM_INT); // Page ID.
$pagenum = optional_param('pagenum', 0, PARAM_INT);

$cm = get_coursemodule_from_id('icontent', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$icontent = $DB->get_record('icontent', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/icontent:edit', $context);

// Log this request.
$params = [
    'objectid' => $PAGE->cm->instance,
    'context' => $PAGE->context,
];
$event = \mod_icontent\event\course_module_viewed::create($params);
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $icontent);
$event->trigger();

$PAGE->set_url('/mod/icontent/edit.php', ['cmid' => $cmid, 'id' => $pageid, 'pagenum' => $pagenum]);
$PAGE->set_pagelayout('incourse');

if ($pageid) {
    $page = $DB->get_record('icontent_pages', ['id' => $pageid, 'icontentid' => $icontent->id], '*', MUST_EXIST);
    // 20240920 See if there are any tags for this page.
    $page->tags = core_tag_tag::get_item_tags_array('mod_icontent', 'icontent_pages', $pageid);
} else {
    $page = new stdClass();
    $page->id = null;
    $page->pagenum = $pagenum;
}
$page->icontentid = $icontent->id;
$page->cmid = $cm->id;

// Mirror view.php TOC behavior while editing so page navigation remains available.
$pages = icontent_info::icontent_preload_pages($icontent);
$currenttocpage = !empty($page->pagenum) ? (int)$page->pagenum : icontent_get_startpagenum($icontent, $context);
if (!empty($pages)) {
    icontent_add_fake_block($pages, $currenttocpage, $icontent, $cm, true);
}

$maxbytes = get_user_max_upload_file_size($PAGE->context, $CFG->maxbytes, $COURSE->maxbytes);
$bgimagemaxbytes = $maxbytes;
$bgimageoptions = [
    'subdirs' => 0,
    'maxbytes' => $bgimagemaxbytes,
    'maxfiles' => 1,
    'accepted_types' => ['web_image'],
];

$pageicontentoptions = [
    'noclean' => true,
    'subdirs' => true,
    'maxfiles' => -1,
    'maxbytes' => $maxbytes,
    'context' => $context,
];
$page = file_prepare_standard_editor($page, 'pageicontent', $pageicontentoptions, $context, 'mod_icontent', 'page', $page->id);
$page = file_prepare_standard_filemanager($page, 'bgimage', $bgimageoptions, $context, 'mod_icontent', 'bgpage', $page->id);

$mform = new icontent_pages_edit_form(null, [
    'page' => $page,
    'pageicontentoptions' => $pageicontentoptions,
    'bgimagemaxbytes' => $bgimagemaxbytes,
]);
$mform->set_data($page);

// If data submitted, then process and store.
if ($mform->is_cancelled()) {
    if (empty($page->id)) {
        redirect("view.php?id=$cm->id");
    } else {
        redirect("view.php?id=$cm->id&pageid=$page->id");
    }
} else if ($data = $mform->get_data()) {
    $data->bgcolor = icontent_normalize_hex_colour($data->bgcolor, $icontent->bgcolor);
    $data->bordercolor = icontent_normalize_hex_colour($data->bordercolor, $icontent->bordercolor);
    $data->titlecolor = icontent_normalize_hex_colour($data->titlecolor, '#000000');

    // 20240920 Added tags to the form.
    core_tag_tag::set_item_tags(
        'mod_icontent',
        'icontent_pages',
        $pageid,
        $context,
        $data->tags,
    );

    // Update.
    if ($data->id) {
        // Store the files.
        $data->timemodified = time();
        $data = file_postupdate_standard_editor(
            $data,
            'pageicontent',
            $pageicontentoptions,
            $context,
            'mod_icontent',
            'page',
            $data->id
        );
        // Defensive fallback: preserve existing background image if the submitted
        // draft area is unexpectedly empty while editing non-file fields.
        $fs = get_file_storage();
        $draftbgfiles = $fs->get_area_files(
            context_user::instance($USER->id)->id,
            'user',
            'draft',
            (int)$data->bgimage_filemanager,
            'id',
            false
        );
        $existingbgfiles = $fs->get_area_files(
            $context->id,
            'mod_icontent',
            'bgpage',
            (int)$data->id,
            'id',
            false
        );
        if (!empty($draftbgfiles) || empty($existingbgfiles)) {
            $data = file_postupdate_standard_filemanager(
                $data,
                'bgimage',
                $bgimageoptions,
                $context,
                'mod_icontent',
                'bgpage',
                $data->id
            );
        } else {
            $data->bgimage = !empty($existingbgfiles) ? '1' : '';
        }

        $DB->update_record('icontent_pages', $data);
        // Get page.
        $page = $DB->get_record('icontent_pages', ['id' => $data->id]);
        // Set log.
        \mod_icontent\event\page_updated::create_from_page($icontent, $context, $page)->trigger();
        // Redirect.
        redirect("view.php?id=$cm->id&pageid=$data->id", get_string('msgsucess', 'icontent'));
    } else {
        $data->pageicontent = ''; // Updated later.
        $data->pageicontentformat = FORMAT_HTML; // Updated later.
        $data->id = $DB->insert_record('icontent_pages', $data);
        $data = file_postupdate_standard_editor(
            $data,
            'pageicontent',
            $pageicontentoptions,
            $context,
            'mod_icontent',
            'page',
            $data->id
        );
        $data = file_postupdate_standard_filemanager(
            $data,
            'bgimage',
            $bgimageoptions,
            $context,
            'mod_icontent',
            'bgpage',
            $data->id
        );

        $DB->update_record('icontent_pages', $data);
        // Fix structure.
        icontent_info::icontent_preload_pages($icontent);
        // Get page.
        $page = $DB->get_record('icontent_pages', ['id' => $data->id]);
        // Set log.
        \mod_icontent\event\page_created::create_from_page($icontent, $context, $page)->trigger();
        redirect("view.php?id=$cm->id&pageid=$data->id", get_string('msgsucess', 'icontent')); // Redirect.
    }
}
// Otherwise fill and print the form.
$PAGE->set_title($icontent->name);
$PAGE->set_heading($course->fullname);
$renderer = $PAGE->get_renderer('mod_icontent');
$renderer->icontent_requires_css();

echo $OUTPUT->header();
echo $OUTPUT->heading($icontent->name);

$mform->display();

echo $OUTPUT->footer();
