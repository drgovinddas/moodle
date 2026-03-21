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
 * Prints a particular instance of icontent.
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_icontent
 * @copyright  2016 Leo Renis Santos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(__DIR__ .'/../../lib/questionlib.php');
use mod_icontent\question\icontent_question_options;
use core_question\local\statistics\statistics_bulk_loader;

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or.
$n = optional_param('n', 0, PARAM_INT);  // The icontent instance ID.
$pageid = optional_param('pageid', 0, PARAM_INT); // Chapter ID.
$action = optional_param('action', '', PARAM_BOOL);

$sort = optional_param('sort', '', PARAM_RAW);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', ICONTENT_PER_PAGE, PARAM_INT);
$questioncategoryid = optional_param('questioncategoryid', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('icontent', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $icontent = $DB->get_record('icontent', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($n) {
    $icontent = $DB->get_record('icontent', ['id' => $n], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $icontent->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('icontent', $icontent->id, $course->id, false, MUST_EXIST);
} else {
    throw new moodle_exception(get_string('incorrectmodule', 'icontent'));
}
if (!$pageid) {
    throw new moodle_exception(get_string('incorrectpage', 'icontent'));
}

$currentpage = $DB->get_record('icontent_pages', [
    'id' => $pageid,
    'cmid' => $cm->id,
    'icontentid' => $icontent->id,
], 'id, title, pagenum', MUST_EXIST);

// Require login.
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
$coursecontext = $context->get_course_context(true)->id;
require_capability('mod/icontent:newquestion', $context);
// Log event.
\mod_icontent\event\question_page_viewed::create_from_question_page($icontent, $context, $pageid)->trigger();

// Print the page header.
$PAGE->set_url('/mod/icontent/addquestionpage.php', ['id' => $cm->id, 'pageid' => $pageid]);
$PAGE->set_title(format_string($icontent->name) . ' - ' . format_string($currentpage->title));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('icontent-addquestionpage');
// CSS.
$PAGE->requires->css(new moodle_url($CFG->wwwroot.'/mod/icontent/styles/font-awesome-4.6.2/css/font-awesome.min.css'));
$hascommentplugin = \core\plugininfo\qbank::is_plugin_enabled('qbank_comment');
if ($hascommentplugin && !empty($CFG->usecomments)) {
    $PAGE->requires->js_call_amd('qbank_comment/comment', 'init');
}
$url = new moodle_url('/mod/icontent/addquestionpage.php',
    [
        'id' => $id,
        'pageid' => $pageid,
        'questioncategoryid' => $questioncategoryid,
        'page' => $page,
        'perpage' => $perpage,
    ]
);

if ($action) {
    // Receives values.
    $questions = optional_param_array('question', [], PARAM_RAW);
    // Save values.
    if (icontent_add_questionpage($questions, $pageid, $cm->id)) {
        $urlredirect = new moodle_url('/mod/icontent/view.php', ['id' => $cm->id, 'pageid' => $pageid]);
        redirect($urlredirect, get_string('msgaddquestionpage', 'mod_icontent'));
    }
}

// Output starts here.
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($currentpage->title) . ": " . get_string('addquestion', 'mod_icontent'));

// Get info.
$sort = icontent_check_value_sort($sort);

// 20260227 Moodle 5+ uses qbank module contexts for categories.
$questioncategoryname = '';
$categorycontextids = [];

// Prefer contexts from qbank module instances available in the course.
$defaultbankmodname = \core_question\local\bank\question_bank_helper::get_default_question_bank_activity_name();
$modinfo = get_fast_modinfo($course);
$banks = $modinfo->get_instances_of($defaultbankmodname);
foreach ($banks as $bank) {
    $categorycontextids[] = (string) $bank->context->id;
}

// Fallback for older setups where categories may still be in course context.
$categorycontextids[] = (string) $coursecontext;

$categorycontextids = array_unique(array_filter($categorycontextids));
$qcids = [];
if (!empty($categorycontextids)) {
    $qcids = \qbank_managecategories\helper::get_categories_for_contexts(
        implode(',', $categorycontextids),
        $sortorder = 'parent, sortorder, name ASC',
        $top = false
    );
}

if (empty($qcids)) {
    echo html_writer::div(get_string('emptyquestionbank', 'mod_icontent'), 'alert alert-warning');
    echo $OUTPUT->footer();
    exit;
}

$categorymenu = [];
foreach ($qcids as $qcid) {
    $categorymenu[(int)$qcid->id] = format_string($qcid->name) . ' (ID ' . (int)$qcid->id . ')';
}

if (!$questioncategoryid || !array_key_exists($questioncategoryid, $categorymenu)) {
    $questioncategoryid = (int) array_key_first($categorymenu);
}
$questioncategoryname = $categorymenu[$questioncategoryid] ?? '';

$questions = icontent_question_options::icontent_get_questions_of_questionbank(
    $coursecontext,
    $questioncategoryid,
    $sort,
    $page,
    $perpage
);
$hasquestions = !empty($questions);
$tquestions = 0;
if ($questioncategoryid) {
    $tquestions = icontent_question_options::icontent_count_questions_of_questionbank_filtered($questioncategoryid);
}
echo get_string('totalquestioncount', 'icontent', $tquestions);
$qtscurrentpage = icontent_get_questions_of_currentpage($pageid, $cm->id);
$answerscurrentpage = icontent_checks_answers_of_currentpage($pageid, $cm->id);
// Make table questions.
$table = new html_table();
$table->id = "categoryquestions";
$table->attributes = ['class' => 'icontentquestions'];
$table->colclasses = ['checkbox', 'qtype', 'questionname', 'actions', 'status', 'version', 'creatorname', 'comments', 'needschecking', 'facilityindex', 'discriminativeefficiency'];
$selectallcheckbox = html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'id' => 'idcheckallquestions',
    'title' => get_string('selectall'),
]);

$makeheaderwithmenu = static function(string $label) use ($OUTPUT): string {
    $makeitemcontent = static function(string $iconname, string $text) use ($OUTPUT): string {
        $icon = $OUTPUT->pix_icon($iconname, '', 'moodle', ['class' => 'iconsmall me-1']);
        return html_writer::span($icon . html_writer::span($text), 'd-inline-flex align-items-center');
    };

    $moveitemcontent = $makeitemcontent('i/dragdrop', get_string('move'));
    $removeitemcontent = $makeitemcontent('t/delete', get_string('remove'));
    $resizeitemcontent = $makeitemcontent('i/twoway', get_string('resize', 'qbank_columnsortorder'));

    $menubutton = html_writer::tag(
        'button',
        html_writer::span('⋮', 'small'),
        [
            'type' => 'button',
            'class' => 'btn btn-link btn-sm p-0 ms-1 text-decoration-none',
            'data-bs-toggle' => 'dropdown',
            'aria-expanded' => 'false',
            'title' => get_string('actions'),
        ]
    );

    $menu = html_writer::tag('ul',
        html_writer::tag('li', html_writer::link('#', $moveitemcontent, ['class' => 'dropdown-item icontent-col-action d-flex align-items-center', 'data-action' => 'move'])) .
        html_writer::tag('li', html_writer::link('#', $removeitemcontent, ['class' => 'dropdown-item icontent-col-action d-flex align-items-center', 'data-action' => 'remove'])) .
        html_writer::tag('li', html_writer::link('#', $resizeitemcontent, ['class' => 'dropdown-item icontent-col-action d-flex align-items-center', 'data-action' => 'resize'])),
        ['class' => 'dropdown-menu']
    );

    return html_writer::tag(
        'span',
        $label . html_writer::tag('span', $menubutton . $menu, ['class' => 'dropdown d-inline-block']),
        ['class' => 'd-inline-flex align-items-center']
    );
};

$table->head  = [
    $makeheaderwithmenu($selectallcheckbox),
    $makeheaderwithmenu('T'),
    $makeheaderwithmenu('Question<br>Question name / ID number'),
    $makeheaderwithmenu(get_string('actions')),
    $makeheaderwithmenu(get_string('status')),
    $makeheaderwithmenu(get_string('version')),
    $makeheaderwithmenu(get_string('createdby', 'question')),
    $makeheaderwithmenu('Comments'),
    $makeheaderwithmenu('Needs checking?'),
    $makeheaderwithmenu('Facility index'),
    $makeheaderwithmenu('Discriminative efficiency'),
];

if ($hasquestions) {
    $hasstatisticsplugin = \core\plugininfo\qbank::is_plugin_enabled('qbank_statistics');
    $questionids = array_map(static function($question) {
        return (int) $question->qid;
    }, $questions);

    $commentsbyquestionid = [];
    if ($hascommentplugin && !empty($questionids)) {
        [$qidin, $qidparams] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED, 'qid');
        $commentparams = [
            'component' => 'qbank_comment',
            'commentarea' => 'question',
            'contextid' => \context_system::instance()->id,
        ] + $qidparams;
        $commentssql = "SELECT itemid, COUNT(1) AS commentcount
                          FROM {comments}
                         WHERE component = :component
                           AND commentarea = :commentarea
                           AND contextid = :contextid
                           AND itemid {$qidin}
                      GROUP BY itemid";
        $commentsbyquestionid = $DB->get_records_sql_menu($commentssql, $commentparams);
    }

    $aggregatestats = [];
    if ($hasstatisticsplugin && !empty($questionids)) {
        $aggregatestats = statistics_bulk_loader::load_aggregate_statistics(
            $questionids,
            ['discriminationindex', 'facility', 'discriminativeefficiency']
        );
    }

    foreach ($questions as $question) {
        $checked = isset($qtscurrentpage[$question->qid]) ? ['checked' => 'checked'] : [];
        $disabled = $answerscurrentpage ? ['disabled' => 'disabled'] : [];
        $checkbox = html_writer::empty_tag('input', ['type' => 'checkbox',
            'name' => 'question[]',
            'value' => $question->qid,
            'id' => 'idcheck'.$question->qid] + $checked + $disabled);
        $qtypecomponent = 'qtype_' . $question->qqtype;
        $qtypename = s($question->qqtype);
        $qtypeiconsrc = $OUTPUT->image_url('q/'.$question->qqtype, 'mod_icontent');
        if (\core_component::get_component_directory($qtypecomponent)) {
            $qtypename = get_string('pluginname', $qtypecomponent);
            $qtypeiconsrc = $OUTPUT->image_url('icon', $qtypecomponent);
        }
        $qtype = html_writer::empty_tag('img', [
            'src' => $qtypeiconsrc,
            'class' => 'smallicon',
            'alt' => $qtypename,
            'title' => $qtypename,
        ]);
        $qname = html_writer::label($question->qname, 'idcheck'.$question->qid);
        $idnumber = !empty($question->qbeidnumber) ? s($question->qbeidnumber) : '-';
        $questioncell = $qname . html_writer::div('ID number: ' . $idnumber, 'text-muted small');

        $returnurl = new moodle_url('/mod/icontent/addquestionpage.php', [
            'id' => (int)$cm->id,
            'pageid' => (int)$pageid,
            'questioncategoryid' => (int)$questioncategoryid,
            'page' => (int)$page,
            'perpage' => (int)$perpage,
            'sort' => $sort,
        ]);
        $previewurl = new moodle_url('/question/bank/previewquestion/preview.php', [
            'id' => (int)$question->qid,
            'cmid' => (int)$cm->id,
            'returnurl' => $returnurl->out_as_local_url(false),
        ]);
        $editurl = new moodle_url('/question/bank/editquestion/question.php', [
            'id' => (int)$question->qid,
            'cmid' => (int)$cm->id,
            'returnurl' => $returnurl->out_as_local_url(false),
        ]);
        $actions = html_writer::link($previewurl, get_string('preview')) . ' | ' .
            html_writer::link($editurl, get_string('edit'));

        $questionstats = $aggregatestats[(int)$question->qid] ?? [];
        $discriminationindex = $questionstats['discriminationindex'] ?? null;
        $facility = $questionstats['facility'] ?? null;
        $discriminativeefficiency = $questionstats['discriminativeefficiency'] ?? null;
        if ($hasstatisticsplugin) {
            [$needscheckingtext, $needscheckingclasses] = \qbank_statistics\helper::format_discrimination_index($discriminationindex);
            $needschecking = html_writer::span($needscheckingtext, trim('badge ' . $needscheckingclasses));
            $facilitydisplay = \qbank_statistics\helper::format_percentage($facility);
            $discriminativedisplay = \qbank_statistics\helper::format_percentage($discriminativeefficiency, false);
        } else {
            $needschecking = '-';
            $facilitydisplay = '-';
            $discriminativedisplay = '-';
        }
        $commentcount = (int) ($commentsbyquestionid[(int)$question->qid] ?? 0);
        $commentdisplay = (string) $commentcount;
        if ($hascommentplugin && !empty($CFG->usecomments)) {
            $questionstub = (object) [
                'id' => (int)$question->qid,
                'category' => (int)$question->qbequestioncategoryid,
            ];
            $commentargs = new stdClass();
            $commentargs->contextid = \context_system::instance()->id;
            $commentargs->courseid = $course->id;
            $commentargs->area = 'question';
            $commentargs->itemid = (int)$question->qid;
            $commentargs->component = 'qbank_comment';
            $comment = new comment($commentargs);

            if (question_has_capability_on($questionstub, 'comment') && $comment->can_post()) {
                $commentdisplay = html_writer::link('#', (string)$commentcount, [
                    'data-target' => 'questioncommentpreview_' . (int)$question->qid,
                    'data-questionid' => (int)$question->qid,
                    'data-courseid' => (int)$course->id,
                    'data-contextid' => \context_system::instance()->id,
                ]);
            }
        }

        // These users must exist or you will get an error.
        $createdby = icontent_get_user_by_id($question->qcreatedby);

        $table->data[] = [
            $checkbox,
            $qtype,
            $questioncell,
            $actions,
            $question->qvstatus,
            (int)$question->qvversion,
            $createdby->firstname.' '.$createdby->lastname.
                '<br>'.date(get_config('mod_icontent', 'dateformat'), $question->qtimecreated),
            $commentdisplay,
            $needschecking,
            $facilitydisplay,
            $discriminativedisplay,
        ];
    }
} else {
    echo html_writer::div(get_string('emptyquestionbank', 'mod_icontent'), 'alert alert-warning');
}
// Show elements HTML.
echo html_writer::div(get_string('infomaxquestionperpage', 'mod_icontent'), 'alert alert-info');
echo $answerscurrentpage ? html_writer::div(get_string('msgstatusdisplay', 'mod_icontent'), 'alert alert-warning') : null;
echo html_writer::start_tag('form',
    [
        'action' => new moodle_url('/mod/icontent/addquestionpage.php'),
        'method' => 'GET',
        'class' => 'mb-3',
    ]
);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'pageid', 'value' => $pageid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sort', 'value' => $sort]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'perpage', 'value' => $perpage]);
echo html_writer::start_div('d-flex align-items-end gap-2 flex-wrap');
echo html_writer::tag('label', get_string('category'), ['for' => 'id_questioncategoryid', 'class' => 'mb-0']);
echo html_writer::select($categorymenu, 'questioncategoryid', $questioncategoryid, false, ['id' => 'id_questioncategoryid']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-secondary', 'value' => get_string('go')]);
echo html_writer::end_div();
echo html_writer::end_tag('form');
echo html_writer::start_tag('form',
    ['action' => new moodle_url('/mod/icontent/addquestionpage.php',
        ['id' => $cm->id, 'pageid' => $pageid, 'questioncategoryid' => $questioncategoryid]),
        'method' => 'POST']
    );
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => true]);
echo html_writer::start_div('categoryquestionscontainer');
echo html_writer::table($table);
echo html_writer::script("(function() {
    var table = document.getElementById('categoryquestions');
    if (!table) {
        return;
    }

    var master = document.getElementById('idcheckallquestions');
    var toggleColumnClass = 'icontent-col-resized';

    function getColumnIndex(cell) {
        if (!cell || !cell.parentNode) {
            return -1;
        }
        return Array.prototype.indexOf.call(cell.parentNode.children, cell);
    }

    function forEachColumnCell(index, callback) {
        table.querySelectorAll('tr').forEach(function(row) {
            var target = row.children[index];
            if (target) {
                callback(target, row);
            }
        });
    }

    if (master) {
        master.addEventListener('change', function() {
            var checkboxes = document.querySelectorAll('#categoryquestions input[name=\"question[]\"]:not([disabled])');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = master.checked;
            });
        });
    }

    table.addEventListener('click', function(e) {
        var actionlink = e.target.closest('.icontent-col-action');
        if (!actionlink) {
            return;
        }
        e.preventDefault();

        var action = actionlink.dataset.action;
        var th = actionlink.closest('th');
        var index = getColumnIndex(th);
        if (index < 0) {
            return;
        }

        if (action === 'move') {
            table.querySelectorAll('tr').forEach(function(row) {
                var current = row.children[index];
                var next = row.children[index + 1];
                if (current && next) {
                    row.insertBefore(next, current);
                }
            });
            return;
        }

        if (action === 'remove') {
            forEachColumnCell(index, function(cell) {
                cell.remove();
            });
            return;
        }

        if (action === 'resize') {
            forEachColumnCell(index, function(cell) {
                if (cell.classList.contains(toggleColumnClass)) {
                    cell.classList.remove(toggleColumnClass);
                    cell.style.width = '';
                    cell.style.minWidth = '';
                    cell.style.maxWidth = '';
                } else {
                    cell.classList.add(toggleColumnClass);
                    cell.style.width = '280px';
                    cell.style.minWidth = '280px';
                    cell.style.maxWidth = '280px';
                }
            });
        }
    });
})();");
echo $OUTPUT->paging_bar($tquestions, $page, $perpage, $url);
echo html_writer::end_div();

// 20240107 Create a link back to where we came from in case we want to cancel.
$url2 = new moodle_url('/mod/icontent/view.php',
    [
        'id' => $id,
        'pageid' => $pageid,
    ]
);
// 20240107 Added two buttons, Add and Cancel..
echo '<input class="btn btn-primary"
    style="border-radius: 8px"
    name="button"
    onClick="return clClick()"
    '.($hasquestions ? '' : 'disabled="disabled"').'
    type="submit" value="'
    .get_string('add')
    .'"> <a href="'
    .$url2
    .'" class="btn btn-secondary"  style="border-radius: 8px">'
    .get_string('cancel')
    .'</a>';
echo html_writer::end_tag('form');

// Finish the page.
echo $OUTPUT->footer();
