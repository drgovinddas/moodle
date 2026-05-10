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
 * Internal library of functions for module iContent.
 *
 * All the icontent specific functions, needed to implement the module logic, should go here.
 *
 * @package    mod_icontent
 * @copyright  2016 Leo Renis Santos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Constants.
 */
define('ICONTENT_PAGE_MIN_HEIGHT', 500);
define('ICONTENT_MAX_PER_PAGE', 1000);
define('ICONTENT_PER_PAGE', 20);
// Questions.
define('ICONTENT_QTYPE_MATCH', 'match');
define('ICONTENT_QTYPE_MULTICHOICE', 'multichoice');
define('ICONTENT_QTYPE_TRUEFALSE', 'truefalse');
define('ICONTENT_QTYPE_ESSAY', 'essay');
define('ICONTENT_QTYPE_ESSAYAUTOGRADE', 'essayautograde');
define('ICONTENT_QTYPE_ESSAY_STATUS_TOEVALUATE', 'toevaluate');
define('ICONTENT_QTYPE_ESSAY_STATUS_VALUED', 'valued');
define('ICONTENT_QUESTION_FRACTION', 1);

require_once(dirname(__FILE__) . '/lib.php');

/**
 * Normalize a hex color to six uppercase hex chars without #.
 *
 * @param string|null $value
 * @param string $fallback
 * @return string
 */
function icontent_normalize_hex_colour($value, $fallback = 'FCFCFC') {
    $default = strtoupper(ltrim(trim((string)$fallback), '#'));
    if (!preg_match('/^[0-9A-F]{6}$/', $default)) {
        $default = 'FCFCFC';
    }

    $normalized = strtoupper(ltrim(trim((string)$value), '#'));
    if (!preg_match('/^[0-9A-F]{6}$/', $normalized)) {
        return $default;
    }

    return $normalized;
}

/**
 * Check whether the Phase 1 question engine bridge is enabled.
 *
 * @return bool
 */
function icontent_question_engine_phase1_enabled() {
    return !empty(get_config('mod_icontent', 'questionenginephase1'));
}

/**
 * Get qtypes supported by the Phase 1 bridge.
 *
 * @return array
 */
function icontent_question_engine_phase1_supported_qtypes() {
    $legacyqtypes = [];

    $allqtypes = array_values(array_keys(\core_component::get_plugin_list('qtype')));
    return array_values(array_diff($allqtypes, $legacyqtypes));
}

/**
 * Reset cached QUBA usage for one user/page combination.
 *
 * @param int $cmid
 * @param int $pageid
 * @param int $userid
 * @return void
 */
function icontent_question_engine_phase1_reset_page_usage($cmid, $pageid, $userid) {
    global $SESSION;

    if (empty($SESSION->mod_icontent_quba) || !is_array($SESSION->mod_icontent_quba)) {
        return;
    }

    $sessionkey = icontent_question_engine_phase1_get_session_key((int)$cmid, (int)$pageid, (int)$userid);
    if (array_key_exists($sessionkey, $SESSION->mod_icontent_quba)) {
        unset($SESSION->mod_icontent_quba[$sessionkey]);
    }
}

/**
 * Build session key used to store a QUBA id per user/page.
 *
 * @param int $cmid
 * @param int $pageid
 * @param int $userid
 * @return string
 */
function icontent_question_engine_phase1_get_session_key($cmid, $pageid, $userid) {
    return 'cm' . $cmid . '_page' . $pageid . '_user' . $userid;
}

/**
 * Phase 1 bridge: create/load a QUBA for supported question types.
 *
 * This wiring is intentionally non-invasive. It prepares question_engine usage
 * behind a feature flag while keeping the existing iContent renderer and submit
 * pipeline unchanged.
 *
 * @param object $objpage
 * @param array $questions
 * @return void
 */
function icontent_question_engine_phase1_bootstrap_usage($objpage, $questions) {
    global $CFG, $SESSION, $USER;

    if (!icontent_question_engine_phase1_enabled()) {
        return;
    }

    if (empty($questions) || empty($objpage->cmid) || empty($objpage->id) || empty($USER->id)) {
        return;
    }

    $supportedqtypes = icontent_question_engine_phase1_supported_qtypes();
    $eligiblequestions = [];
    foreach ($questions as $question) {
        if (!empty($question->qtype) && in_array($question->qtype, $supportedqtypes)) {
            $eligiblequestions[] = $question;
        }
    }

    if (empty($eligiblequestions)) {
        return;
    }

    require_once($CFG->libdir . '/questionlib.php');

    if (!isset($SESSION->mod_icontent_quba)) {
        $SESSION->mod_icontent_quba = [];
    }

    $sessionkey = icontent_question_engine_phase1_get_session_key($objpage->cmid, $objpage->id, $USER->id);
    $existingqubaid = $SESSION->mod_icontent_quba[$sessionkey] ?? 0;
    $targetcount = count($eligiblequestions);
    $targetquestionids = array_map(static function ($question) {
        return (int)$question->qid;
    }, $eligiblequestions);
    sort($targetquestionids);

    if (!empty($existingqubaid)) {
        try {
            $existingquba = question_engine::load_questions_usage_by_activity($existingqubaid);
            $existingquestionids = [];
            foreach ($existingquba->get_slots() as $slot) {
                $slotquestion = $existingquba->get_question($slot);
                if (!empty($slotquestion->id)) {
                    $existingquestionids[] = (int)$slotquestion->id;
                }
            }
            sort($existingquestionids);

            if (count($existingquba->get_slots()) === $targetcount && $existingquestionids === $targetquestionids) {
                return;
            }
        } catch (\Throwable $e) {
            debugging('Failed to load existing question usage: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    $context = context_module::instance($objpage->cmid);
    $quba = question_engine::make_questions_usage_by_activity('mod_icontent', $context);
    $quba->set_preferred_behaviour('deferredfeedback');

    foreach ($eligiblequestions as $question) {
        try {
            $questiondef = question_bank::load_question($question->qid);
            $quba->add_question($questiondef, 1);
        } catch (\Throwable $e) {
            // Skip invalid question definitions and continue with remaining items.
            continue;
        }
    }

    if (!count($quba->get_slots())) {
        return;
    }

    $quba->start_all_questions();
    question_engine::save_questions_usage_by_activity($quba);
    $SESSION->mod_icontent_quba[$sessionkey] = $quba->get_id();
}

/**
 * Phase 2 bridge: render a supported question using question_engine/QUBA.
 *
 * This intentionally does not process actions yet (Phase 3). It is a render-only
 * bridge with safe fallback to legacy HTML when anything is unavailable.
 *
 * @param object $objpage
 * @param object $question
 * @param int $displaynumber
 * @return string|false
 */
function icontent_question_engine_phase2_render_question($objpage, $question, $displaynumber = 1) {
    global $CFG, $SESSION, $USER;

    if (!icontent_question_engine_phase1_enabled()) {
        return false;
    }

    if (empty($question->qtype) || !in_array($question->qtype, icontent_question_engine_phase1_supported_qtypes())) {
        return false;
    }

    if (empty($objpage->cmid) || empty($objpage->id) || empty($USER->id)) {
        return false;
    }

    if (empty($SESSION->mod_icontent_quba) || !is_array($SESSION->mod_icontent_quba)) {
        return false;
    }

    $sessionkey = icontent_question_engine_phase1_get_session_key($objpage->cmid, $objpage->id, $USER->id);
    $qubaid = $SESSION->mod_icontent_quba[$sessionkey] ?? 0;
    if (empty($qubaid)) {
        return false;
    }

    require_once($CFG->libdir . '/questionlib.php');

    try {
        $quba = question_engine::load_questions_usage_by_activity($qubaid);
    } catch (\Throwable $e) {
        return false;
    }

    $slot = null;
    foreach ($quba->get_slots() as $candidateslot) {
        try {
            $slotquestion = $quba->get_question($candidateslot);
            if (!empty($slotquestion->id) && (int)$slotquestion->id === (int)$question->qid) {
                $slot = $candidateslot;
                break;
            }
        } catch (\Throwable $e) {
            continue;
        }
    }

    if (empty($slot)) {
        return false;
    }

    try {
        $displayoptions = new question_display_options();
        $renderedhtml = $quba->render_question($slot, $displayoptions, (string)$displaynumber);
    } catch (\Throwable $e) {
        return false;
    }

    $renderedhtml = icontent_qengine_rewrite_questiontext_pluginfile_urls(
        (string)$renderedhtml,
        (int)$question->qid,
        (int)$objpage->cmid
    );

    if (in_array((string)$question->qtype, ['ddimageortext', 'ddmarker'])) {
        $renderedhtml = icontent_qengine_embed_dd_background_data_uri(
            (string)$renderedhtml,
            (int)$question->qid,
            (string)$question->qtype,
            (int)$objpage->cmid
        );
    }

    $questiontools = icontent_make_question_tools($question, $objpage);

    return html_writer::div(
        $questiontools . $renderedhtml,
        'question ' . s($question->qtype) . ' qengine-render'
    );
}

/**
 * Rewrite questiontext pluginfile URLs in rendered question HTML to mod_icontent proxy URLs.
 *
 * @param string $renderedhtml
 * @param int $questionid
 * @param int $cmid
 * @return string
 */
function icontent_qengine_rewrite_questiontext_pluginfile_urls(string $renderedhtml, int $questionid, int $cmid): string {
    global $CFG;

    $cmcontext = context_module::instance($cmid, IGNORE_MISSING);
    if (!$cmcontext) {
        return $renderedhtml;
    }

    $wwwroot = preg_quote($CFG->wwwroot, '/');
    $pattern = '/(' . $wwwroot . '\/pluginfile\.php\/\d+\/question\/questiontext\/\d+\/\d+\/)(\d+)(\/[^"\?\s]+)(\?[^"\s]*)?/i';

    $rewritten = preg_replace_callback($pattern, static function (array $matches) use ($cmcontext, $questionid) {
        $itemid = (int)$matches[2];
        $filepathandname = ltrim((string)$matches[3], '/');
        $query = isset($matches[4]) ? (string)$matches[4] : '';

        $slashpos = strrpos($filepathandname, '/');
        if ($slashpos === false) {
            $filepath = '/';
            $filename = $filepathandname;
        } else {
            $filepath = '/' . trim(substr($filepathandname, 0, $slashpos), '/') . '/';
            $filename = substr($filepathandname, $slashpos + 1);
        }

        $proxyurl = moodle_url::make_pluginfile_url(
            (int)$cmcontext->id,
            'mod_icontent',
            'questiontextproxy',
            (int)$questionid,
            $filepath,
            $filename
        );

        return $proxyurl->out(false) . $query;
    }, $renderedhtml);

    return $rewritten ?? $renderedhtml;
}

/**
 * Replace drag-drop background image URL with a data URI fallback when available.
 *
 * @param string $renderedhtml
 * @param int $questionid
 * @param string $qtype
 * @param int $cmid
 * @return string
 */
function icontent_qengine_embed_dd_background_data_uri(string $renderedhtml, int $questionid, string $qtype, int $cmid): string {
    global $DB;

    $contextid = (int)$DB->get_field('question', 'contextid', ['id' => $questionid]);
    if (empty($contextid)) {
        return $renderedhtml;
    }

    $component = 'qtype_' . $qtype;
    $fs = get_file_storage();
    $files = $fs->get_area_files($contextid, $component, 'bgimage', $questionid, 'id ASC', false);
    if (empty($files)) {
        return $renderedhtml;
    }

    $imagefile = reset($files);
    if (!$imagefile) {
        return $renderedhtml;
    }

    $cmcontext = context_module::instance($cmid, IGNORE_MISSING);
    if ($cmcontext && $imagefile->get_filename() !== '.') {
        $proxysrc = moodle_url::make_pluginfile_url(
            (int)$cmcontext->id,
            'mod_icontent',
            'qtypebgimage',
            (int)$questionid,
            '/' . trim((string)$qtype, '/') . '/',
            $imagefile->get_filename()
        )->out(false);

        $rewrittenhtml = preg_replace_callback(
            '/<img([^>]*class="[^"]*dropbackground[^"]*"[^>]*)>/i',
            static function (array $matches) use ($proxysrc) {
                $imgtag = $matches[0];
                if (preg_match('/\ssrc="[^"]*"/i', $imgtag)) {
                    return preg_replace('/\ssrc="[^"]*"/i', ' src="' . s($proxysrc) . '"', $imgtag, 1);
                }
                return str_replace('<img', '<img src="' . s($proxysrc) . '"', $imgtag);
            },
            $renderedhtml,
            1
        );

        if (!empty($rewrittenhtml)) {
            return $rewrittenhtml;
        }
    }

    $mimetype = (string)$imagefile->get_mimetype();
    if (strpos($mimetype, 'image/') !== 0) {
        return $renderedhtml;
    }

    $content = $imagefile->get_content();
    if ($content === false || $content === '') {
        return $renderedhtml;
    }

    $datasrc = 'data:' . $mimetype . ';base64,' . base64_encode($content);

    return preg_replace_callback(
        '/<img([^>]*class="[^"]*dropbackground[^"]*"[^>]*)>/i',
        static function (array $matches) use ($datasrc) {
            $imgtag = $matches[0];
            if (preg_match('/\ssrc="[^"]*"/i', $imgtag)) {
                return preg_replace('/\ssrc="[^"]*"/i', ' src="' . s($datasrc) . '"', $imgtag, 1);
            }
            return str_replace('<img', '<img src="' . s($datasrc) . '"', $imgtag);
        },
        $renderedhtml,
        1
    ) ?? $renderedhtml;
}

/**
 * Add the icontent TOC sticky block to the default region.
 *
 * @param array $pages
 * @param object $page
 * @param object $icontent
 * @param object $cm
 * @param bool $edit
 */
function icontent_add_fake_block($pages, $page, $icontent, $cm, $edit) {
    global $OUTPUT, $PAGE;
    $toc = icontent_get_toc($pages, $page, $icontent, $cm, $edit, 0);
    $bc = new block_contents();
    $bc->title = get_string('icontentmenu', 'icontent');
    $bc->attributes['class'] = 'block block_icontent_toc';
    $bc->content = $toc;
    $defaultregion = $PAGE->blocks->get_default_region();
    $PAGE->blocks->add_fake_block($bc, $defaultregion);
}

/**
 * Generate toc structure.
 *
 * @param array $pages
 * @param object $page
 * @param object $icontent
 * @param object $cm
 * @param bool $edit
 * @return string
 */
function icontent_get_toc($pages, $page, $icontent, $cm, $edit) {
    global $USER, $OUTPUT;
    $context = context_module::instance($cm->id);
    $tpages = count($pages);
    $toc = '';
    $toc .= html_writer::start_tag('div', ['class' => 'icontent_toc clearfix']);
    // Teacher's TOC.
    if ($edit) {
        $toc .= html_writer::start_tag('ul');
        $i = 0;
        foreach ($pages as $pg) {
            $i++;
            $title = trim(format_string($pg->title, true, ['context' => $context]));
            $toc .= html_writer::start_tag('li', ['class' => 'clearfix']); // Start <li>.
            $toc .= html_writer::link(
                new moodle_url('/mod/icontent/view.php', ['id' => $pg->cmid, 'pageid' => $pg->id]),
                $title,
                [
                    'title' => s($title),
                    'class' => 'load-page page' . $pg->pagenum,
                    'data-pageid' => $pg->id,
                    'data-pagenum' => $pg->pagenum,
                    'data-cmid' => $pg->cmid,
                    'data-sesskey' => sesskey(),
                    'data-totalpages' => $tpages,
                ]
            );
            // Actions.
            $toc .= html_writer::start_tag('div', ['class' => 'action-list']); // Start <div>.
            if ($i != 1) {
                $toc .= html_writer::link(
                    new moodle_url(
                        'move.php',
                        [
                        'id' => $cm->id,
                        'pageid' => $pg->id,
                        'up' => '1',
                        'sesskey' => $USER->sesskey,
                        ]
                    ),
                    $OUTPUT->pix_icon('t/up', get_string('up')),
                    ['title' => get_string('up')]
                );
            }
            if ($i != count($pages)) {
                $toc .= html_writer::link(
                    new moodle_url(
                        'move.php',
                        [
                        'id' => $cm->id,
                        'pageid' => $pg->id,
                        'up' => '0',
                        'sesskey' => $USER->sesskey,
                        ]
                    ),
                    $OUTPUT->pix_icon('t/down', get_string('down')),
                    ['title' => get_string('down')]
                );
            }
            $toc .= html_writer::link(
                new moodle_url(
                    'edit.php',
                    [
                    'cmid' => $pg->cmid,
                    'id' => $pg->id,
                    'sesskey' => $USER->sesskey,
                    ]
                ),
                $OUTPUT->pix_icon('t/edit', get_string('edit')),
                ['title' => get_string('edit')]
            );
            $toc .= html_writer::link(
                new moodle_url(
                    'delete.php',
                    [
                    'id' => $pg->cmid,
                    'pageid' => $pg->id,
                    'sesskey' => $USER->sesskey,
                    ]
                ),
                $OUTPUT->pix_icon('t/delete', get_string('delete')),
                ['title' => get_string('delete')]
            );
            if ($pg->hidden) {
                $toc .= html_writer::link(
                    new moodle_url(
                        'show.php',
                        [
                        'id' => $pg->cmid,
                        'pageid' => $pg->id,
                        'sesskey' => $USER->sesskey,
                        ]
                    ),
                    $OUTPUT->pix_icon('t/show', get_string('show')),
                    ['title' => get_string('show')]
                );
            } else {
                $toc .= html_writer::link(
                    new moodle_url(
                        'show.php',
                        [
                        'id' => $pg->cmid,
                        'pageid' => $pg->id,
                        'sesskey' => $USER->sesskey,
                        ]
                    ),
                    $OUTPUT->pix_icon('t/hide', get_string('hide')),
                    ['title' => get_string('hide')]
                );
            }
            $toc .= html_writer::link(
                new moodle_url(
                    'edit.php',
                    [
                    'cmid' => $pg->cmid,
                    'pagenum' => $pg->pagenum,
                    'sesskey' => $USER->sesskey,
                    ]
                ),
                $OUTPUT->pix_icon('add', get_string('addafter', 'mod_icontent'), 'mod_icontent'),
                [
                        'title' => get_string('addafter', 'mod_icontent'),
                    ]
            );
            $toc .= html_writer::end_tag('div'); // End </div>.
            $toc .= html_writer::end_tag('li'); // End </li>.
        }
        $toc .= html_writer::end_tag('ul');
    } else {
        // Visualization to students.
        $toc .= html_writer::start_tag('ul');
        foreach ($pages as $pg) {
            if (!$pg->hidden) {
                $title = trim(format_string($pg->title, true, ['context' => $context]));
                $toc .= html_writer::start_tag('li', ['class' => 'clearfix']);
                $toc .= html_writer::link(
                    new moodle_url('/mod/icontent/view.php', ['id' => $pg->cmid, 'pageid' => $pg->id]),
                    $title,
                    [
                        'title' => s($title),
                        'class' => 'load-page page' . $pg->pagenum,
                        'data-pageid' => $pg->id,
                        'data-pagenum' => $pg->pagenum,
                        'data-cmid' => $pg->cmid,
                        'data-sesskey' => sesskey(),
                        'data-totalpages' => $tpages,
                    ]
                );
                $toc .= html_writer::end_tag('li');
            }
        }
        $toc .= html_writer::end_tag('ul');
    }
    $toc .= html_writer::end_tag('div');
    return $toc;
}

/**
 * Add dynamic attributes in page loading screen.
 * @param object $pagestyle
 * @return void
 */
function icontent_add_properties_css($pagestyle) {
    $bgcolor = icontent_normalize_hex_colour($pagestyle->bgcolor, 'FCFCFC');
    $bordercolor = icontent_normalize_hex_colour($pagestyle->bordercolor, 'E4E4E4');

    $style = "background-color: #{$bgcolor}; ";
    $style .= "min-height: " . ICONTENT_PAGE_MIN_HEIGHT . "px; ";
    $style .= "border: {$pagestyle->borderwidth}px solid #{$bordercolor};";
    if ($pagestyle->bgimage) {
        $style .= "background-image: url('{$pagestyle->bgimage}')";
    }
    return $style;
}

/**
 * Add script that load tooltip twitter bootstrap.
 *
 * @return void
 */
function icontent_add_script_load_tooltip() {
    $js = "
(function() {
    function getGroupFromNode(node) {
        if (!node || !node.className) {
            return null;
        }
        var match = String(node.className).match(/group(\\d+)/);
        return match ? match[1] : null;
    }

    function normalizeDdwtosQuestion(questionNode) {
        if (!questionNode || questionNode.offsetParent === null) {
            return;
        }

        var dragDropItems = questionNode.querySelectorAll('span.draghome[class*=group], span.drop[class*=group], input.placeinput[class*=group]');
        if (!dragDropItems.length) {
            return;
        }

        dragDropItems.forEach(function(itemNode) {
            if (itemNode.style) {
                itemNode.style.width = '';
                itemNode.style.height = '';
                itemNode.style.lineHeight = '';
            }
        });

        var groups = {};
        dragDropItems.forEach(function(itemNode) {
            var group = getGroupFromNode(itemNode);
            if (group) {
                groups[group] = true;
            }
        });

        Object.keys(groups).forEach(function(group) {
            var items = questionNode.querySelectorAll('span.group' + group + ', input.group' + group);
            if (!items.length) {
                return;
            }

            var maxWidth = 0;
            var maxHeight = 0;
            items.forEach(function(itemNode) {
                maxWidth = Math.max(maxWidth, Math.ceil(itemNode.offsetWidth || 0));
                maxHeight = Math.max(maxHeight, Math.ceil(itemNode.offsetHeight || 0));
            });

            if (maxWidth <= 0 || maxHeight <= 0) {
                return;
            }

            maxWidth += 8;
            maxHeight += 2;
            items.forEach(function(itemNode) {
                if (!itemNode.style) {
                    return;
                }
                itemNode.style.width = maxWidth + 'px';
                itemNode.style.height = maxHeight + 'px';
                itemNode.style.lineHeight = maxHeight + 'px';
            });
        });
    }

    function normalizeAllDdwtos() {
        var questionNodes = document.querySelectorAll('.fulltextpage .que.ddwtos');
        questionNodes.forEach(function(questionNode) {
            normalizeDdwtosQuestion(questionNode);
        });
    }

    function bindDdwtosImageLoads() {
        var imageNodes = document.querySelectorAll('.fulltextpage .que.ddwtos img');
        imageNodes.forEach(function(imageNode) {
            if (imageNode.getAttribute('data-icontent-ddwtos-bound') === '1') {
                return;
            }
            imageNode.setAttribute('data-icontent-ddwtos-bound', '1');
            imageNode.addEventListener('load', function() {
                normalizeAllDdwtos();
            });
        });
    }

    function isTinyToolbarReady(textareaNode) {
        if (!textareaNode || !textareaNode.id) {
            return true;
        }

        var iframeNode = document.getElementById(textareaNode.id + '_ifr');
        if (!iframeNode) {
            return false;
        }

        var editorNode = iframeNode.closest('.tox.tox-tinymce');
        if (!editorNode) {
            return false;
        }

        var toolbarButtons = editorNode.querySelectorAll('.tox-toolbar button, .tox-toolbar__group button');
        return toolbarButtons.length > 0;
    }

    function ensureTinyEssayEditorsReady() {
        if (typeof require !== 'function') {
            return;
        }

        var textareaNodes = document.querySelectorAll('.fulltextpage textarea.qtype_essay_response, .fulltextpage .qtype_essay_response textarea');
        if (!textareaNodes.length) {
            return;
        }

        require(['editor_tiny/editor'], function(tinyEditor) {
            if (!tinyEditor || typeof tinyEditor.setupForElementId !== 'function') {
                return;
            }

            var attempts = 0;
            var maxattempts = 12;

            var tryInit = function() {
                var pending = 0;

                textareaNodes.forEach(function(textareaNode) {
                    if (!textareaNode || !textareaNode.id) {
                        return;
                    }

                    if (isTinyToolbarReady(textareaNode)) {
                        return;
                    }

                    pending++;
                    try {
                        tinyEditor.setupForElementId({
                            elementId: textareaNode.id,
                            options: {}
                        });
                    } catch (error) {
                        // Ignore and retry while the question HTML finishes rendering.
                    }
                });

                if (pending > 0 && attempts < maxattempts) {
                    attempts++;
                    setTimeout(tryInit, 200);
                }
            };

            tryInit();
        });
    }

    function scheduleNormalizePasses() {
        normalizeAllDdwtos();
        bindDdwtosImageLoads();
        ensureTinyEssayEditorsReady();
        setTimeout(normalizeAllDdwtos, 120);
        setTimeout(normalizeAllDdwtos, 400);
        setTimeout(ensureTinyEssayEditorsReady, 120);
        setTimeout(ensureTinyEssayEditorsReady, 400);
    }

    if (!window.__icontentDdwtosResizeHooked) {
        window.__icontentDdwtosResizeHooked = true;

        document.addEventListener('click', function(event) {
            var target = event.target;
            if (!target || !target.closest) {
                return;
            }

            if (target.closest('.load-page') ||
                target.closest('.btn-previous-page') ||
                target.closest('.btn-next-page') ||
                target.closest('#idtitlequestionsarea')) {
                setTimeout(scheduleNormalizePasses, 650);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleNormalizePasses);
    } else {
        scheduleNormalizePasses();
    }
})();
";

    return html_writer::script($js);
}

/**
 * Get page style.
 *
 * This method checks if the current page have enough attributes to create your style. Otherwise returns Generic style plugin.
 *
 * @param object $icontent
 * @param object $page
 * @param object $context
 * @return pagestyle;
 */
function icontent_get_page_style($icontent, $page, $context) {
    $pagestyle = new stdClass();
    $pagestyle->bgcolor = $page->bgcolor ? $page->bgcolor : $icontent->bgcolor;
    $pagestyle->borderwidth = $page->borderwidth ? $page->borderwidth : $icontent->borderwidth;
    $pagestyle->bordercolor = $page->bordercolor ? $page->bordercolor : $icontent->bordercolor;
    $pagestyle->bgimage = false;
    if ($page->showbgimage) {
        $pagestyle->bgimage = icontent_get_page_bgimage($context, $page) ?
            icontent_get_page_bgimage($context, $page) : icontent_get_bgimage($context);
    }
    return icontent_add_properties_css($pagestyle);
}

/**
 * Add border options.
 *
 * @return array $arr
 */
function icontent_add_borderwidth_options() {
    $arr = [];
    // 20240216 Changed from 50 to 51.
    for ($i = 0; $i < 51; $i++) {
        $arr[$i] = $i . 'px';
    }
    return $arr;
}

/**
 * Get background image of interactive content plugin <iContent>.
 *
 * @param object $context
 * @return string $fullpath
 */
function icontent_get_bgimage($context) {
    $fs = get_file_storage();
    // This is not very efficient!!
    $files = $fs->get_area_files(
        $context->id,
        'mod_icontent',
        'icontent',
        0,
        'sortorder DESC,
        id ASC',
        false
    );
    if (count($files) >= 1) {
        $file = reset($files);
        unset($files);
        $fullurl = moodle_url::make_pluginfile_url(
            (int)$context->id,
            'mod_icontent',
            'icontent',
            0,
            (string)$file->get_filepath(),
            (string)$file->get_filename()
        )->out(false);
        $mimetype = $file->get_mimetype();
        if (file_mimetype_in_typegroup($mimetype, 'web_image')) { // It's an image.
            return $fullurl;
        } else {
            return false;
        }
    }
    return false;
}

/**
 * Get background image of pages of interactive content plugin <iContent>.
 *
 * @param object $context
 * @param object $page
 * @return string $fullpath
 */
function icontent_get_page_bgimage($context, $page) {
    $fs = get_file_storage();
    // This is not very efficient!
    $files = $fs->get_area_files(
        $context->id,
        'mod_icontent',
        'bgpage',
        $page->id,
        'sortorder DESC,
        id ASC',
        false
    );
    if (count($files) >= 1) {
        $file = reset($files);
        unset($files);
        $fullurl = moodle_url::make_pluginfile_url(
            (int)$context->id,
            'mod_icontent',
            'bgpage',
            (int)$page->id,
            (string)$file->get_filepath(),
            (string)$file->get_filename()
        )->out(false);
        $mimetype = $file->get_mimetype();
        if (file_mimetype_in_typegroup($mimetype, 'web_image')) { // It's an image.
            return $fullurl;
        } else {
            return false;
        }
    }
    return false;
}

/**
 * Delete question per page by id.
 *
 * Returns true or false
 *
 * @param int $id
 * @return boolean $result
 */
function icontent_remove_questionpagebyid($id) {
    global $DB;
    return $DB->delete_records('icontent_pages_questions', ['id' => $id]);
}

/**
 * Update question attempt.
 *
 * Returns true or false.
 *
 * @param object $attempt
 * @return boolean true or false
 */
function icontent_update_question_attempts($attempt) {
    global $DB;
    return $DB->update_record('icontent_question_attempts', $attempt);
}

/**
 * Loads full paging button bar.
 *
 * Returns buttons related pages.
 *
 * @param object $pages
 * @param object $cmid
 * @param int $startwithpage
 * @return string with $pgbuttons
 */
function icontent_full_paging_button_bar($pages, $cmid, $startwithpage = 1) {
    if (empty($pages)) {
        return false;
    }
    // Object button.
    $objbutton = new stdClass();
    $objbutton->name = get_string('previous', 'mod_icontent');
    $objbutton->title = get_string('previouspage', 'mod_icontent');
    $objbutton->cmid = $cmid;
    $objbutton->startwithpage = $startwithpage;
    // Create buttons!
    $npage = 0;
    $tpages = count($pages);
    $pgbuttons = html_writer::start_div('full-paging-buttonbar icontent-buttonbar', ['id' => 'idicontentbuttonbar']);
    $pgbuttons .= icontent_make_button_previous_page($objbutton, $tpages);
    foreach ($pages as $page) {
        if (!$page->hidden) {
            $npage++;
            $pgbuttons .= html_writer::link(
                new moodle_url('/mod/icontent/view.php', ['id' => $page->cmid, 'pageid' => $page->id]),
                $npage,
                [
                    'title' => s($page->title),
                    'class' => 'load-page mr-1 btn-icontent-page btn btn-secondary page' . $page->pagenum,
                    'data-toggle' => 'tooltip',
                    'data-totalpages' => $tpages,
                    'data-placement' => 'top',
                    'data-pageid' => $page->id,
                    'data-pagenum' => $page->pagenum,
                    'data-cmid' => $page->cmid,
                    'data-sesskey' => sesskey(),
                ]
            );
        }
    }
    $objbutton->name = get_string('next', 'mod_icontent');
    $objbutton->title = get_string('nextpage', 'mod_icontent');
    $pgbuttons .= icontent_make_button_next_page($objbutton, $tpages);
    $pgbuttons .= html_writer::end_div();
    return $pgbuttons;
}

/**
 * Loads simple paging button bar.
 *
 * Returns buttons previous and next.
 *
 * @param object $pages
 * @param int $cmid
 * @param int $startwithpage
 * @param string $attrid
 * @return string with $controlbuttons
 */
function icontent_simple_paging_button_bar($pages, $cmid, $startwithpage = 1, $attrid = 'fgroup_id_buttonar') {
    // Object button.
    $objbutton = new stdClass();
    $objbutton->name  = get_string('previous', 'mod_icontent');
    $objbutton->title = get_string('previouspage', 'mod_icontent');
    $objbutton->cmid  = $cmid;
    $objbutton->startwithpage = $startwithpage;
    // Go back.
    $controlbuttons = icontent_make_button_previous_page(
        $objbutton,
        count($pages),
        html_writer::tag(
            'i',
            null,
            [
                'class' => 'fa fa-chevron-circle-left mr-2',
            ]
        )
    );
    $objbutton->name = get_string('advance', 'mod_icontent');
    $objbutton->title = get_string('nextpage', 'mod_icontent');
    // Advance.
    $controlbuttons .= icontent_make_button_next_page(
        $objbutton,
        count($pages),
        html_writer::tag(
            'i',
            null,
            [
                'class' => 'fa fa-chevron-circle-right ml-2',
            ]
        )
    );
    return html_writer::div($controlbuttons, "simple-paging-buttonbar icontent-buttonbar mt-2", ['id' => $attrid]);
}

/**
 * Get the number of the user home page logged in.
 *
 * Returns array of pages.
 * Please note the icontent/text of pages is not included.
 *
 * @param object $icontent
 * @param object $context
 * @return array of id=>icontent
 */
function icontent_get_startpagenum($icontent, $context) {
    global $DB;
    if (has_any_capability(['mod/icontent:edit', 'mod/icontent:manage'], $context)) {
        return icontent_get_minpagenum($icontent);
    }
    // Discover page to be presented to the student.
    global $USER;
    $cm = get_coursemodule_from_instance('icontent', $icontent->id);
    $pagedisplay = $DB->get_record_sql(
        "SELECT MAX(timecreated) AS maxtimecreated
           FROM {icontent_pages_displayed}
          WHERE cmid IN(?)
            AND userid IN(?);",
        [
            $cm->id,
            $USER->id,
        ]
    );
    $totalpagesvieweduser = $DB->count_records('icontent_pages_displayed', ['cmid' => $cm->id, 'userid' => $USER->id]);
    $totalpagesavailable = $DB->count_records('icontent_pages', ['cmid' => $cm->id, 'hidden' => 0]);
    if (!$pagedisplay->maxtimecreated || $totalpagesvieweduser === $totalpagesavailable) {
        return icontent_get_minpagenum($icontent);
    }
    $lastpagedisplay = $DB->get_record(
        "icontent_pages_displayed",
        [
            'cmid' => $cm->id,
            'userid' => $USER->id,
            'timecreated' => $pagedisplay->maxtimecreated,
        ],
        'id,
        pageid'
    );
    $page = $DB->get_record("icontent_pages", ['id' => $lastpagedisplay->pageid], "id, pagenum");
    return $page->pagenum;
}

/**
 * Loads first page content.
 *
 * Returns array of pages.
 * Please note the icontent/text of pages is not included.
 *
 * @param object $icontent
 * @return array of id=>icontent
 */
function icontent_get_minpagenum($icontent) {
    global $DB;
    // Get object.
    $sql = "SELECT Min(pagenum) AS minpagenum FROM {icontent_pages} WHERE icontentid = ? AND hidden = ?;";
     $objpage = $DB->get_record_sql($sql, [$icontent->id, 0]);
     // Return min page.
    return $objpage->minpagenum;
}

/**
 * Get page previous.
 *
 * Return int  page previous.
 *
 * @param stdClass $objpage
 * @return int $page
 */
function icontent_get_prev_pagenum(stdClass $objpage) {
    global $DB;
    // Get page previous.
    $maxpagenum = $objpage->pagenum - 1;
    $page = $DB->get_record_sql(
        "SELECT max(pagenum) AS previous
           FROM {icontent_pages}
          WHERE cmid = ?
            AND hidden = ?
            AND pagenum BETWEEN ? and ?;",
        [
            $objpage->cmid,
            0,
            0,
            $maxpagenum,
        ]
    );
    return $page->previous;
}

/**
 * Get next page.
 *
 * Return int next page
 *
 * @param stdClass $objpage
 * @return int $next
 */
function icontent_get_next_pagenum(stdClass $objpage) {
    global $DB;
    // Get max valid pagenum.
    $pagenum = $DB->get_record_sql(
        "SELECT max(pagenum) AS max
           FROM {icontent_pages}
          WHERE cmid = ?
            AND hidden = ?;",
        [
            $objpage->cmid,
            0,
        ]
    );
    // Get next page.
    $minpagenum = $objpage->pagenum + 1;
    $page = $DB->get_record_sql(
        "SELECT min(pagenum) AS next
           FROM {icontent_pages}
          WHERE cmid = ?
            AND hidden = ?
            AND pagenum BETWEEN ? and ?;",
        [
            $objpage->cmid,
            0,
            $minpagenum,
            $pagenum->max,
        ]
    );
    return $page->next;
}

/**
 * Get page id by page number.
 *
 * @param int $cmid
 * @param int $pagenum
 * @return int|null
 */
function icontent_get_pageid_by_pagenum($cmid, $pagenum) {
    global $DB;

    if (empty($pagenum)) {
        return null;
    }

    return $DB->get_field('icontent_pages', 'id', ['cmid' => $cmid, 'pagenum' => $pagenum, 'hidden' => 0]) ?: null;
}

/**
 * Set updates for grades in table {grade_grades}.
 *
 * Returns true or false.
 *
 * @param stdClass $icontent
 * @param int $cmid
 * @param object $userid
 * @return boolean $return
 */
function icontent_set_grade_item(stdClass $icontent, $cmid, $userid) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');
    $params = [
        'itemname' => $icontent->name,
        'idnumber' => $cmid,
    ];
    $sumfraction = icontent_get_sumfraction_by_userid($cmid, $userid);
    $totalmaxfraction = icontent_get_totalmaxfraction_by_instance($cmid);
    if ($totalmaxfraction <= 0) {
        $totalmaxfraction = (float) icontent_get_totalquestions_by_instance($cmid);
    }
    $finalgrade = $totalmaxfraction > 0 ? ($sumfraction * $icontent->grade) / $totalmaxfraction : 0;
    // Make set icontent_grade for <iContent>.
    $igrade = new stdClass();
    $igrade->icontentid = $icontent->id;
    $igrade->userid = $userid;
    $igrade->cmid = $cmid;
    $igrade->grade = $finalgrade;
    $igrade->timemodified = time();
    // Check if table {icontent_grades} has grade for user.
    $igradeid = $DB->get_field(
        'icontent_grades',
        'id',
        [
            'icontentid' => $icontent->id,
            'userid' => $userid,
            'cmid' => $cmid,
        ]
    );
    if ($igradeid) {
        $igrade->id = $igradeid;
        $DB->update_record('icontent_grades', $igrade);
    } else {
        $DB->insert_record('icontent_grades', $igrade);
    }
    // Make grade.
    $grade = new stdClass();
    $grade->rawgrade = number_format($finalgrade, 5);
    $grade->userid = $userid;
    // Update gradebook.
    grade_update('mod/icontent', $icontent->course, 'mod', 'icontent', $icontent->id, 0, $grade, $params);
}

/**
 * Get total questions of question bank.
 *
 * Returns int of total questions.
 *
 * @param object $coursecontext
 * @return int of $tquestions
 */
function icontent_count_questions_of_questionbank($coursecontext) {
    global $DB;
    // 20240106 This seems to be working!
    $questions = $DB->get_record_sql(
        'SELECT count(*) as total
           FROM {question_bank_entries} qbe
           JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
          WHERE qc.contextid = ?',
        [$coursecontext]
    );
    return (int) $questions->total;
}

/**
 * Get total attempts users of users by course modules ID.
 *
 * Returns int of total attempts users.
 *
 * @param object $cmid
 * @param int $groupid
 * @return int of $tattemptsusers
 */
function icontent_count_attempts_users($cmid, $groupid = 0) {
    global $DB;

    $sql = "SELECT Count(DISTINCT u.id) AS totalattemptsusers
              FROM {user} u
        INNER JOIN {icontent_question_attempts} qa
                ON u.id = qa.userid
                         WHERE  qa.cmid = ?";
        $params = [$cmid];
    if (!empty($groupid)) {
            $sql .= "
                             AND EXISTS (
                                        SELECT 1
                                            FROM {groups_members} gm
                                         WHERE gm.userid = u.id
                                             AND gm.groupid = ?
                             )";
            $params[] = $groupid;
    }
        $totalattemptsusers = $DB->get_record_sql($sql, $params);
    return (int) $totalattemptsusers->totalattemptsusers;
}

/**
 * Get total attempts users of users with answers not evaluated by course modules ID.
 *
 * Returns int of total attempts users.
 *
 * @param object $cmid
 * @param null $status
 * @param int $groupid
 * @return int of $tattemptsusers
 */
function icontent_count_attempts_users_with_open_answers($cmid, $status = null, $groupid = 0) {
    global $DB;
    // Check if status is filled in.
    if (!isset($status)) {
        $status = ICONTENT_QTYPE_ESSAY_STATUS_TOEVALUATE;
    }
    // SQL Query.
        $sql = "SELECT Count(DISTINCT u.id) AS totalattemptsusers
              FROM {user} u
        INNER JOIN {icontent_question_attempts} qa
                ON u.id = qa.userid
             WHERE  qa.cmid = ?
                             AND (
                                        qa.rightanswer IN (?)
                        OR EXISTS (
                            SELECT 1
                                FROM {question} q
                             WHERE q.id = qa.questionid
                                 AND q.qtype = ?
                        )
                                        OR (
                                                qa.fraction = 0
                                                AND EXISTS (
                                                        SELECT 1
                                                            FROM {question} q
                                                            JOIN {qtype_poodllrecording_opts} qpo
                                                                ON qpo.questionid = q.id
                                                         WHERE q.id = qa.questionid
                                                             AND q.qtype = 'poodllrecording'
                                                             AND qpo.responseformat = 'picture'
                                                )
                                        )
                                                         )";
        $params = [$cmid, $status, ICONTENT_QTYPE_ESSAYAUTOGRADE];
    if (!empty($groupid)) {
            $sql .= "
                             AND EXISTS (
                                        SELECT 1
                                            FROM {groups_members} gm
                                         WHERE gm.userid = u.id
                                             AND gm.groupid = ?
                             )";
            $params[] = $groupid;
    }
        $totalattemptsusers = $DB->get_record_sql($sql, $params);
    return (int) $totalattemptsusers->totalattemptsusers;
}

/**
 * Get questions of current page.
 *
 * Returns array questionspage.
 *
 * @param int $pageid
 * @param int $cmid
 * @return array $questionspage
 */
function icontent_get_questions_of_currentpage($pageid, $cmid) {
    global $DB;
    return $DB->get_records('icontent_pages_questions', ['pageid' => $pageid, 'cmid' => $cmid], null, 'questionid, id');
}

/**
 * Get info answers by questionid.
 * Important: This function assumes that the naming patterns described in
 * <icontent_make_questions_answers_by_type> function were followed correctly.
 * Returns object infoanswer.
 *
 * @param int $questionid
 * @param int $qtype
 * @param string $answer
 * @return object $infoanswer
 */
function icontent_get_infoanswer_by_questionid($questionid, $qtype, $answer) {
    global $DB;
    // Check if var $qtype equals match. If true get $answerid.
    if (substr($qtype, 0, 5) === ICONTENT_QTYPE_MATCH) {
        [$strvar, $optionid] = explode('-', $qtype);
        $qtype = ICONTENT_QTYPE_MATCH;
    }
    // Creating and initializing the $infoanswer object.
    $infoanswer = new stdClass();
    $infoanswer->fraction = 0;
    $infoanswer->rightanswer = '';
    $infoanswer->answertext = '';
    // Set information by qtype.
    switch ($qtype) {
        case ICONTENT_QTYPE_MULTICHOICE:
        case ICONTENT_QTYPE_TRUEFALSE:
            // Check if answer is a checkbox. Otherwise, is radio.
            if (is_array($answer)) {
                $rightanswers = $DB->get_records_select('question_answers', 'question = ? AND fraction > ?', [$questionid, 0]);
                if (count($answer) === count($rightanswers)) {
                    // Get array with key ID answer.
                    $arrayoptionsids = icontent_get_array_options_answerid($answer);
                    // Checks answers correct.
                    foreach ($rightanswers as $rightanswer) {
                        $infoanswer->rightanswer .= $rightanswer->answer . ';';
                        if (array_key_exists($rightanswer->id, $arrayoptionsids)) {
                            $infoanswer->fraction += $rightanswer->fraction;
                            $infoanswer->answertext .= $rightanswer->answer . ';';
                        }
                    }
                    // Checks wrong answers.
                    if ($infoanswer->fraction < ICONTENT_QUESTION_FRACTION) {
                        $wronganswers = $DB->get_records_select(
                            'question_answers',
                            'question = ? AND fraction = ?',
                            [
                                $questionid,
                                0,
                            ]
                        );
                        foreach ($wronganswers as $wronganswer) {
                            if (array_key_exists($wronganswer->id, $arrayoptionsids)) {
                                $infoanswer->answertext .= $wronganswer->answer . ';';
                            }
                        }
                    }
                    return $infoanswer;
                }
                return false;
            } else {
                // Get data answer. Pattern e.g. [qpid-8_answerid-2].
                [$qp, $dtanswer] = explode('_', $answer);
                [$stranswer, $answerid] = explode('-', $dtanswer);
                $currentanwser = $DB->get_record_select(
                    'question_answers',
                    'question = ? AND id = ?',
                    [
                        $questionid,
                        $answerid,
                    ]
                );
                $infoanswer->fraction = $currentanwser->fraction;
                $infoanswer->rightanswer = $currentanwser->answer;
                $infoanswer->answertext = $currentanwser->answer;

                if ($infoanswer->fraction < ICONTENT_QUESTION_FRACTION) {
                    $rightanwser = $DB->get_record_select(
                        'question_answers',
                        'question = ? AND fraction = ?',
                        [
                            $questionid,
                            ICONTENT_QUESTION_FRACTION,
                        ]
                    );
                    $infoanswer->rightanswer = $rightanwser->answer;
                }
                return $infoanswer;
            }
            break;
        case ICONTENT_QTYPE_MATCH:
            $rightanwser = $DB->get_record('qtype_match_subquestions', ['id' => $optionid]);
            // Clean answers.
            $currentanwser = trim(strip_tags($answer));
            $rightanwser->answertext = trim(strip_tags($rightanwser->answertext));
            // Fill object $infoanswer.
            $infoanswer->rightanswer = $rightanwser->answertext . '->' . $rightanwser->questiontext . ';';
            $infoanswer->answertext = $currentanwser . '->' . $rightanwser->questiontext . ';';
            // Checks if answer is correct.
            if ($rightanwser->answertext === $currentanwser) {
                $infoanswer->fraction = ICONTENT_QUESTION_FRACTION;
            }
            return $infoanswer;
            break;
        case ICONTENT_QTYPE_ESSAY:
            $infoanswer->rightanswer = ICONTENT_QTYPE_ESSAY_STATUS_TOEVALUATE;    // Wait evaluation of tutor.
            $infoanswer->answertext = s($answer);
            return $infoanswer;
            break;
    }
    throw new Exception("QTYPE Invalid.");
}

/**
 * Get object with attempts of users by course modules ID <iContent>.
 *
 * Returns object attempt users.
 *
 * @param int $cmid
 * @param string $sort
 * @param int $page
 * @param int $perpage
 * @param int $groupid
 * @return object $attemptusers, otherwhise false.
 */
function icontent_get_attempts_users($cmid, $sort, $page = 0, $perpage = ICONTENT_PER_PAGE, $groupid = 0) {
    global $CFG, $DB;
    $sortparams = 'u.lastname ' . $sort;
    $page = (int) $page;
    $perpage = (int) $perpage;
    // Setup pagination - when both $page and $perpage = 0, get all results.
    if ($page || $perpage) {
        if ($page < 0) {
            $page = 0;
        }
        if ($perpage > ICONTENT_MAX_PER_PAGE) {
            $perpage = ICONTENT_MAX_PER_PAGE;
        } else if ($perpage < 1) {
            $perpage = ICONTENT_PER_PAGE;
        }
    }

    // 20231225 Added Moodle branch check.
    if ($CFG->branch < 311) {
        $namefields = user_picture::fields('u', null, 'userid');
    } else {
        $userfieldsapi = \core_user\fields::for_userpic();
        $namefields = $userfieldsapi->get_sql('u', false, '', 'id', false)->selects;
    }

        $sql = "SELECT DISTINCT $namefields,
                                     (SELECT Sum(fraction)
                                            FROM {icontent_question_attempts}
                                         WHERE userid = u.id
                                             AND cmid = ?) AS sumfraction,
                         (SELECT Sum(COALESCE(NULLIF(pq2.maxmark, 0), q2.defaultmark, 1))
                            FROM {icontent_question_attempts} qa2
                          INNER JOIN {icontent_pages_questions} pq2
                              ON qa2.pagesquestionsid = pq2.id
                          INNER JOIN {question} q2
                              ON qa2.questionid = q2.id
                           WHERE qa2.userid = u.id
                             AND qa2.cmid = ?) AS maxfraction,
                                     (SELECT Count(id)
                                            FROM {icontent_question_attempts}
                                         WHERE userid = u.id
                                             AND cmid = ?) AS totalanswers,
                                     (SELECT Count(id)
                                            FROM {icontent_question_attempts} qa2
                                         WHERE userid = u.id
                                             AND cmid = ?
                                             AND (
                                                        qa2.rightanswer IN (?)
                                                        OR EXISTS (
                                                                SELECT 1
                                                                    FROM {question} q
                                                                 WHERE q.id = qa2.questionid
                                                                     AND q.qtype = ?
                                                        )
                                                        OR (
                                                                qa2.fraction = 0
                                                                AND EXISTS (
                                                                        SELECT 1
                                                                            FROM {question} q
                                                                            JOIN {qtype_poodllrecording_opts} qpo
                                                                                ON qpo.questionid = q.id
                                                                         WHERE q.id = qa2.questionid
                                                                             AND q.qtype = 'poodllrecording'
                                                                             AND qpo.responseformat = 'picture'
                                                                )
                                                        )
                                             )) AS totalopenanswers
                            FROM {user} u
                INNER JOIN {icontent_question_attempts} qa
                                ON u.id = qa.userid
                         WHERE qa.cmid = ?";
    $params = [
        $cmid,
        $cmid,
        $cmid,
        $cmid,
        ICONTENT_QTYPE_ESSAY_STATUS_TOEVALUATE,
        ICONTENT_QTYPE_ESSAYAUTOGRADE,
        $cmid,
    ]; // Field CMID used four times. Check (?).
    if (!empty($groupid)) {
        $sql .= "
              AND EXISTS (
                   SELECT 1
                     FROM {groups_members} gm
                    WHERE gm.userid = u.id
                      AND gm.groupid = ?
              )";
        $params[] = $groupid;
    }
    $sql .= "
         ORDER BY $sortparams";
    return $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
}

/**
 * Get object with attempts of users with answers not evaluated by course modules ID <iContent>.
 *
 * Returns object attempt users.
 *
 * @param int $cmid
 * @param string $sort
 * @param string $status
 * @param int $page
 * @param int $perpage
 * @param int $groupid
 * @return object $attemptusers, otherwhise false.
 */
function icontent_get_attempts_users_with_open_answers(
    $cmid,
    $sort,
    $status = null,
    $page = 0,
    $perpage = ICONTENT_PER_PAGE,
    $groupid = 0
) {
    global $CFG, $DB;
    $sortparams = 'u.firstname ' . $sort;
    $page = (int) $page;
    $perpage = (int) $perpage;
    // Check if status is filled in.
    if (!isset($status)) {
        $status = ICONTENT_QTYPE_ESSAY_STATUS_TOEVALUATE;
    }
    // Setup pagination - when both $page and $perpage = 0, get all results.
    if ($page || $perpage) {
        if ($page < 0) {
            $page = 0;
        }
        if ($perpage > ICONTENT_MAX_PER_PAGE) {
            $perpage = ICONTENT_MAX_PER_PAGE;
        } else if ($perpage < 1) {
            $perpage = ICONTENT_PER_PAGE;
        }
    }

    // 20231225 Added Moodle branch check.
    if ($CFG->branch < 311) {
        $namefields = user_picture::fields('u', null, 'userid');
    } else {
        $userfieldsapi = \core_user\fields::for_userpic();
        $namefields = $userfieldsapi->get_sql('u', false, '', 'id', false)->selects;
        ;
    }

        $sql = "SELECT DISTINCT $namefields,
                (SELECT Count(id)
                                     FROM {icontent_question_attempts} qa2
                  WHERE userid = u.id
                    AND cmid = ?
                                        AND (
                                                qa2.rightanswer IN (?)
                            OR EXISTS (
                                SELECT 1
                                    FROM {question} q
                                 WHERE q.id = qa2.questionid
                                     AND q.qtype = ?
                            )
                                                OR (
                                                        qa2.fraction = 0
                                                        AND EXISTS (
                                                                SELECT 1
                                                                    FROM {question} q
                                                                    JOIN {qtype_poodllrecording_opts} qpo
                                                                        ON qpo.questionid = q.id
                                                                 WHERE q.id = qa2.questionid
                                                                     AND q.qtype = 'poodllrecording'
                                                                     AND qpo.responseformat = 'picture'
                                                        )
                                                )
                                        )) AS totalopenanswers
             FROM {user} u
       INNER JOIN {icontent_question_attempts} qa
               ON u.id = qa.userid
            WHERE qa.cmid = ?
                            AND (
                                        qa.rightanswer IN (?)
                            OR EXISTS (
                                SELECT 1
                                    FROM {question} q
                                 WHERE q.id = qa.questionid
                                     AND q.qtype = ?
                            )
                                        OR (
                                                qa.fraction = 0
                                                AND EXISTS (
                                                        SELECT 1
                                                            FROM {question} q
                                                            JOIN {qtype_poodllrecording_opts} qpo
                                                                ON qpo.questionid = q.id
                                                         WHERE q.id = qa.questionid
                                                             AND q.qtype = 'poodllrecording'
                                                             AND qpo.responseformat = 'picture'
                                                )
                                        )
                            )";
    $params = [
        $cmid,
        $status,
        ICONTENT_QTYPE_ESSAYAUTOGRADE,
        $cmid,
        $status,
        ICONTENT_QTYPE_ESSAYAUTOGRADE,
    ]; // Field CMID used two times. Check (?).
    if (!empty($groupid)) {
        $sql .= "
              AND EXISTS (
                   SELECT 1
                     FROM {groups_members} gm
                    WHERE gm.userid = u.id
                      AND gm.groupid = ?
              )";
        $params[] = $groupid;
    }
    $sql .= "
         ORDER BY {$sortparams}";
    return $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
}

/**
 * Get object with attempt summary of user the current page.
 *
 * Returns object attempt summary.
 *
 * @param int $pageid
 * @param int $cmid
 * @return object $attemptsummary, otherwhise false.
 */
function icontent_get_attempt_summary_by_page($pageid, $cmid) {
    global $DB, $USER;

    $latestattempttime = icontent_get_latest_attempt_timecreated_by_page($pageid, $cmid, $USER->id);
    if (empty($latestattempttime)) {
        return false;
    }

        $sql = "SELECT Sum(qa.fraction) AS sumfraction,
               Sum(COALESCE(NULLIF(pq.maxmark, 0), q.defaultmark, 1)) AS maxfraction,
                   Count(qa.id) AS totalanswers,
                   qa.timecreated
              FROM {icontent_question_attempts} qa
        INNER JOIN {icontent_pages_questions} pq
                ON qa.pagesquestionsid = pq.id
        INNER JOIN {question} q
            ON qa.questionid = q.id
             WHERE pq.pageid = ?
               AND pq.cmid = ?
               AND qa.userid = ?
               AND qa.timecreated = ?
          GROUP BY qa.timecreated";
    $attemptsummary = $DB->get_record_sql($sql, [$pageid, $cmid, $USER->id, $latestattempttime]);
    // Checks if a property isn't empty.
    if (!empty($attemptsummary->totalanswers)) {
        return $attemptsummary;
    }
    return false;
}

/**
 * Get object with right answers by attempt summary the current page.
 *
 * Returns object total right answers by attempt summary.
 *
 * @param int $pageid
 * @param int $cmid
 * @return object $rightanswers
 */
function icontent_get_right_answers_by_attempt_summary_by_page($pageid, $cmid) {
    global $DB, $USER;
    $latestattempttime = icontent_get_latest_attempt_timecreated_by_page($pageid, $cmid, $USER->id);
    if (empty($latestattempttime)) {
        return (object)['totalrightanswers' => 0];
    }

        $sql = "SELECT Sum(CASE
                               WHEN qa.rightanswer = ? THEN 1
                               WHEN qa.fraction >= COALESCE(NULLIF(pq.maxmark, 0), q.defaultmark, 1) THEN 1
                               ELSE 0
                           END) AS totalrightanswers,
                           Sum(CASE
                               WHEN qa.rightanswer = ? THEN 1
                               WHEN qa.rightanswer = ? THEN 0
                               ELSE GREATEST(
                                   LEAST(
                                       qa.fraction / COALESCE(NULLIF(pq.maxmark, 0), q.defaultmark, 1),
                                       1
                                   ),
                                   0
                               )
                           END) AS equivalentrightanswers
              FROM {icontent_question_attempts} qa
        INNER JOIN {icontent_pages_questions} pq
                ON qa.pagesquestionsid = pq.id
        INNER JOIN {question} q
            ON qa.questionid = q.id
                                 WHERE pq.pageid = ?
               AND pq.cmid = ?
               AND qa.userid = ?
               AND qa.timecreated = ?";
    return $DB->get_record_sql($sql, [
        ICONTENT_QTYPE_ESSAY_STATUS_VALUED,
        ICONTENT_QTYPE_ESSAY_STATUS_VALUED,
        ICONTENT_QTYPE_ESSAY_STATUS_TOEVALUATE,
        $pageid,
        $cmid,
        $USER->id,
        $latestattempttime,
    ]);
}

/**
 * Get object with open answers by attempt summary the current page.
 *
 * Returns object total open answers by attempt summary.
 *
 * @param int $pageid
 * @param int $cmid
 * @return object $openanswers
 */
function icontent_get_open_answers_by_attempt_summary_by_page($pageid, $cmid) {
    global $DB, $USER;
    $latestattempttime = icontent_get_latest_attempt_timecreated_by_page($pageid, $cmid, $USER->id);
    if (empty($latestattempttime)) {
        return (object)['totalopenanswers' => 0];
    }

        $sql = "SELECT Count(qa.id) AS totalopenanswers
              FROM {icontent_question_attempts} qa
        INNER JOIN {icontent_pages_questions} pq
                ON qa.pagesquestionsid = pq.id
             WHERE pq.pageid = ?
               AND pq.cmid = ?
               AND qa.userid = ?
                             AND (
                                        qa.rightanswer IN (?)
                                        OR (
                                                qa.fraction = 0
                                                AND EXISTS (
                                                        SELECT 1
                                                            FROM {question} q
                                                            JOIN {qtype_poodllrecording_opts} qpo
                                                                ON qpo.questionid = q.id
                                                         WHERE q.id = qa.questionid
                                                             AND q.qtype = 'poodllrecording'
                                                             AND qpo.responseformat = 'picture'
                                                )
                                        )
                             )
               AND qa.timecreated = ?";
    return $DB->get_record_sql($sql, [$pageid, $cmid, $USER->id, ICONTENT_QTYPE_ESSAY_STATUS_TOEVALUATE, $latestattempttime]);
}

/**
 * Get latest attempt timestamp by page, module and user.
 *
 * @param int $pageid
 * @param int $cmid
 * @param int $userid
 * @return int|null
 */
function icontent_get_latest_attempt_timecreated_by_page($pageid, $cmid, $userid) {
    global $DB;

    $sql = "SELECT MAX(qa.timecreated)
              FROM {icontent_question_attempts} qa
        INNER JOIN {icontent_pages_questions} pq
                ON qa.pagesquestionsid = pq.id
             WHERE pq.pageid = ?
               AND pq.cmid = ?
               AND qa.userid = ?";
    $latestattempttime = $DB->get_field_sql($sql, [$pageid, $cmid, $userid]);

    if ($latestattempttime === false || $latestattempttime === null) {
        return null;
    }

    return (int)$latestattempttime;
}

/**
 * Get object with questions and open answers by user the current page.
 *
 * Returns object questions and open answers by attempt summary.
 *
 * @param int $userid
 * @param int $cmid
 * @param string $status
 * @return object $qopenanswers
 */
function icontent_get_questions_and_open_answers_by_user($userid, $cmid, $status = null) {
    global $DB;
    // Check if status is filled in.
    if (!isset($status)) {
        $status = ICONTENT_QTYPE_ESSAY_STATUS_TOEVALUATE;
    }
    // SQL query.
    $sql = "SELECT qa.id,
                   qa.userid,
                   qa.questionid,
                   qa.pagesquestionsid,
                   qa.answertext,
                   qa.reviewercomment,
                   qa.reviewercommentformat,
                   qa.fraction,
                   qa.timecreated,
                   q.questiontext,
                   q.qtype,
                   qpo.responseformat,
                   pq.maxmark,
                   q.defaultmark,
                   pq.pageid
              FROM {icontent_question_attempts} qa
        INNER JOIN {question} q
                ON qa.questionid = q.id
        LEFT JOIN {qtype_poodllrecording_opts} qpo
               ON qpo.questionid = q.id
        INNER JOIN {icontent_pages_questions} pq
                ON qa.pagesquestionsid = pq.id
             WHERE qa.cmid = ?
               AND qa.userid = ?
               AND (
                    qa.rightanswer IN (?)
                    OR q.qtype = ?
                    OR (
                        qa.fraction = 0
                        AND q.qtype = 'poodllrecording'
                        AND qpo.responseformat = 'picture'
                    )
               );";
    // Get records and return.
    return $DB->get_records_sql($sql, [$cmid, $userid, $status, ICONTENT_QTYPE_ESSAYAUTOGRADE]);
}

/**
 * Render manual-review answer content for supported question types.
 *
 * @param stdClass $qopenanswer
 * @param int $cmid
 * @return string
 */
function icontent_render_manual_review_answer(stdClass $qopenanswer, int $cmid): string {
    $answertext = (string)($qopenanswer->answertext ?? '');
    $qtype = (string)($qopenanswer->qtype ?? '');
    $responseformat = (string)($qopenanswer->responseformat ?? '');

    if ($qtype === 'poodllrecording' && $responseformat === 'picture' && $answertext !== '') {
        $filename = icontent_extract_poodll_response_filename($answertext);
        $imagesrc = icontent_get_poodll_response_image_url($filename, (int)$qopenanswer->userid, $cmid);
        if (!empty($imagesrc)) {
            return html_writer::empty_tag('img', [
                'src' => $imagesrc,
                'alt' => s($filename),
                'class' => 'img-fluid icontent-manualreview-image',
                'style' => 'max-width: 100%; height: auto;',
            ]);
        }
    }

    return format_text($answertext, FORMAT_HTML, [
        'noclean' => false,
        'para' => false,
    ]);
}

/**
 * Extract a PoodLL drawing filename from stored response text.
 *
 * @param string $answertext
 * @return string
 */
function icontent_extract_poodll_response_filename(string $answertext): string {
    $answertext = trim(strip_tags($answertext));
    if ($answertext === '') {
        return '';
    }

    if (preg_match('/(upfile_drawingboard_[0-9]+\.(?:png|jpe?g|gif|webp))/i', $answertext, $matches)) {
        return $matches[1];
    }

    $path = parse_url($answertext, PHP_URL_PATH);
    if (!empty($path)) {
        $basename = basename($path);
        if (preg_match('/\.(?:png|jpe?g|gif|webp)$/i', $basename)) {
            return $basename;
        }
    }

    if (preg_match('/\.(?:png|jpe?g|gif|webp)$/i', $answertext)) {
        return $answertext;
    }

    return '';
}

/**
 * Locate metadata for a stored PoodLL sketch response image.
 *
 * @param string $filename
 * @param int $userid
 * @param int $cmid
 * @return stdClass|null
 */
function icontent_get_poodll_response_image_file_record(string $filename, int $userid, int $cmid): ?stdClass {
    global $DB;

    if ($filename === '') {
        return null;
    }

    $sql = "SELECT f.contextid,
                   f.itemid,
                   f.filepath,
                   f.filename,
                   f.mimetype
              FROM {files} f
              JOIN {context} c
                ON c.id = f.contextid
             WHERE f.component = 'question'
               AND f.filearea = 'response_answer'
               AND f.filename = ?
               AND f.userid = ?
               AND c.contextlevel = ?
               AND c.instanceid = ?
               AND f.filesize > 0
          ORDER BY f.timemodified DESC";
    $file = $DB->get_record_sql($sql, [$filename, $userid, CONTEXT_MODULE, $cmid], IGNORE_MULTIPLE);

    if (!$file) {
        $fallbacksql = "SELECT f.contextid,
                               f.itemid,
                               f.filepath,
                               f.filename,
                               f.mimetype
                          FROM {files} f
                          JOIN {context} c
                            ON c.id = f.contextid
                         WHERE f.component = 'question'
                           AND f.filearea = 'response_answer'
                           AND f.filename = ?
                           AND c.contextlevel = ?
                           AND c.instanceid = ?
                           AND f.filesize > 0
                      ORDER BY f.timemodified DESC";
        $file = $DB->get_record_sql($fallbacksql, [$filename, CONTEXT_MODULE, $cmid], IGNORE_MULTIPLE);
    }

    return $file ?: null;
}

/**
 * Locate a stored PoodLL sketch response image and return a pluginfile URL.
 *
 * @param string $filename
 * @param int $userid
 * @param int $cmid
 * @return string
 */
function icontent_get_poodll_response_image_url(string $filename, int $userid, int $cmid): string {
    global $CFG;

    require_once($CFG->libdir . '/filelib.php');
    require_once($CFG->libdir . '/filestorage/file_storage.php');

    $filename = icontent_extract_poodll_response_filename($filename);
    if ($filename === '') {
        return '';
    }

    $file = icontent_get_poodll_response_image_file_record($filename, $userid, $cmid);

    if (!$file) {
        return '';
    }

    $mimetype = (string)$file->mimetype;
    if ($mimetype === '' || strpos($mimetype, 'image/') !== 0) {
        return '';
    }

    $filestorage = get_file_storage();
    $storedfile = $filestorage->get_file(
        (int)$file->contextid,
        'question',
        'response_answer',
        (int)$file->itemid,
        (string)$file->filepath,
        (string)$file->filename
    );

    if ($storedfile) {
        $content = $storedfile->get_content();
        if ($content !== false && $content !== '') {
            return 'data:' . $mimetype . ';base64,' . base64_encode($content);
        }
    }

    return moodle_url::make_pluginfile_url(
        (int)$file->contextid,
        'question',
        'response_answer',
        (int)$file->itemid,
        (string)$file->filepath,
        (string)$file->filename
    )->out(false);
}

/**
 * Get latest submitted PoodLL sketch answers by page for current user.
 *
 * @param int $pageid
 * @param int $cmid
 * @return array
 */
function icontent_get_poodll_sketch_answers_by_attempt_summary_by_page($pageid, $cmid) {
    global $DB, $USER;

    $latestattempttime = icontent_get_latest_attempt_timecreated_by_page($pageid, $cmid, $USER->id);
    if (empty($latestattempttime)) {
        return [];
    }

    $sql = "SELECT qa.id,
                   qa.userid,
                   qa.answertext,
                   q.name AS questionname,
                   q.qtype,
                   qpo.responseformat
              FROM {icontent_question_attempts} qa
        INNER JOIN {icontent_pages_questions} pq
                ON qa.pagesquestionsid = pq.id
        INNER JOIN {question} q
                ON q.id = qa.questionid
         LEFT JOIN {qtype_poodllrecording_opts} qpo
                ON qpo.questionid = q.id
             WHERE pq.pageid = ?
               AND pq.cmid = ?
               AND qa.userid = ?
               AND qa.timecreated = ?
               AND q.qtype = 'poodllrecording'
               AND qpo.responseformat = 'picture'
               AND qa.answertext IS NOT NULL
               AND qa.answertext <> ''
          ORDER BY qa.id ASC";

    return $DB->get_records_sql($sql, [$pageid, $cmid, $USER->id, $latestattempttime]);
}

/**
 * Get latest submitted answers by page for current user.
 *
 * @param int $pageid
 * @param int $cmid
 * @return array
 */
function icontent_get_submitted_answers_by_attempt_summary_by_page($pageid, $cmid) {
    global $DB, $USER;

    $latestattempttime = icontent_get_latest_attempt_timecreated_by_page($pageid, $cmid, $USER->id);
    if (empty($latestattempttime)) {
        return [];
    }

    $sql = "SELECT qa.id,
                   qa.userid,
                   qa.answertext,
                   qa.questionid,
                   q.name AS questionname,
                   q.qtype,
                   qpo.responseformat
              FROM {icontent_question_attempts} qa
        INNER JOIN {icontent_pages_questions} pq
                ON qa.pagesquestionsid = pq.id
        INNER JOIN {question} q
                ON q.id = qa.questionid
         LEFT JOIN {qtype_poodllrecording_opts} qpo
                ON qpo.questionid = q.id
             WHERE pq.pageid = ?
               AND pq.cmid = ?
               AND qa.userid = ?
               AND qa.timecreated = ?
          ORDER BY qa.id ASC";

    return $DB->get_records_sql($sql, [$pageid, $cmid, $USER->id, $latestattempttime]);
}

/**
 * Get reviewer comments attached to the latest attempt summary on a page.
 *
 * @param int $pageid
 * @param int $cmid
 * @return array
 */
function icontent_get_reviewer_comments_by_attempt_summary_by_page($pageid, $cmid) {
    global $DB, $USER;

    $latestattempttime = icontent_get_latest_attempt_timecreated_by_page($pageid, $cmid, $USER->id);
    if (empty($latestattempttime)) {
        return [];
    }

    $sql = "SELECT qa.id,
                   qa.questionid,
                   qa.reviewercomment,
                   qa.reviewercommentformat,
                   q.name AS questionname
              FROM {icontent_question_attempts} qa
        INNER JOIN {icontent_pages_questions} pq
                ON qa.pagesquestionsid = pq.id
        INNER JOIN {question} q
                ON q.id = qa.questionid
             WHERE pq.pageid = ?
               AND pq.cmid = ?
               AND qa.userid = ?
               AND qa.timecreated = ?
               AND qa.reviewercomment IS NOT NULL
               AND qa.reviewercomment <> ''
          ORDER BY qa.id ASC";

    return $DB->get_records_sql($sql, [$pageid, $cmid, $USER->id, $latestattempttime]);
}

/**
 * Get sum fraction by instance and userid.
 *
 * Returns sum fraction.
 *
 * @param int $cmid
 * @param int $userid
 * @return float $sumfraction
 */
function icontent_get_sumfraction_by_userid($cmid, $userid) {
    global $DB;
    $sql = "SELECT Sum(fraction) AS sumfraction FROM {icontent_question_attempts}  WHERE  userid = ? AND cmid = ?;";
    $grade = $DB->get_record_sql($sql, [$userid, $cmid]);
    return $grade->sumfraction;
}

/**
 * Get array of the options of answers. Pattern input e.g. array options with [qpid-9_answerid-5].
 *
 * Returns array of $arrayoptionsid.
 *
 * @param array $answers
 * @return array $arrayoptionsid[$answerid] = $questionpage
 */
function icontent_get_array_options_answerid($answers) {
    $arrayoptionsids = [];
    foreach ($answers as $optanswer) {
        [$qp, $answer] = explode('_', $optanswer);
        [$stranswer, $answerid] = explode('-', $answer);
        $arrayoptionsids[$answerid] = $qp;
    }
    return $arrayoptionsids;
}

/**
 * Add preview in page if its not previewed.
 *
 * Returns object of pagedisplayed.
 *
 * @param int $pageid
 * @param int $cmid
 * @return object $pagedisplayed
 */
function icontent_add_pagedisplayed($pageid, $cmid) {
    global $DB, $USER;
    $pagedisplayed = icontent_get_pagedisplayed($pageid, $cmid);
    if (empty($pagedisplayed)) {
        $pagedisplayed = new stdClass();
        $pagedisplayed->pageid = $pageid;
        $pagedisplayed->cmid = $cmid;
        $pagedisplayed->userid = $USER->id;
        $pagedisplayed->timecreated = time();
        return $DB->insert_record('icontent_pages_displayed', $pagedisplayed);
    }
    return $pagedisplayed;
}

/**
 * Adds questions on a page.
 *
 * Returns true or false.
 *
 * @param array $questions
 * @param int $pageid
 * @param int $cmid
 * @return boolean true or false
 */
function icontent_add_questionpage($questions, $pageid, $cmid) {
    global $DB;
    if (empty($questions)) {
        return false;
    }

    $selectedquestionids = array_unique(array_map('intval', $questions));
    $selectedquestionids = array_values(array_filter($selectedquestionids));
    if (empty($selectedquestionids)) {
        return false;
    }

    $questionmarks = $DB->get_records_list('question', 'id', $selectedquestionids, '', 'id, defaultmark');

    $existing = $DB->get_records_menu(
        'icontent_pages_questions',
        ['pageid' => $pageid, 'cmid' => $cmid],
        '',
        'id, questionid'
    );
    $existingquestionids = array_map('intval', array_values($existing));

    $timecreated = time();
    $records = [];
    foreach ($selectedquestionids as $questionid) {
        if (in_array($questionid, $existingquestionids, true)) {
            continue;
        }

        $record = new stdClass();
        $record->pageid = $pageid;
        $record->questionid = $questionid;
        $record->cmid = $cmid;
        $defaultmark = isset($questionmarks[$questionid]) ? (float)$questionmarks[$questionid]->defaultmark : 0.0;
        $record->maxmark = $defaultmark > 0 ? $defaultmark : 1;
        $record->timecreated = $timecreated;
        $records[] = $record;
    }

    if (!empty($records)) {
        $DB->insert_records('icontent_pages_questions', $records);
    }

    return true;
}

/**
 * Get page viewed.
 *
 * Returns string of pagedisplayed.
 *
 * @param int $pageid
 * @param int $cmid
 * @return object $pagedisplayed
 */
function icontent_get_pagedisplayed($pageid, $cmid) {
    global $DB, $USER;
    return $DB->get_record(
        'icontent_pages_displayed',
        [
            'pageid' => $pageid,
            'cmid' => $cmid,
            'userid' => $USER->id,
        ],
        'id,
        timecreated'
    );
}

/**
 * Get questions by pageid.
 *
 * Returns array of questions.
 *
 * @param int $pageid
 * @param int $cmid
 * @return array $questions
 */
function icontent_get_pagequestions($pageid, $cmid) {
    global $DB;
    $sql = 'SELECT pq.id AS qpid,
                   q.id  AS qid,
                   q.name,
                   pq.maxmark,
                   q.defaultmark,
                   q.questiontext,
                   q.questiontextformat,
                   q.qtype
              FROM {icontent_pages_questions} pq
        INNER JOIN {question} q
                ON pq.questionid = q.id
             WHERE pq.pageid = ?
               AND pq.cmid = ?;';
    return $DB->get_records_sql($sql, [$pageid, $cmid]);
}

/**
 * Get total of questions and subquestions by instance <iContent>.
 *
 * Returns total of questions by instance.
 *
 * @param int $cmid
 * @return int $tquestions
 */
function icontent_get_totalquestions_by_instance($cmid) {
    global $DB;
    // Get total subquestions.
    $sql = 'SELECT Count(*)
              FROM {qtype_match_subquestions} qms
        INNER JOIN {icontent_pages_questions} pq
                ON qms.questionid = pq.questionid
             WHERE pq.cmid = ?;';
    $tquest = $DB->count_records_sql($sql, [$cmid]);
    // Get total questions.
    $sql = 'SELECT Count(*)
              FROM {icontent_pages_questions} pq
        INNER JOIN {question} q
                ON pq.questionid = q.id
             WHERE q.qtype NOT IN (?)
               AND pq.cmid = ?;';
    $tsub = $DB->count_records_sql($sql, [ICONTENT_QTYPE_MATCH, $cmid]);
    return $tsub + $tquest;
}

/**
 * Get total maximum points by instance <iContent>.
 *
 * @param int $cmid
 * @return float
 */
function icontent_get_totalmaxfraction_by_instance($cmid) {
    global $DB;

    $sql = "SELECT Sum(COALESCE(NULLIF(pq.maxmark, 0), q.defaultmark, 1))
              FROM {icontent_pages_questions} pq
        INNER JOIN {question} q
                ON pq.questionid = q.id
             WHERE pq.cmid = ?";

    $maxfraction = $DB->get_field_sql($sql, [$cmid]);
    if ($maxfraction === false || $maxfraction === null) {
        return 0.0;
    }

    return (float)$maxfraction;
}

/**
 * Get pagenotes by pageid according to the user's capability logged.
 *
 * Returns array of pagenotes.
 *
 * @param int $pageid
 * @param int $cmid
 * @param string $tab
 * @return object $pagenotes
 */
function icontent_get_pagenotes($pageid, $cmid, $tab) {
    global $DB, $USER;
    if (icontent_has_permission_manager(context_module::instance($cmid))) {
        // If manager: see everything.
        return $DB->get_records('icontent_pages_notes', ['pageid' => $pageid, 'cmid' => $cmid, 'tab' => $tab], 'path');
    }
    // Non-manager: own notes + public non-doubttutor notes from others.
    $sql = 'SELECT *
              FROM {icontent_pages_notes}
             WHERE pageid = ?
               AND cmid = ?
               AND tab = ?
               AND (userid = ? OR (private = ? AND doubttutor = ?))
          ORDER BY path ASC;';
    $notes = $DB->get_records_sql($sql, [$pageid, $cmid, $tab, $USER->id, 0, 0]);
    // Also include tutor replies in threads started by this user's own doubttutor
    // questions, so the student can read the tutor's response to their private question.
    $ownrootids = $DB->get_fieldset_select(
        'icontent_pages_notes',
        'id',
        'pageid = ? AND cmid = ? AND tab = ? AND userid = ? AND parent = 0 AND doubttutor = 1',
        [$pageid, $cmid, $tab, $USER->id]
    );
    foreach ($ownrootids as $rootid) {
        $likelead = $DB->sql_like('path', ':pathlike');
        $sql2 = "SELECT *
                   FROM {icontent_pages_notes}
                  WHERE userid != :userid
                    AND (path = :pathexact OR $likelead)";
        $replies = $DB->get_records_sql($sql2, [
            'userid'    => $USER->id,
            'pathexact' => '/' . $rootid,
            'pathlike'  => '/' . $rootid . '/%',
        ]);
        foreach ($replies as $reply) {
            $notes[$reply->id] = $reply;
        }
    }
    if (!empty($ownrootids)) {
        uasort($notes, function ($a, $b) {
            return strcmp($a->path, $b->path);
        });
    }
    return $notes;
}

/**
 * Get likes of page.
 *
 * Returns object of  {icontent_pages_notes_like}.
 *
 * @param int $pagenoteid
 * @param int $userid
 * @param int $cmid
 * @return object $pagenotelike
 */
function icontent_get_pagenotelike($pagenoteid, $userid, $cmid) {
    global $DB;
    return $DB->get_record('icontent_pages_notes_like', ['pagenoteid' => $pagenoteid, 'userid' => $userid, 'cmid' => $cmid], 'id');
}

/**
 * Check if expandnotesarea or expandquestionsarea field are true or false and returns toggle object.
 *
 * Returns toggle area object.
 *
 * @param boolean $expandarea
 * @return object $attrtogglearea
 */
function icontent_get_toggle_area_object($expandarea) {
    $attrtogglearea = new stdClass();
    if (!$expandarea) {
        $attrtogglearea->icon = '<i class="fa fa-caret-right" aria-hidden="true"></i>&nbsp;';
        $attrtogglearea->style = "display: none;";
        $attrtogglearea->class = "closed";
        return $attrtogglearea;
    }
    $attrtogglearea->icon = '<i class="fa fa-caret-down" aria-hidden="true"></i>&nbsp;';
    $attrtogglearea->style = '';
    $attrtogglearea->class = '';
    return  $attrtogglearea;
}

/**
 * Get pages number interactive content <iContent>
 *
 * Returns pagenum.
 *
 * @param int $icontentid
 * @return int pagenum
 */
function icontent_count_pages($icontentid) {
    global $DB;
    return $DB->count_records('icontent_pages', ['icontentid' => $icontentid]);
}

/**
 * Get pages number viewed by user.
 *
 * Returns page viewed by user.
 *
 * @param int $userid
 * @param int $cmid
 * @return int $pageviewedbyuser
 */
function icontent_count_pageviewedbyuser($userid, $cmid) {
    global $DB;
    return $DB->count_records('icontent_pages_displayed', ['userid' => $userid, 'cmid' => $cmid]);
}

/**
 * Get count of likes a note {icontent_pages_notes_like}.
 *
 * Returns count.
 *
 * @param int $pagenoteid
 * @return int count
 */
function icontent_count_pagenotelike($pagenoteid) {
    global $DB;
    return $DB->count_records('icontent_pages_notes_like', ['pagenoteid' => $pagenoteid]);
}

/**
 * Get page number by pageid.
 *
 * Returns pagenum.
 *
 * @param int $pageid
 * @return int pagenum
 */
function icontent_get_pagenum_by_pageid($pageid) {
    global $DB;
    $sql = "SELECT pagenum  FROM {icontent_pages} WHERE id = ?;";
    $obj = $DB->get_record_sql($sql, [$pageid], MUST_EXIST);
    return $obj->pagenum;
}

/**
 * Get the level of depth this note.
 *
 * Returns levels.
 *
 * @param string $path
 * @return int $levels
 */
function icontent_get_noteparentinglevels($path) {
    $countpath = count(explode('/', $path)) - 1;
    if (!$countpath) {
        return 1;
    } else if ($countpath > 12) {
        return 12;
    } else {
        return $countpath;
    }
}

/**
 * Get user by ID.
 *
 * Returns object $user.
 *
 * @param int $userid
 * @return object $user
 */
function icontent_get_user_by_id($userid) {
    global $DB;
    return $DB->get_record(
        'user',
        ['id' => $userid],
        'id,
        firstname,
        lastname,
        email,
        picture,
        firstnamephonetic,
        lastnamephonetic,
        middlename,
        alternatename,
        imagealt'
    );
}

/**
 * Recursive function that gets notes daughters.
 *
 * Returns array $notesdaughters.
 *
 * @param int $pagenoteid
 * @return array $notesdaughters
 */
function icontent_get_notes_daughters($pagenoteid) {
    global $DB;
    $pagenotes = $DB->get_records('icontent_pages_notes', ['parent' => $pagenoteid]);
    if ($pagenotes) {
        $notesdaughters = [];
        foreach ($pagenotes as $pagenote) {
            $notesdaughters[$pagenote->id] = $pagenote->comment;
            $tree = icontent_get_notes_daughters($pagenote->id);
            if ($tree) {
                $notesdaughters = $notesdaughters + $tree;
            }
        }
        return $notesdaughters;
    }
    return $pagenotes;
}

/**
 * Check that the value of param is a valid SQL clause.
 *
 * Returns string ASC or DESC.
 *
 * @param string $sortsql
 * @return $sort ASC or DESC.
 */
function icontent_check_value_sort($sortsql) {
    $sortsql = strtolower($sortsql);
    switch ($sortsql) {
        case 'desc':
            return 'DESC';
            break;
        default:
            return "ASC";
    }
}

/**
 * Checks if exists answers the questions of current page.
 *
 * Returns array answerspage
 *
 * @param int $pageid
 * @param int $cmid
 * @return array $answerspage
 */
function icontent_checks_answers_of_currentpage($pageid, $cmid) {
    global $DB;
    $sql = "SELECT Count(qa.id)     AS totalanswers
            FROM   {icontent_question_attempts} qa
                   INNER JOIN {icontent_pages_questions} pq
                           ON qa.pagesquestionsid = pq.id
            WHERE  pq.pageid = ?
                   AND pq.cmid = ?;";
    $totalanswers = $DB->get_record_sql($sql, [$pageid, $cmid]);
    // Checks if a property isn't empty.
    if (!empty($totalanswers->totalanswers)) {
        return $totalanswers;
    }
    return false;
}

/**
 * Check if has permission for edition.
 *
 * @param boolean $allowedit
 * @param boolean $edit Received by parameter in the URL.
 * @return boolean true if the user has this permission. Otherwise false.
 */
function icontent_has_permission_edition($allowedit, $edit = 0) {
    global $USER;
    if ($allowedit) {
        if ($edit != -1 && confirm_sesskey()) {
            $USER->editing = $edit;
        } else {
            if (isset($USER->editing)) {
                $edit = $USER->editing;
            } else {
                $edit = 0;
            }
        }
    } else {
        $edit = 0;
    }
    return $edit;
}

// FUNCTIONS CAPABILITYES.
/**
 * Check if has permission of manager.
 * @param string $context
 * @return boolean true if the user has this permission. Otherwise false.
 */
function icontent_has_permission_manager($context) {
    if (has_any_capability(['mod/icontent:edit', 'mod/icontent:manage'], $context)) {
        return true;
    }
    return false;
}

/**
 * Check if the user is owner the note.
 * @param object $pagenote
 * @return boolean true if the user has this permission. Otherwise false.
 */
function icontent_check_user_isowner_note($pagenote) {
    global $USER;
    if ($USER->id === $pagenote->userid) {
        return true;
    }
    return false;
}

/**
 * Check if user can remove note.
 * @param object $pagenote
 * @param string $context
 * @return boolean true if the user has this permission. Otherwise false.
 */
function icontent_user_can_remove_note($pagenote, $context) {
    if (has_capability('mod/icontent:removenotes', $context)) {
        if (icontent_check_user_isowner_note($pagenote)) {
            return true;
        }
        if (icontent_has_permission_manager($context)) {
            return true;
        }
    }
    return false;
}

/**
 * Check if user can edit note.
 * @param object $pagenote
 * @param string $context
 * @return boolean true if the user has this permission. Otherwise false.
 */
function icontent_user_can_edit_note($pagenote, $context) {
    if (has_capability('mod/icontent:editnotes', $context)) {
        if (icontent_check_user_isowner_note($pagenote)) {
            return true;
        }
        if (icontent_has_permission_manager($context)) {
            return true;
        }
    }
    return false;
}

/**
 * Check if user can reply note.
 * @param object $pagenote
 * @param string $context
 * @return boolean true if the user has this permission. Otherwise false.
 */
function icontent_user_can_reply_note($pagenote, $context) {
    if (has_capability('mod/icontent:replynotes', $context)) {
        if (icontent_has_permission_manager($context)) {
            return true;
        }
        if ($pagenote->doubttutor) {
            return false;
        }
        return true;
    }
    return false;
}

/**
 * Check if user can like or do not like the note.
 * @param object $pagenote
 * @param string $context
 * @return boolean true if the user has this permission. Otherwise false.
 */
function icontent_user_can_likeunlike_note($pagenote, $context) {
    if (has_capability('mod/icontent:likenotes', $context)) {
        if (icontent_has_permission_manager($context)) {
            return true;
        }
        if ($pagenote->doubttutor) {
            return false;
        }
        return true;
    }
    return false;
}

/**
 * Check if user can view private field.
 * @param string $context
 * @return boolean true if the user has this permission. Otherwise false.
 */
function icontent_user_can_view_checkbox_field_private($context) {
    if (has_capability('mod/icontent:checkboxprivatenotes', $context)) {
        return true;
    }
    return false;
}

/**
 * Check if user can view featured field.
 * @param string $context
 * @return boolean true if the user has this permission. Otherwise false.
 */
function icontent_user_can_view_checkbox_field_featured($context) {
    if (has_capability('mod/icontent:checkboxfeaturednotes', $context)) {
        return true;
    }
    return false;
}

/**
 * Check if user can view doubttutor field.
 * @param string $context
 * @return boolean true if the user has this permission. Otherwise false.
 */
function icontent_user_can_view_checkbox_field_doubttutor($context) {
    if (has_capability('mod/icontent:checkboxdoubttutornotes', $context)) {
        return true;
    }
    return false;
}

/**
 * Check if user can remove attempts answers for try again.
 * @param int $pageid
 * @param int $cmid
 * @return boolean true if the user has this permission. Otherwise false.
 */
function icontent_user_can_remove_attempts_answers_for_tryagain($pageid, $cmid) {
    global $DB;
    // Get context.
    $context = context_module::instance($cmid);
    if (icontent_has_permission_manager($context)) {
        return true;
    }
    if (has_capability('mod/icontent:answerquestionstryagain', $context)) {
        // Get object page.
        $objpage = $DB->get_record('icontent_pages', ['id' => $pageid], 'id, pagenum, attemptsallowed', MUST_EXIST);
        if ((int)$objpage->attemptsallowed === 0) {
            return true;
        }
    }
    return false;
}

// FUNCTIONS CREATING AND RETURNS HTML.

 /**
  * Create button previous page.
  *
  * Returns button.
  *
  * @param object $button
  * @param int $tpages
  * @param string $icon
  * @return string with $btnprevious
  */
function icontent_make_button_previous_page($button, $tpages, $icon = null) {
    $objpage = new stdClass();
    $objpage->pagenum = $button->startwithpage;
    $objpage->cmid = $button->cmid;
    $pageprevious = icontent_get_prev_pagenum($objpage);
    $pagepreviousid = icontent_get_pageid_by_pagenum($button->cmid, $pageprevious);
    $attributes = [
        'title' => $button->title,
        'class' => 'load-page btn-previous-page btn btn-secondary mr-1',
        'data-toggle' => 'tooltip',
        'data-totalpages' => $tpages,
        'data-placement' => 'top',
        'data-pagenum' => $pageprevious,
        'data-pageid' => $pagepreviousid,
        'data-cmid' => $button->cmid,
        'data-sesskey' => sesskey(),
    ];
    $url = '#';
    if (!empty($pagepreviousid)) {
        $url = new moodle_url('/mod/icontent/view.php', ['id' => $button->cmid, 'pageid' => $pagepreviousid]);
    }
    if (!$pageprevious) {
        $attributes = $attributes + [
            'disabled' => 'disabled',
            'aria-disabled' => 'true',
            'tabindex' => '-1',
            'class' => $attributes['class'] . ' disabled',
        ];
    }
    return html_writer::link($url, $icon . $button->name, $attributes);
}

/**
 * Create button next page.
 *
 * Returns button.
 *
 * @param object $button
 * @param int $tpages
 * @param string $icon
 * @return string with $btnnext
 */
function icontent_make_button_next_page($button, $tpages, $icon = null) {
    $objpage = new stdClass();
    $objpage->pagenum = $button->startwithpage;
    $objpage->cmid = $button->cmid;
    $nextpage = icontent_get_next_pagenum($objpage);
    $nextpageid = icontent_get_pageid_by_pagenum($button->cmid, $nextpage);
    $attributes = [
        'title' => $button->title,
        'class' => 'load-page btn-next-page btn btn-secondary',
        'data-toggle' => 'tooltip',
        'data-totalpages' => $tpages,
        'data-placement' => 'top',
        'data-pagenum' => $nextpage,
        'data-pageid' => $nextpageid,
        'data-cmid' => $button->cmid,
        'data-sesskey' => sesskey(),
    ];
    $url = '#';
    if (!empty($nextpageid)) {
        $url = new moodle_url('/mod/icontent/view.php', ['id' => $button->cmid, 'pageid' => $nextpageid]);
    }
    if (!$nextpage) {
        $attributes = $attributes + [
            'disabled' => 'disabled',
            'aria-disabled' => 'true',
            'tabindex' => '-1',
            'class' => $attributes['class'] . ' disabled',
        ];
    }
    return html_writer::link($url, $button->name . $icon, $attributes);
}

/**
 * This is the function responsible for creating a list of answers to the notes that will be removed.
 *
 * Return list of answers.
 *
 * @param array $notesdaughters
 * @return string $listgroup
 */
function icontent_make_list_group_notesdaughters($notesdaughters) {
    if ($notesdaughters) {
        $listgroup = html_writer::start_tag('ul');
        $likes = '';
        foreach ($notesdaughters as $key => $note) {
            $likes = html_writer::span(icontent_count_pagenotelike($key), 'badge');
            $listgroup .= html_writer::tag('li', $note . $likes, ['class' => 'list-group-item']);
        }
        $listgroup .= html_writer::end_tag('ul');
        return $listgroup;
    }
    return false;
}

/**
 * This is the function responsible for creating a progress bar.
 *
 * Return progress bar.
 *
 * @param object $objpage
 * @param object $icontent
 * @param object $context
 * @return string $progressbar
 */
function icontent_make_progessbar($objpage, $icontent, $context) {
    if (!$icontent->progressbar) {
        return false;
    }
    global $USER;
    $npages = icontent_count_pages($icontent->id);
    $npagesviewd = icontent_count_pageviewedbyuser($USER->id, $objpage->cmid);
    $percentage = ($npagesviewd * 100) / $npages;
    $percent = html_writer::span(get_string('labelprogressbar', 'icontent', $percentage), 'sr-only');
    $progressbar = html_writer::div(
        $percent,
        'progress-bar progress-bar-striped active',
        [
            'role' => 'progressbar',
            'aria-valuenow' => $percentage,
            'aria-valuemin' => '0',
            'aria-valuemax' => '100',
            'style' => "width: {$percentage}%;",
        ]
    );
    $progress = html_writer::div($progressbar, 'progress');
    return $progress;
}

/**
 * This is the function responsible for creating the area questions on pages.
 *
 * Returns questions area.
 *
 * @param object $objpage
 * @param object $icontent
 * @return string $questionsarea
 */
function icontent_make_questionsarea($objpage, $icontent) {
    $questions = icontent_get_pagequestions($objpage->id, $objpage->cmid);
    if (!$questions) {
        return false;
    }

    // Phase 1 wiring: bootstrap question engine usage for supported qtypes.
    icontent_question_engine_phase1_bootstrap_usage($objpage, $questions);

    if (icontent_get_attempt_summary_by_page($objpage->id, $objpage->cmid)) {
        return icontent_make_attempt_summary_by_page($objpage->id, $objpage->cmid);
    }
    // Add the triangle that toggles the list of questions visible and not visible.
    $togglearea = icontent_get_toggle_area_object($objpage->expandquestionsarea);
    // Title in h4 style for the questions part of the page.
    $title = html_writer::tag(
        'h4',
        $togglearea->icon . get_string('answerthequestions', 'mod_icontent'),
        [
            'class' => 'titlequestions text-uppercase ' . $togglearea->class,
            'id' => 'idtitlequestionsarea',
        ]
    );
    $qlist = '';
    $questionnumber = 1;
    foreach ($questions as $question) {
        // Assemble the listing of all the questions on a slide/page.
        $qlist .= icontent_make_questions_answers_by_type($question, $objpage, $questionnumber);
        $questionnumber++;
    }
    // Hidden form fields.
    $hiddenfields = html_writer::empty_tag(
        'input',
        [
            'type' => 'hidden',
            'name' => 'id',
            'value' => $objpage->cmid,
            'id' => 'idhfieldcmid',
        ]
    );
    $hiddenfields .= html_writer::empty_tag(
        'input',
        [
            'type' => 'hidden',
            'name' => 'pageid',
            'value' => $objpage->id,
            'id' => 'idhfieldpageid',
        ]
    );
    $hiddenfields .= html_writer::empty_tag(
        'input',
        [
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey(),
            'id' => 'idhfieldsesskey',
        ]
    );
    // Button send questions.
    $qbtnsend = html_writer::empty_tag(
        'input',
        [
            'type' => 'submit',
            'name' => 'qbtnsend',
            'class' => 'btn-sendanswers btn-primary pull-right',
            'value' => get_string('sendanswers', 'mod_icontent'),
        ]
    );
    $coldivbtnsend = html_writer::div($qbtnsend, 'col align-self-end');
    $divbtnsend = html_writer::div($coldivbtnsend, 'row sendanswers mb-2');
    // Tag form.
    $qform = html_writer::tag(
        'form',
        $hiddenfields . $qlist . $divbtnsend,
        [
            'action' => '',
            'method' => 'POST',
            'id' => 'idformquestions',
        ]
    );
    $divcontent = html_writer::div(
        $qform,
        'contentquestionsarea',
        [
            'id' => 'idcontentquestionsarea',
            'style' => $togglearea->style,
        ]
    );
    return html_writer::div($title . $divcontent, 'questionsarea', ['id' => 'idquestionsarea']);
}

/**
 * Build per-question tools (e.g. remove) for edit mode on view page.
 *
 * @param object $question
 * @param object|null $objpage
 * @return string
 */
function icontent_make_question_tools($question, $objpage = null) {
    global $USER;

    if (empty($objpage) || empty($question->qpid)) {
        return '';
    }

    if (!property_exists($USER, 'editing') || empty($USER->editing)) {
        return '';
    }

    $context = context_module::instance((int)$objpage->cmid);
    if (!has_any_capability(['mod/icontent:edit', 'mod/icontent:manage'], $context)) {
        return '';
    }

    if (icontent_checks_answers_of_currentpage((int)$objpage->id, (int)$objpage->cmid)) {
        return '';
    }

    $removeurl = new moodle_url('/mod/icontent/view.php', [
        'id' => (int)$objpage->cmid,
        'pageid' => (int)$objpage->id,
        'removeqpid' => (int)$question->qpid,
        'sesskey' => sesskey(),
    ]);
    $confirmmessage = get_string('confirmremovequestion', 'mod_icontent');
    if (preg_match('/^\[\[.*\]\]$/', $confirmmessage)) {
        $confirmmessage = 'Are you sure you want to remove this question from the page?';
    }

    $removeicon = html_writer::link(
        $removeurl,
        '<i class="fa fa-times-circle fa-lg"></i>',
        [
            'title' => s(get_string('remove', 'mod_icontent')),
            'class' => 'icon icon-removequestion',
            'data-toggle' => 'tooltip',
            'data-placement' => 'top',
            'onclick' => 'return confirm(' . json_encode($confirmmessage) . ');',
        ]
    );

    return html_writer::div($removeicon, 'question-tools text-end mb-2');
}

/**
 * This is the function responsible for creating the answers of questions area.
 *
 * Patterns for field names and values of question types:
 * Multichoice name = qpid-QPID_qid-QID_QTYPE or qpid-QPID_qid-QID_QTYPE[];
 * Multichoice value = qpid-QPID_answerid-ID;
 * Match name = qpid-QPID_qid-QID_QTYPE-ID;
 * Truefalse name = qpid-QPID_qid-QID_QTYPE;
 * Essay name = qpid-QPID_qid-QID_QTYPE.
 *
 * Important: Items in capital letters must be replaced by variables.
 *
 * Returns fields and answers by type.
 *
 * @param object $question
 * @param object|null $objpage
 * @param int $displaynumber
 * @return string $answers
 */
function icontent_make_questions_answers_by_type($question, $objpage = null, $displaynumber = 1) {
    global $DB;

    if (!empty($objpage)) {
        $qenginehtml = icontent_question_engine_phase2_render_question($objpage, $question, $displaynumber);
        if ($qenginehtml !== false) {
            return $qenginehtml;
        }
    }

    $questiontools = icontent_make_question_tools($question, $objpage);

    switch ($question->qtype) {
        case ICONTENT_QTYPE_MULTICHOICE:
            $answers = $DB->get_records('question_answers', ['question' => $question->qid]);
            shuffle($answers); // 20240718 Trying to shuffle the answers for multichoice question. Appears to work!
            $totalrightanswers = $DB->count_records_select(
                'question_answers',
                'question = ? AND fraction > ?',
                [
                    $question->qid,
                    0,
                ],
                'COUNT(fraction)'
            );
            // Print out the prompts. If there is more than one correct answer use the if, Choice {$a} options:,
            // and if there is only one answer use the else, Choice a:.
            if ($totalrightanswers > 1) {
                $type = 'checkbox';
                $brackets = '[]';
                $strprompt = get_string('choiceoneormore', 'mod_icontent', $totalrightanswers);
            } else {
                $type = 'radio';
                $brackets = '';
                $strprompt = get_string('choiceone', 'mod_icontent');
            }
            $strpromptinfo = html_writer::span($strprompt, 'label label-info');
            $questionanswers = html_writer::start_div('question ' . ICONTENT_QTYPE_MULTICHOICE);
            $questionanswers .= $questiontools;
            $questionanswers .= html_writer::div(strip_tags($question->questiontext, '<b><strong>'), 'questiontext');
            $questionanswers .= html_writer::div($strpromptinfo, 'prompt');
            $questionanswers .= html_writer::start_div('optionslist'); // Start div options list.
            foreach ($answers as $anwswer) {
                $fieldname = 'qpid-' . $question->qpid . '_qid-' . $question->qid . '_' . ICONTENT_QTYPE_MULTICHOICE . $brackets;
                $value = 'qpid-' . $question->qpid . '_answerid-' . $anwswer->id;
                $fieldid = 'idfield-qpid:' . $question->qpid . '_answerid:' . $anwswer->id;
                $check = html_writer::empty_tag(
                    'input',
                    [
                        'id' => $fieldid,
                        'name' => $fieldname,
                        'type' => $type,
                        'value' => $value,
                        'class' => 'mr-2',
                    ]
                );
                $label = html_writer::label(strip_tags($anwswer->answer), $fieldid);
                $questionanswers .= html_writer::div($check . $label);
            }
            $questionanswers .= html_writer::end_div(); // End div options list.
            $questionanswers .= html_writer::end_div();
            return $questionanswers;
            break;
        case ICONTENT_QTYPE_MATCH:
            $options = $DB->get_records('qtype_match_subquestions', ['questionid' => $question->qid], 'answertext');
            $questionanswers = html_writer::start_div('question ' . ICONTENT_QTYPE_MATCH);
            $questionanswers .= $questiontools;
            $questionanswers .= html_writer::div(strip_tags($question->questiontext, '<b><strong>'), 'questiontext mr-2');
            $questionanswers .= html_writer::start_div('optionslist'); // Start div options list.
            $contenttable = '';
            $arrayanswers = [];
            $rows = array_values($options);
            $answers = array_values($options);
            shuffle($rows);
            shuffle($answers);
            foreach ($answers as $option) {
                $optanswertext = trim(strip_tags($option->answertext));
                $arrayanswers[$optanswertext] = $optanswertext;
            }
            foreach ($rows as $option) {
                $fieldname = 'qpid-' . $question->qpid . '_qid-' . $question->qid . '_' . ICONTENT_QTYPE_MATCH . '-' . $option->id;
                $qtext = html_writer::tag('td', strip_tags($option->questiontext), ['class' => 'matchoptions']);
                $answertext = html_writer::tag(
                    'td',
                    html_writer::select(
                        $arrayanswers,
                        $fieldname,
                        null,
                        [
                            '' => 'choosedots',
                        ],
                        [
                            'class' => 'match-select',
                            'required' => 'required',
                        ]
                    ),
                    ['class' => 'matchanswer']
                );
                $contenttable .= html_writer::tag('tr', $qtext . $answertext);
            }
            $questionanswers .= html_writer::tag('table', $contenttable, ['class' => 'match-table']);
            $questionanswers .= html_writer::end_div(); // End div options list.
            $questionanswers .= html_writer::end_div();
            return $questionanswers;
            break;
        case ICONTENT_QTYPE_TRUEFALSE:
            $answers = $DB->get_records('question_answers', ['question' => $question->qid]);
            shuffle($answers); // 20240718 Trying to shuffle the answers for true/false question. Appears to work!
            $strpromptinfo = html_writer::span(get_string('choiceoneoption', 'mod_icontent'), 'label label-info' . 'test3');
            $questionanswers = html_writer::start_div('question ' . ICONTENT_QTYPE_TRUEFALSE);
            $questionanswers .= $questiontools;
            $questionanswers .= html_writer::div(strip_tags($question->questiontext, '<b><strong>'), 'questiontext');
            $questionanswers .= html_writer::div($strpromptinfo, 'prompt');
            $questionanswers .= html_writer::start_div('optionslist'); // Start div options list.
            foreach ($answers as $anwswer) {
                $fieldname = 'qpid-' . $question->qpid . '_qid-' . $question->qid . '_' . ICONTENT_QTYPE_TRUEFALSE;
                $value = 'qpid-' . $question->qpid . '_answerid-' . $anwswer->id;
                $fieldid = 'idfield-qpid:' . $question->qpid . '_answerid:' . $anwswer->id;
                $radio = html_writer::empty_tag(
                    'input',
                    [
                        'id' => $fieldid,
                        'name' => $fieldname,
                        'type' => 'radio',
                        'value' => $value,
                        'class' => 'mr-2',
                    ]
                );
                $label = html_writer::label(strip_tags($anwswer->answer), $fieldid);
                $questionanswers .= html_writer::div($radio . $label, 'options');
            }
            $questionanswers .= html_writer::end_div(); // End div options list.
            $questionanswers .= html_writer::end_div();
            return $questionanswers;
            break;
        case ICONTENT_QTYPE_ESSAY:
            $fieldname = 'qpid-' . $question->qpid . '_qid-' . $question->qid . '_' . ICONTENT_QTYPE_ESSAY;
            $fieldid = 'idfield-qpid:' . $question->qpid . '_qid:' . $question->qid . '_' . ICONTENT_QTYPE_ESSAY;
            $context = null;
            if (!empty($objpage) && !empty($objpage->cmid)) {
                $context = context_module::instance((int)$objpage->cmid);
            }
            $preferredformat = $context ? editors_get_preferred_format($context) : FORMAT_HTML;
            $preferrededitor = editors_get_preferred_editor($preferredformat);
            $questionanswers = html_writer::start_div('question essay');
            $questionanswers .= $questiontools;
            $questionanswers .= html_writer::div(strip_tags($question->questiontext, '<b><strong>'), 'questiontext');
            // 20240204 Modified params. See ticket iContent_1188.
            $questionanswers .= html_writer::tag(
                'textarea',
                null,
                [
                    'name' => $fieldname,
                    'id' => $fieldid,
                    'class' => 'col-12 answertextarea',
                    'required' => 'required',
                    'placeholder' => get_string('writeessay', 'mod_icontent'),
                ]
            );
            if ($preferrededitor) {
                $preferrededitor->use_editor($fieldid, [
                    'context' => $context,
                    'autosave' => false,
                ]);
            }
            $questionanswers .= html_writer::end_div();
            return $questionanswers;
            break;
        default:
            return false;
    }
}

/**
 * This is the function responsible for creating the attempt summary the current page.
 *
 * Returns attempt summary.
 *
 * @param int $pageid
 * @param int $cmid
 * @return string $attemptsummary
 */
function icontent_make_attempt_summary_by_page($pageid, $cmid) {
    global $DB;
    // Get objects that create summary attempt.
    $summaryattempt = icontent_get_attempt_summary_by_page($pageid, $cmid);
    $rightanswer = icontent_get_right_answers_by_attempt_summary_by_page($pageid, $cmid); // Items with hits.
    $openanswer = icontent_get_open_answers_by_attempt_summary_by_page($pageid, $cmid);
    $allownewattempts = icontent_user_can_remove_attempts_answers_for_tryagain($pageid, $cmid);
    // Check capabilities for new attempts.
    $straction = null;
    $iconrepeatattempt = null;
    if ($allownewattempts) {
        $straction = get_string('action', 'mod_icontent');
        // Icon repeat attempt.
        $iconrepeatattempt = html_writer::link(
            new moodle_url(
                'deleteattempt.php',
                [
                    'id' => $cmid,
                    'pageid' => $pageid,
                    'sesskey' => sesskey(),
                ]
            ),
            '<i class="fa fa-repeat fa-lg"></i>',
            [
                'title' => get_string('tryagain', 'mod_icontent'),
                'data-toggle' => 'tooltip',
                'data-placement' => 'top',
            ]
        );
    }
    $expandarea = $DB->get_field('icontent_pages', 'expandquestionsarea', ['id' => $pageid]);
    $togglearea = icontent_get_toggle_area_object($expandarea);
    // Create title.
    $title = html_writer::tag(
        'h4',
        $togglearea->icon . get_string('resultlastattempt', 'mod_icontent'),
        [
            'class' => 'titlequestions text-uppercase ' . $togglearea->class,
            'id' => 'idtitlequestionsarea',
        ]
    );
    // Create table.
    $summarygrid = new html_table();
    $summarygrid->id = "idcontentquestionsarea";
    $summarygrid->attributes = [
        'class' => 'table table-hover contentquestionsarea icontentattemptsummary',
        'style' => $togglearea->style,
    ];
    $summarygrid->head = [
        get_string('state', 'mod_icontent'),
        get_string('answers', 'mod_icontent'),
        get_string('rightanswers', 'mod_icontent'),
        get_string('result', 'mod_icontent'),
        $straction,
    ];
    $state = get_string('strstate', 'mod_icontent', userdate($summaryattempt->timecreated));
    $totalanswers = $summaryattempt->totalanswers;
    $totalrightanswers = (float)($rightanswer->totalrightanswers ?? 0);
    $equivalentrightanswers = (float)($rightanswer->equivalentrightanswers ?? 0);
    $rightanswersdisplay = (string)(int)$totalrightanswers;
    $stropenanswer = $openanswer->totalopenanswers ?
        get_string('stropenanswer', 'mod_icontent', $openanswer->totalopenanswers) : '';
    if (!empty($openanswer->totalopenanswers) && $equivalentrightanswers <= 0 && $totalrightanswers <= 0) {
        $rightanswersdisplay = get_string('pendingreview', 'mod_icontent');
    } else if ((int)$totalanswers > 1) {
        if ($equivalentrightanswers < 0) {
            $equivalentrightanswers = 0;
        }
        if ($equivalentrightanswers > (float)$totalanswers) {
            $equivalentrightanswers = (float)$totalanswers;
        }
        $rightanswersdisplay = number_format($equivalentrightanswers, 2) . ' / ' . (int)$totalanswers;
    }
    // String.
    $evaluate = new stdClass();
    $maxfraction = (float)($summaryattempt->maxfraction ?? 0);
    if ($maxfraction <= 0) {
        $maxfraction = (float)$summaryattempt->totalanswers;
    }
    $evaluate->fraction = number_format($summaryattempt->sumfraction, 2);
    $evaluate->maxfraction = number_format($maxfraction, 2);
    $evaluate->percentage = $maxfraction > 0 ? round(($summaryattempt->sumfraction * 100) / $maxfraction) : 0;
    $evaluate->openanswer = $stropenanswer;
    $strevaluate = get_string('strtoevaluate', 'mod_icontent', $evaluate);
    // Set data.
    $summarygrid->data[] = [$state, $totalanswers, $rightanswersdisplay, $strevaluate, $iconrepeatattempt];

    // Create table summary attempt.
    $tablesummary = html_writer::table($summarygrid);
    $answershtml = '';
    $submittedanswers = icontent_get_submitted_answers_by_attempt_summary_by_page($pageid, $cmid);
    if (!empty($submittedanswers)) {
        $answeritems = [];
        foreach ($submittedanswers as $submittedanswer) {
            $questionlabel = html_writer::tag('strong', format_string($submittedanswer->questionname) . ': ');
            $answercontent = icontent_render_manual_review_answer($submittedanswer, $cmid);
            $answeritems[] = html_writer::tag('li', $questionlabel . $answercontent, ['class' => 'mb-3']);
        }

        $answershtml = html_writer::div(
            html_writer::tag('h5', get_string('answers', 'mod_icontent')) .
            html_writer::tag('ul', implode('', $answeritems), ['class' => 'list-unstyled mb-0']),
            'icontent-submitted-answers mt-2'
        );
    }

    $commentshtml = '';
    $reviewercomments = icontent_get_reviewer_comments_by_attempt_summary_by_page($pageid, $cmid);
    if (!empty($reviewercomments)) {
        $commentstitle = get_string('comments', 'mod_icontent');
        if (get_string_manager()->string_exists('reviewercomments', 'mod_icontent')) {
            $commentstitle = get_string('reviewercomments', 'mod_icontent');
        }

        $items = [];
        foreach ($reviewercomments as $comment) {
            $questionlabel = html_writer::tag('strong', format_string($comment->questionname) . ': ');
            $commenttext = format_text((string)$comment->reviewercomment, (int)$comment->reviewercommentformat, [
                'noclean' => false,
                'para' => false,
            ]);
            $items[] = html_writer::tag('li', $questionlabel . $commenttext, ['class' => 'mb-3']);
        }

        $commentshtml = html_writer::div(
            html_writer::tag('h5', $commentstitle) .
            html_writer::tag('ul', implode('', $items), ['class' => 'list-unstyled mb-0']),
            'icontent-reviewer-comments mt-2'
        );
    }

    return html_writer::div($title . $tablesummary . $answershtml . $commentshtml, 'questionsarea', ['id' => 'idquestionsarea']);
}

/**
 * This is the function responsible for creating the area comments on pages.
 *
 * Returns notes area.
 *
 * @param object $objpage
 * @param object $icontent
 * @return string $notesarea
 */
function icontent_make_notesarea($objpage, $icontent) {
    if (!$icontent->shownotesarea) {
        return false;
    }
    $context = context_module::instance($objpage->cmid);
    if (!has_capability('mod/icontent:viewnotes', $context)) {
        return false;
    }
    global $OUTPUT, $USER;
    $togglearea = icontent_get_toggle_area_object($objpage->expandnotesarea);

    // Title page.
    $title = html_writer::tag(
        'h4',
        $togglearea->icon . get_string('doubtandnotes', 'mod_icontent'),
        [
            'class' => 'titlenotes text-uppercase ' . $togglearea->class,
            'id' => 'idtitlenotes',
        ]
    );

    // User image used under the Notes and Question tabs.
    $picture = html_writer::tag(
        'div',
        $OUTPUT->user_picture(
            $USER,
            [
            'size' => 120,
            'class' => 'img-thumbnail',
            ]
        ),
        [
            'class' => 'col-2 userpicture',
        ]
    );

    // Fields. Create text area for notes.
    $textareanote = html_writer::tag(
        'textarea',
        null,
        [
            'name' => 'comment',
            'id' => 'idcommentnote',
            'class' => 'col-12',
            'maxlength' => '1024',
            'required' => 'required',
            'placeholder' => get_string('writenotes', 'mod_icontent'),
        ]
    );

    // Create checkboxes for private and featured, right under the notes textarea.
    $spanprivate = icontent_make_span_checkbox_field_private($objpage);
    $spanfeatured = icontent_make_span_checkbox_field_featured($objpage);

    // Create the, Save, button under the right side of the note textarea.
    $btnsavenote = html_writer::tag(
        'button',
        get_string('save', 'mod_icontent'),
        [
            'class' => 'btn btn-primary pull-right',
            'id' => 'idbtnsavenote',
            'data-pageid' => $objpage->id,
            'data-cmid' => $objpage->cmid,
            'data-sesskey' => sesskey(),
        ]
    );

    // Create text area for questions.
    $textareadoubt = html_writer::tag(
        'textarea',
        null,
        [
            'name' => 'comment',
            'id' => 'idcommentdoubt',
            'class' => 'col-12',
            'maxlength' => '1024',
            'required' => 'required',
            'placeholder' => get_string('writedoubt', 'mod_icontent'),
        ]
    );
    // Create check box for, Ask tutor only.
    $spandoubttutor = icontent_make_span_checkbox_field_doubttutor($objpage);
    // Create the question save button.
    $btnsavedoubt = html_writer::tag(
        'button',
        get_string('save', 'mod_icontent'),
        [
            'class' => 'btn btn-primary pull-right',
            'id' => 'idbtnsavedoubt',
            'data-pageid' => $objpage->id,
            'data-cmid' => $objpage->cmid,
            'data-sesskey' => sesskey(),
        ]
    );

    // Create text area for tags.
    $textareatag = html_writer::tag(
        'textarea',
        null,
        [
            'name' => 'comment',
            'id' => 'idcommenttag',
            'class' => 'col-12',
            'maxlength' => '1024',
            'required' => 'required',
            'placeholder' => get_string('writetag', 'mod_icontent'),
        ]
    );

    // Ask tutor only is intentionally hidden from this form.
    // Create the question save button.
    // Tag save button is intentionally disabled.

    // Data page.
    $datapagenotesnote = icontent_get_pagenotes($objpage->id, $objpage->cmid, 'note'); // Data page notes note.
    $datapagenotesdoubt = icontent_get_pagenotes($objpage->id, $objpage->cmid, 'doubt'); // Data page notes question.
    // Tag notes are intentionally disabled.
    $pagenotesnote = html_writer::div(
        icontent_make_listnotespage($datapagenotesnote, $icontent, $objpage),
        'pagenotesnote',
        [
            'id' => 'idpagenotesnote',
        ]
    );
    $pagenotesdoubt = html_writer::div(
        icontent_make_listnotespage($datapagenotesdoubt, $icontent, $objpage),
        'pagenotesdoubt',
        [
            'id' => 'idpagenotesdoubt',
        ]
    );

    // Fields.
    $fieldsnote = html_writer::tag(
        'div',
        $textareanote . $spanprivate . $spanfeatured . $btnsavenote . $pagenotesnote,
        [
            'class' => 'col-10',
        ]
    );
    $fieldsdoubt = html_writer::tag(
        'div',
        $textareadoubt . $spandoubttutor . $btnsavedoubt . $pagenotesdoubt,
        [
            'class' => 'col-10',
        ]
    );
    // Tag field block is intentionally disabled.

    // Forms.
    $formnote = html_writer::tag('div', $picture . $fieldsnote, ['class' => 'row fields mt-2']);
    $formdoubt = html_writer::tag('div', $picture . $fieldsdoubt, ['class' => 'row fields mt-2']);
    // Tag form is intentionally disabled.

    // TAB NAVS.
    $note = html_writer::tag(
        'li',
        html_writer::link(
            '#note',
            get_string('note', 'icontent', count($datapagenotesnote)),
            [
                'id' => 'note-tab',
                'aria-controls' => 'note',
                'role' => 'tab',
                'data-bs-toggle' => 'tab',
                'class' => 'nav-link active',
            ]
        ),
        [
            'class' => 'nav-item',
            'role' => 'presentation',
        ]
    );
    $doubt = html_writer::tag(
        'li',
        html_writer::link(
            '#doubt',
            get_string('doubt', 'icontent', count($datapagenotesdoubt)),
            [
                'id' => 'doubt-tab',
                'aria-controls' => 'doubt',
                'role' => 'tab',
                'data-bs-toggle' => 'tab',
                'class' => 'nav-link',
            ]
        ),
        [
            'class' => 'nav-item',
            'role' => 'presentation',
        ]
    );
    // Tag tab is intentionally disabled.
    $tabnav = html_writer::tag('ul', $note . $doubt, ['class' => 'nav nav-tabs', 'id' => 'tabnav']);
    // Tag tab navigation is intentionally disabled.
    // TAB CONTENT.
    $icontentnote = html_writer::div($formnote, 'tab-pane active', ['role' => 'tabpanel', 'id' => 'note']);
    $icontentdoubt = html_writer::div($formdoubt, 'tab-pane', ['role' => 'tabpanel', 'id' => 'doubt']);
    // Tag tab content is intentionally disabled.
    $tabicontent = html_writer::div($icontentnote . $icontentdoubt, 'tab-content', ['id' => 'idtabicontent']);
    $fulltab = html_writer::div($tabnav . $tabicontent, 'fulltab', ['id' => 'idfulltab', 'style' => $togglearea->style]);
    // Return notes area.
    return html_writer::tag('div', $title . $fulltab, ['class' => 'notesarea', 'id' => 'idnotesarea']);
}

/**
 * This is the function responsible for creating checkbox field private.
 *
 * Returns span with checkbox field.
 *
 * @param string $page
 * @return string $spancheckbox
 */
function icontent_make_span_checkbox_field_private($page) {
    $context = context_module::instance($page->cmid);
    if (icontent_user_can_view_checkbox_field_private($context)) {
        $checkprivate = html_writer::tag(
            'input',
            null,
            [
                'name' => 'private',
                'type' => 'checkbox',
                'id' => 'idprivate',
                'class' => 'icontent-checkbox mr-2',
            ]
        );
        $labelprivate = html_writer::tag(
            'label',
            get_string('private', 'mod_icontent'),
            [
                'for' => 'idprivate',
                'class' => 'icontent-label',
            ]
        );
        // Return span.
        return html_writer::tag('span', $checkprivate . $labelprivate, ['class' => 'fieldprivate font-weight-light']);
    }
    return false;
}

/**
 * This is the function responsible for creating checkbox field featured.
 *
 * Returns span with checkbox featured.
 *
 * @param string $page
 * @return string $spancheckbox
 */
function icontent_make_span_checkbox_field_featured($page) {
    $context = context_module::instance($page->cmid);
    if (icontent_user_can_view_checkbox_field_featured($context)) {
        $checkfeatured = html_writer::tag(
            'input',
            null,
            [
                'name' => 'featured',
                'type' => 'checkbox',
                'id' => 'idfeatured',
                'class' => 'icontent-checkbox mr-2',
            ]
        );
        $labelfeatured = html_writer::tag(
            'label',
            get_string('featured', 'mod_icontent'),
            [
                'for' => 'idfeatured',
                'class' => 'icontent-label',
            ]
        );
        // Return span.
        return html_writer::tag('span', $checkfeatured . $labelfeatured, ['class' => 'fieldfeatured font-weight-light']);
    }
    return false;
}

/**
 * This is the function responsible for creating checkbox field doubttutor.
 *
 * Returns span with checkbox doubttutor
 * @param object $page
 * @return string $spancheckbox
 */
function icontent_make_span_checkbox_field_doubttutor($page) {
    $context = context_module::instance($page->cmid);
    if (icontent_user_can_view_checkbox_field_doubttutor($context)) {
        $checkdoubttutor = html_writer::tag(
            'input',
            null,
            [
                'name' => 'doubttutor',
                'type' => 'checkbox',
                'id' => 'iddoubttutor',
                'class' => 'icontent-checkbox mr-2',
            ]
        );
        $labeldoubttutor = html_writer::tag(
            'label',
            get_string('doubttutor', 'mod_icontent'),
            [
                'for' => 'iddoubttutor',
                'class' => 'icontent-label',
            ]
        );
        // Return span.
        return html_writer::tag('span', $checkdoubttutor . $labeldoubttutor, ['class' => 'fielddoubttutor font-weight-light']);
    }
    return false;
}

/**
 * This is the function responsible for creating notes list by page.
 *
 * Returns notes list
 *
 * @param object $pagenotes
 * @param object $icontent
 * @param object $page
 * @return string $listnotes
 */
function icontent_make_listnotespage($pagenotes, $icontent, $page) {
    global $OUTPUT;
    if (!empty($pagenotes)) {
        $divnote = '';
        $context = context_module::instance($page->cmid);
        foreach ($pagenotes as $pagenote) {
            // Object user.
            $user = icontent_get_user_by_id($pagenote->userid);
            // Get picture for use with the note listing.
            $picture = $OUTPUT->user_picture($user, ['size' => 35, 'class' => 'img-thumbnail pull-left']);
            // Note header comprised of the user first name and the title of the slide.
            $linkfirstname = html_writer::link(
                new moodle_url(
                    '/user/view.php',
                    [
                    'id' => $user->id,
                    'course' => $icontent->course,
                    ]
                ),
                $user->firstname . ' ' . $user->lastname,
                [
                    'title' => $user->firstname,
                ]
            );
            $noteon = html_writer::tag('em', get_string('notedon', 'icontent'), ['class' => 'noteon mr-2 ml-2']);
            // Reply header.
            $replyon = html_writer::tag(
                'em',
                ' ' . strtolower(trim(get_string('respond', 'icontent'))) . ': ',
                [
                    'class' => 'noteon mr-2 ml-2',
                ]
            );
            $notepagetitle = html_writer::span($page->title, 'notepagetitle');
            $noteheader = $pagenote->parent ? html_writer::div($linkfirstname . $replyon, 'noteheader') :
                html_writer::div($linkfirstname . $noteon . $notepagetitle, 'noteheader');
            // Note comments.
            $notecomment = html_writer::div(
                $pagenote->comment,
                'notecomment',
                [
                    'data-pagenoteid' => $pagenote->id,
                    'data-cmid' => $pagenote->cmid,
                    'data-sesskey' => sesskey(),
                ]
            );
            // Note footer.
            $noteedit = icontent_make_link_edit_note($pagenote, $context);
            $noteremove = icontent_make_link_remove_note($pagenote, $context);
            $notelike = icontent_make_likeunlike($pagenote, $context);
            $notereply = icontent_make_link_reply_note($pagenote, $context);
            $notedate = html_writer::tag('span', userdate($pagenote->timecreated), ['class' => 'notedate pull-right']);
            // Create footer with items in the order given here.
            $notefooter = html_writer::div($noteedit . $noteremove . $notereply . $notelike . $notedate, 'notefooter');
            // Verify path levels.
            $pathlevels = icontent_get_noteparentinglevels($pagenote->path);

            // Assemle all the notes into just one Div list.
            $noterowicontent = html_writer::div($noteheader . $notecomment . $notefooter, 'noterowicontent');
            $divnote .= html_writer::div(
                $picture . $noterowicontent,
                "pagenoterow level-$pathlevels",
                [
                    'data-level' => $pathlevels,
                    'id' => "pnote{$pagenote->id}",
                ]
            );
        }
        $divnotes = html_writer::div($divnote, 'span notelist');
        return $divnotes;
    }
    // Do this if there are not any notes.
    return html_writer::div(get_string('nonotes', 'icontent'));
}

/**
 * This is the function responsible for creating the responses of notes.
 *
 * Returns responses of notes.
 *
 * @param object $pagenote
 * @param object $icontent
 * @return string $pagenotereply
 */
function icontent_make_pagenotereply($pagenote, $icontent) {
    global $OUTPUT;
    $user = icontent_get_user_by_id($pagenote->userid);
    $context = context_module::instance($pagenote->cmid);
    // Get picture for use with the reply listing.
    $picture = $OUTPUT->user_picture($user, ['size' => 30, 'class' => 'img-thumbnail pull-left']);
    // Note header.
    $linkfirstname = html_writer::link(
        new moodle_url(
            '/user/view.php',
            [
            'id' => $user->id,
            'course' => $icontent->course,
            ]
        ),
        $user->firstname,
        [
            'title' => $user->firstname,
        ]
    );
    $replyon = html_writer::tag(
        'em',
        ' ' . strtolower(trim(get_string('respond', 'icontent'))) . ': ',
        [
            'class' => 'noteon mr-2 ml-2',
        ]
    );
    $noteheader = html_writer::div($linkfirstname . $replyon, 'noteheader');
    // Note comments.
    $notecomment = html_writer::div(
        $pagenote->comment,
        'notecomment',
        [
            'data-pagenoteid' => $pagenote->id,
            'data-cmid' => $pagenote->cmid,
            'data-sesskey' => sesskey(),
        ]
    );
    // Note footer.
    $noteedit = icontent_make_link_edit_note($pagenote, $context);
    $noteremove = icontent_make_link_remove_note($pagenote, $context);
    $notelike = icontent_make_likeunlike($pagenote, $context);
    $notereply = icontent_make_link_reply_note($pagenote, $context);
    $notedate = html_writer::tag('span', userdate($pagenote->timecreated), ['class' => 'notedate pull-right']);
    $notefooter = html_writer::div($noteedit . $noteremove . $notereply . $notelike . $notedate, 'notefooter');
    // Verify path levels.
    $pathlevels = icontent_get_noteparentinglevels($pagenote->path);
    // Div list page notes.
    $noterowicontent = html_writer::div($noteheader . $notecomment . $notefooter, 'noterowicontent');
    // Return reply.
    return html_writer::div(
        $picture . $noterowicontent,
        "pagenoterow level-{$pathlevels}",
        [
            'data-level' => $pathlevels,
            'id' => "pnote{$pagenote->id}",
        ]
    );
}

/**
 * This is the function responsible for creating link to remove note.
 *
 * Returns link.
 *
 * @param object $pagenote
 * @param object $context
 * @return string $link
 */
function icontent_make_link_remove_note($pagenote, $context) {
    if (icontent_user_can_remove_note($pagenote, $context)) {
        return html_writer::link(
            new moodle_url(
                'deletenote.php',
                [
                'id' => $pagenote->cmid,
                'pnid' => $pagenote->id,
                'sesskey' => sesskey(),
                ]
            ),
            "<i class='fa fa-times'></i>" . get_string('remove', 'icontent'),
            [
                'class' => 'removenote',
            ]
        );
    }
    return false;
}

/**
 * This is the function responsible for creating link to edit note.
 *
 * Returns link.
 *
 * @param object $pagenote
 * @param object $context
 * @return string $link
 */
function icontent_make_link_edit_note($pagenote, $context) {
    if (icontent_user_can_edit_note($pagenote, $context)) {
        return html_writer::link(null, "<i class='fa fa-pencil'></i>" . get_string('edit', 'icontent'), ['class' => 'editnote']);
    }
    return false;
}

/**
 * This is the function responsible for creating link to reply note.
 *
 * Returns link.
 *
 * @param object $pagenote
 * @param object $context
 * @return string $link
 */
function icontent_make_link_reply_note($pagenote, $context) {
    if (icontent_user_can_reply_note($pagenote, $context)) {
        return html_writer::link(
            null,
            "<i class='fa fa-reply-all'></i>" . get_string('reply', 'icontent'),
            ['class' => 'replynote']
        );
    }
    return false;
}

/**
 * This is the function responsible for creating links like and do not like.
 *
 * Returns links.
 *
 * @param object $pagenote
 * @param object $context
 * @return string $likeunlike
 */
function icontent_make_likeunlike($pagenote, $context) {
    global $USER;
    if (icontent_user_can_likeunlike_note($pagenote, $context)) {
        $pagenotelike = icontent_get_pagenotelike($pagenote->id, $USER->id, $pagenote->cmid);
        $countlikes = icontent_count_pagenotelike($pagenote->id);
        $notelinklabel = html_writer::span(get_string('like', 'icontent', $countlikes));
        if (!empty($pagenotelike)) {
            $notelinklabel = html_writer::span(get_string('unlike', 'icontent', $countlikes));
        }
        return html_writer::link(
            null,
            "<i class='fa fa-star-o'></i>" . $notelinklabel,
            [
                'class' => 'likenote',
                'data-cmid' => $pagenote->cmid,
                'data-pagenoteid' => $pagenote->id,
                'data-sesskey' => sesskey(),
            ]
        );
    }
    return false;
}

/**
 * This is the function responsible for creating the toolbar.
 *
 * @param object $page
 * @param object $icontent
 * @return string $toolbar
 */
function icontent_make_toolbar($page, $icontent) {
    global $USER;
    // Icons for all users.
    $comments = html_writer::link(
        '#idnotesarea',
        '<i class="fa fa-comments fa-lg"></i>',
        [
            'title' => s(get_string('comments', 'icontent')),
            'class' => 'icon icon-comments',
            'data-toggle' => 'tooltip',
            'data-placement' => 'top',
        ]
    );
    $icondisplayed = icontent_get_pagedisplayed($page->id, $page->cmid) ?
        '<i class="fa fa-check-square-o fa-lg"></i>' :
        '<i class="fa fa-square-o fa-lg"></i>';
    $displayed = html_writer::link(
        '#',
        $icondisplayed,
        [
            'title' => s(get_string('statusview', 'icontent')),
            'class' => 'icon icon-displayed',
            'data-toggle' => 'tooltip',
            'data-placement' => 'top',
        ]
    );
    $highcontrast = html_writer::link(
        '#!',
        '<i class="fa fa-adjust fa-lg"></i>',
        [
            'title' => s(get_string('highcontrast', 'icontent')),
            'class' => 'icon icon-highcontrast togglehighcontrast',
            'data-toggle' => 'tooltip',
            'data-placement' => 'top',
        ]
    );
    $update = false;
    $new = false;
    $addquestion = false;
    $delete = false;
    // Check if editing exists for $USER.
    if (property_exists($USER, 'editing')) {
        $context = context_module::instance($page->cmid);
        // Edit mode (view.php). Icons for teachers.
        if ($USER->editing && has_any_capability(['mod/icontent:edit', 'mod/icontent:manage'], $context)) {
            // Add new question.
            $addquestionparams = [
                'id' => $page->cmid,
                'pageid' => $page->id,
            ];
            $questioncategoryid = icontent_get_page_primary_questioncategoryid((int)$page->id, (int)$page->cmid);
            if (!empty($questioncategoryid)) {
                $addquestionparams['questioncategoryid'] = $questioncategoryid;
            }
            $addquestion = html_writer::link(
                new moodle_url('addquestionpage.php', $addquestionparams),
                '<i class="fa fa-question-circle fa-lg"></i>',
                [
                    'title' => s(get_string('addquestion', 'mod_icontent')),
                    'class' => 'icon icon-addquestion',
                    'data-toggle' => 'tooltip',
                    'data-placement' => 'top',
                ]
            );
            // Update page.
            $update = html_writer::link(
                new moodle_url(
                    'edit.php',
                    [
                        'cmid' => $page->cmid,
                        'id' => $page->id,
                        'sesskey' => $USER->sesskey,
                    ]
                ),
                '<i class="fa fa-pencil-square-o fa-lg"></i>',
                [
                    'title' => s(get_string('editcurrentpage', 'mod_icontent')),
                    'class' => 'icon icon-update',
                    'data-toggle' => 'tooltip',
                    'data-placement' => 'top',
                    ]
            );
            // Add new page.
            $new = html_writer::link(
                new moodle_url(
                    'edit.php',
                    [
                        'cmid' => $page->cmid,
                        'pagenum' => $page->pagenum,
                        'sesskey' => $USER->sesskey,
                    ]
                ),
                '<i class="fa fa-plus-circle fa-lg"></i>',
                [
                    'title' => s(get_string('addnewpage', 'mod_icontent')),
                    'class' => 'icon icon-new',
                    'data-toggle' => 'tooltip',
                    'data-placement' => 'top',
                ]
            );
            // Delete current page.
            $delete = html_writer::link(
                new moodle_url(
                    'delete.php',
                    [
                        'id' => $page->cmid,
                        'pageid' => $page->id,
                        'sesskey' => $USER->sesskey,
                    ]
                ),
                '<i class="fa fa-trash fa-lg"></i>',
                [
                    'title' => s(get_string('delete')),
                    'class' => 'icon icon-deletepage',
                    'data-toggle' => 'tooltip',
                    'data-placement' => 'top',
                ]
            );
        }
    }
    // Make toolbar.
    $toolbar = html_writer::tag(
        'div',
        $highcontrast . $comments . $displayed . $addquestion . $update . $new . $delete,
        [
            'class' => 'toolbarpage ',
        ]
    );
    // Return toolbar.
    return $toolbar;
}

/**
 * Get the first question bank category used by questions on this iContent page.
 *
 * @param int $pageid
 * @param int $cmid
 * @return int
 */
function icontent_get_page_primary_questioncategoryid($pageid, $cmid) {
        global $DB;

        $sql = "SELECT qbe.questioncategoryid
                            FROM {icontent_pages_questions} pq
                            JOIN {question_versions} qv
                                ON qv.questionid = pq.questionid
                            JOIN {question_bank_entries} qbe
                                ON qbe.id = qv.questionbankentryid
                         WHERE pq.pageid = ?
                             AND pq.cmid = ?
                    ORDER BY pq.id ASC";
        $categoryid = $DB->get_field_sql($sql, [$pageid, $cmid], IGNORE_MULTIPLE);

        return $categoryid ? (int)$categoryid : 0;
}

/**
 * Save tags for one iContent page.
 *
 * @param int $pageid
 * @param int $cmid
 * @param context_module $context
 * @param string $tagtext
 * @return void
 */
function icontent_save_page_tags($pageid, $cmid, context_module $context, $tagtext) {
    global $DB;

    $DB->get_record('icontent_pages', ['id' => $pageid, 'cmid' => $cmid], 'id', MUST_EXIST);

    $tags = preg_split('/[\r\n,]+/', (string)$tagtext);
    $tags = array_map('trim', $tags);
    $tags = array_values(array_filter($tags, static function ($tag) {
        return $tag !== '';
    }));

    \core_tag_tag::set_item_tags('mod_icontent', 'icontent_pages', $pageid, $context, $tags);
}

/**
 * Build the page tags area (list + optional edit form).
 *
 * @param stdClass $objpage
 * @return string
 */
function icontent_make_page_tags_area($objpage) {
    global $OUTPUT;

    $context = context_module::instance($objpage->cmid);
    $canedittags = has_capability('mod/icontent:edit', $context);
    $tags = \core_tag_tag::get_item_tags('mod_icontent', 'icontent_pages', $objpage->id);

    $title = html_writer::tag('h5', get_string('tags'), ['class' => 'text-uppercase']);
    $taglist = $OUTPUT->tag_list($tags, null, 'icontent-tags');
    if (!$tags) {
        $taglist = html_writer::div(get_string('notagsyet', 'mod_icontent'), 'alert alert-info');
    }

    $content = html_writer::div($taglist, 'icontent-page-tags-list', ['id' => 'idpagetagslist']);

    if ($canedittags) {
        $existingtags = [];
        foreach ($tags as $tag) {
            if (!empty($tag->rawname)) {
                $existingtags[] = $tag->rawname;
            } else if (!empty($tag->name)) {
                $existingtags[] = $tag->name;
            }
        }

        $textarea = html_writer::tag('textarea', s(implode(', ', $existingtags)), [
            'name' => 'pagetags',
            'id' => 'idcommenttag',
            'class' => 'col-12',
            'maxlength' => '1024',
            'placeholder' => get_string('writetag', 'mod_icontent'),
        ]);

        $savebtn = html_writer::tag('button', get_string('save', 'mod_icontent'), [
            'type' => 'submit',
            'class' => 'btn btn-primary pull-right mt-2',
            'id' => 'idbtnsavetag',
        ]);

        $hidden = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $objpage->cmid]);
        $hidden .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'pageid', 'value' => $objpage->id]);
        $hidden .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'savetags', 'value' => 1]);
        $hidden .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

        $form = html_writer::tag('form', $hidden . $textarea . $savebtn, [
            'method' => 'post',
            'action' => new moodle_url('/mod/icontent/view.php'),
            'class' => 'icontent-page-tags-form mt-2',
        ]);

        $content .= $form;
    }

    return html_writer::div($title . $content, 'icontent-page-tags mt-3', ['id' => 'idpagetagsarea']);
}

/**
 * This is the function responsible for creating the content for cover page.
 *
 * Returns a string with the cover page.
 *
 * @param object $icontent
 * @param object $objpage
 * @param object $context
 * @return string $coverpage
 */
function icontent_make_cover_page($icontent, $objpage, $context) {
    $limitcharshow = 500;
    $displaynone = false;
    $strcontent = strip_tags($objpage->pageicontent);
    $tchars = strlen($strcontent);
    if ($tchars > $limitcharshow) {
        $chars = html_writer::start_tag('p', ['class' => 'read-more-wrap']);
        $chars .= substr($strcontent, 0, $limitcharshow);
        $chars .= html_writer::span('...', 'suspension-points');
        $chars .= html_writer::span(substr($strcontent, $limitcharshow, $tchars), 'read-more-target');
        $chars .= html_writer::end_tag('p');
        $buttons = html_writer::link(
            null,
            '<i class="fa fa-plus"></i>&nbsp;' . get_string('showmore', 'mod_icontent'),
            [
                'class' => 'btn btn-default read-more-state-on',
            ]
        );
        $buttons .= html_writer::link(
            null,
            '<i class="fa fa-minus"></i>&nbsp;' . get_string('showless', 'mod_icontent'),
            [
                'class' => 'btn btn-default read-more-state-off',
            ]
        );
        $chars .= html_writer::div($buttons, 'state-readmore');
    } else {
        $chars = html_writer::tag('p', $strcontent);
        // Checks if content is empty.
        $nospace = str_replace('&nbsp;', '', $strcontent);
        $nospace = str_replace('.', '', $nospace);
        $nospace = trim($nospace);
        // Add class 'hide' to hide element and builds the page.
        $displaynone = empty($nospace) ? 'hide' : false;
    }
    $script = icontent_add_script_load_tooltip();
    // Elements toolbar.
    $toolbarpage = icontent_make_toolbar($objpage, $icontent);
    $title = html_writer::tag('h1', $objpage->title, ['class' => 'titlecoverpage']);
    $header = $objpage->showtitle ? html_writer::div($title, 'headercoverpage row ') : false;
    $content = html_writer::div($chars, "contentcoverpage " . $displaynone);
    $tagsarea = icontent_make_page_tags_area($objpage);
    $coverpage = html_writer::tag(
        'div',
        $toolbarpage . $header . $content . $tagsarea . $script,
        [
            'class' => 'fulltextpage coverpage',
            'data-pageid' => $objpage->id,
            'data-pagenum' => $objpage->pagenum,
            'style' => icontent_get_page_style($icontent, $objpage, $context),
        ]
    );
    // Set page preview, log event and return page.
    icontent_add_pagedisplayed($objpage->id, $objpage->cmid);
    \mod_icontent\event\page_viewed::create_from_page($icontent, $context, $objpage)->trigger();
    return $coverpage;
}

/**
 * This is the function responsible for creating the content of a page.
 *
 * Returns an object with the page content.
 *
 * @param int $pagenum or $startpage
 * @param object $icontent
 * @param object $context
 * @return object $fullpage
 */
function icontent_get_fullpageicontent($pagenum, $icontent, $context) {
    global $DB, $CFG;

    // Get page.
    $objpage = $DB->get_record('icontent_pages', ['pagenum' => $pagenum, 'icontentid' => $icontent->id]);
    if (!$objpage) {
        $objpage = new stdClass();
        $objpage->fullpageicontent = html_writer::div(
            get_string('pagenotfound', 'mod_icontent'),
            'alert alert-warning',
            [
                'role' => 'alert',
            ]
        );
        return $objpage;
    }
    if ($objpage->coverpage) {
        // Make cover page.
        $objpage->fullpageicontent = icontent_make_cover_page($icontent, $objpage, $context);
        // Control button.
        $objpage->previous = icontent_get_prev_pagenum($objpage);
        $objpage->next = icontent_get_next_pagenum($objpage);
        $objpage->previouspageid = icontent_get_pageid_by_pagenum($objpage->cmid, $objpage->previous);
        $objpage->nextpageid = icontent_get_pageid_by_pagenum($objpage->cmid, $objpage->next);
        return $objpage;
    }
    // Add tooltip.
    $script = icontent_add_script_load_tooltip();
    // Elements toolbar.
    $toolbarpage = icontent_make_toolbar($objpage, $icontent);
    // Add title page.
    $titlestyle = '';
    if (!empty($objpage->titlecolor)) {
        $titlestyle = 'color: #' . icontent_normalize_hex_colour($objpage->titlecolor, '000000') . ';';
    }
    $title = $objpage->showtitle ? html_writer::tag(
        'h3',
        '<i class="fa fa-hand-o-right"></i> ' . $objpage->title,
        [
            'class' => 'pagetitle',
            'style' => $titlestyle,
        ]
    ) : false;
    // Make content.
    $objpage->pageicontent = file_rewrite_pluginfile_urls(
        $objpage->pageicontent,
        'pluginfile.php',
        $context->id,
        'mod_icontent',
        'page',
        $objpage->id
    );
    $objpage->pageicontent = format_text(
        $objpage->pageicontent,
        $objpage->pageicontentformat,
        [
            'noclean' => true,
            'overflowdiv' => false,
            'context' => $context,
        ]
    );
    $objpage->pageicontent = html_writer::div($objpage->pageicontent, 'page-layout columns-' . $objpage->layout);
    // Element page number.
    $npage = html_writer::tag('div', get_string('page', 'icontent', $objpage->pagenum), ['class' => 'pagenum']);
    // Progress bar.
    $progbar = icontent_make_progessbar($objpage, $icontent, $context);
    // Go assemble the list of Questions for this slide/page.
    $qtsareas = icontent_make_questionsarea($objpage, $icontent);
    // Form notes.
    $notesarea = icontent_make_notesarea($objpage, $icontent);
    $tarea = icontent_make_page_tags_area($objpage);

    // Control button.
    $objpage->previous = icontent_get_prev_pagenum($objpage);
    $objpage->next = icontent_get_next_pagenum($objpage);
    $objpage->previouspageid = icontent_get_pageid_by_pagenum($objpage->cmid, $objpage->previous);
    $objpage->nextpageid = icontent_get_pageid_by_pagenum($objpage->cmid, $objpage->next);
    // Content page for return.
    $objpage->fullpageicontent = html_writer::tag(
        'div',
        $toolbarpage .
        $title .
        $objpage->pageicontent .
        $npage .
        $progbar .
        $qtsareas .
        $notesarea .
        $tarea .
        $script,
        [
            'class' => 'fulltextpage',
            'data-pageid' => $objpage->id,
            'data-pagenum' => $objpage->pagenum,
            'style' => icontent_get_page_style($icontent, $objpage, $context),
        ]
    );
    // Set page preview, log event and return page.
    icontent_add_pagedisplayed($objpage->id, $objpage->cmid);
    \mod_icontent\event\page_viewed::create_from_page($icontent, $context, $objpage)->trigger();
    unset($objpage->pageicontent);
    return $objpage;
}

/**
 * Returns icontent pages tagged with a specified tag.
 *
 * This is a callback used by the tag area mod_icontent/icontent_pages to search for icontent pages
 * tagged with a specific tag.
 *
 * @param core_tag_tag $tag
 * @param bool $exclusivemode if set to true it means that no other entities tagged with this tag
 *             are displayed on the page and the per-page limit may be bigger
 * @param int $fromctx context id where the link was displayed, may be used by callbacks
 *            to display items in the same context first
 * @param int $ctx context id where to search for records
 * @param bool $rec search in subcontexts as well
 * @param int $page 0-based number of page being displayed
 * @return \core_tag\output\tagindex
 */
function mod_icontent_get_tagged_pages($tag, $exclusivemode = false, $fromctx = 0, $ctx = 0, $rec = 1, $page = 0) {
    global $OUTPUT;
    $perpage = $exclusivemode ? 20 : 5;

    // Build the SQL query.
    $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
    $query = "SELECT ip.id, ip.title, ip.icontentid,
                     cm.id AS cmid, c.id AS courseid, c.shortname, c.fullname, $ctxselect
                FROM {icontent_pages} ip
                JOIN {icontent} ic ON ip.icontentid = ic.id
                JOIN {modules} m ON m.name='icontent'
                JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = ic.id
                JOIN {tag_instance} tt ON ip.id = tt.itemid
                JOIN {course} c ON cm.course = c.id
                JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :coursemodulecontextlevel
               WHERE tt.itemtype = :itemtype AND tt.tagid = :tagid AND tt.component = :component
                 AND cm.deletioninprogress = 0
                 AND ip.id %ITEMFILTER% AND c.id %COURSEFILTER%";

    $params = ['itemtype' => 'icontent_pages',
        'tagid' => $tag->id,
        'component' => 'mod_icontent',
        'coursemodulecontextlevel' => CONTEXT_MODULE,
    ];

    if ($ctx) {
        $context = $ctx ? context::instance_by_id($ctx) : context_system::instance();
        $query .= $rec ? ' AND (ctx.id = :contextid OR ctx.path LIKE :path)' : ' AND ctx.id = :contextid';
        $params['contextid'] = $context->id;
        $params['path'] = $context->path . '/%';
    }

    $query .= " ORDER BY ";
    if ($fromctx) {
        // In order-clause specify that modules from inside "fromctx" context should be returned first.
        $fromcontext = context::instance_by_id($fromctx);
        $query .= ' (CASE WHEN ctx.id = :fromcontextid OR ctx.path LIKE :frompath THEN 0 ELSE 1 END),';
        $params['fromcontextid'] = $fromcontext->id;
        $params['frompath'] = $fromcontext->path . '/%';
    }
    $query .= ' c.sortorder, cm.id, ip.id';

    $totalpages = $page + 1;

    // Use core_tag_index_builder to build and filter the list of items.
    $builder = new core_tag_index_builder('mod_icontent', 'icontent_pages', $query, $params, $page * $perpage, $perpage + 1);
    while ($item = $builder->has_item_that_needs_access_check()) {
        context_helper::preload_from_record($item);
        $courseid = $item->courseid;
        if (!$builder->can_access_course($courseid)) {
            $builder->set_accessible($item, false);
            continue;
        }
        $modinfo = get_fast_modinfo($builder->get_course($courseid));
        // Set accessibility of this item and all other items in the same course.
        $builder->walk(function ($taggeditem) use ($courseid, $modinfo, $builder) {
            if ($taggeditem->courseid == $courseid) {
                $accessible = false;
                if (($cm = $modinfo->get_cm($taggeditem->cmid)) && $cm->uservisible) {
                    // Mode and sub-content data are not needed here.
                    $icontent = (object)['id' => $taggeditem->icontentid, 'course' => $cm->course];
                    $accessible = icontent_user_can_view($subicontent, $icontent);
                }
                $builder->set_accessible($taggeditem, $accessible);
            }
        });
    }

    $items = $builder->get_items();
    if (count($items) > $perpage) {
        $totalpages = $page + 2; // We don't need exact page count, just indicate that the next page exists.
        array_pop($items);
    }

    // Build the display contents.
    if ($items) {
        $tagfeed = new core_tag\output\tagfeed();
        foreach ($items as $item) {
            context_helper::preload_from_record($item);
            $modinfo = get_fast_modinfo($item->courseid);
            $cm = $modinfo->get_cm($item->cmid);
            $pageurl = new moodle_url('/mod/icontent/view.php', ['pageid' => $item->id]);
            $pagename = format_string($item->title, true, ['context' => context_module::instance($item->cmid)]);
            $pagename = html_writer::link($pageurl, $pagename);
            $courseurl = course_get_url($item->courseid, $cm->sectionnum);
            $cmname = html_writer::link($cm->url, $cm->get_formatted_name());
            $coursename = format_string($item->fullname, true, ['context' => context_course::instance($item->courseid)]);
            $coursename = html_writer::link($courseurl, $coursename);
            $icon = html_writer::link($pageurl, html_writer::empty_tag('img', ['src' => $cm->get_icon_url()]));
            $tagfeed->add($icon, $pagename, $cmname . '<br>' . $coursename);
        }

        $content = $OUTPUT->render_from_template(
            'core_tag/tagfeed',
            $tagfeed->export_for_template($OUTPUT)
        );

        // Debug printouts intentionally removed for release code.

        return new core_tag\output\tagindex(
            $tag,
            'mod_icontent',
            'icontent_pages',
            $content,
            $exclusivemode,
            $fromctx,
            $ctx,
            $rec,
            $page,
            $totalpages
        );
    }
}
