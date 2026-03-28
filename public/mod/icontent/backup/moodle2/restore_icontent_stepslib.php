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
 * Define all the restore steps that will be used by the restore_icontent_activity_task
 *
 * @package   mod_icontent
 * @category  backup
 * @copyright 2016 Leo Renis Santos <leorenis@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step to restore one icontent activity.
 */
class restore_icontent_activity_structure_step extends restore_questions_activity_structure_step {
    /**
     * iContent restores linked question ids, but does not restore question usage records here.
     *
     * @param int $newusageid
     * @return void
     */
    protected function inform_new_usage_id($newusageid) {
        // No question usages are attached to this restore step.
    }

    /**
     * Resolve the restored course module id for this activity.
     *
     * @return int
     */
    protected function get_restored_cmid() {
        $cmid = $this->task->get_moduleid();
        if (!empty($cmid)) {
            return (int)$cmid;
        }

        $icontentid = $this->get_new_parentid('icontent');
        $cm = get_coursemodule_from_instance('icontent', $icontentid);
        return !empty($cm->id) ? (int)$cm->id : 0;
    }

    /**
     * Map a user id and fall back to the original id if no mapping exists.
     *
     * @param int $olduserid
     * @return int
     */
    protected function map_userid_with_fallback($olduserid) {
        $newuserid = $this->get_mappingid('user', $olduserid);
        return !empty($newuserid) ? (int)$newuserid : (int)$olduserid;
    }

    /**
     * Resolve a restored question id.
     *
     * First prefer backup/restore mappings. If unavailable, accept the original
     * id only when the question still exists on this site and optionally matches
     * the expected qtype from the page-question record.
     *
     * @param int $oldquestionid
     * @param string|null $expectedqtype
     * @return int
     */
    protected function resolve_questionid($oldquestionid, $expectedqtype = null) {
        global $DB;

        $newquestionid = (int)$this->get_mappingid('question', $oldquestionid);
        if (!empty($newquestionid)) {
            return $newquestionid;
        }

        $question = $DB->get_record('question', ['id' => $oldquestionid], 'id, qtype');
        if (empty($question)) {
            return 0;
        }

        if ($expectedqtype !== null && $expectedqtype !== '' && $question->qtype !== $expectedqtype) {
            return 0;
        }

        return (int)$question->id;
    }

    /**
     * Defines structure of path elements to be processed during the restore.
     *
     * @return restore_path_element $structure
     */
    protected function define_structure() {

        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');
        $paths[] = new restore_path_element(
            'icontent',
            '/activity/icontent'
        );
        $paths[] = new restore_path_element(
            'icontent_page',
            '/activity/icontent/pages/page'
        );
        $paths[] = new restore_path_element(
            'icontent_page_question',
            '/activity/icontent/pages/page/page_questions/page_question'
        );
        $paths[] = new restore_path_element(
            'icontent_page_tag',
            '/activity/icontent/pages/page/page_tags/page_tag'
        );

        if ($userinfo) {
            $paths[] = new restore_path_element(
                'icontent_page_note',
                '/activity/icontent/pages/page/pages_notes/pages_note'
            );
            $paths[] = new restore_path_element(
                'icontent_page_note_like',
                '/activity/icontent/pages/page/pages_notes/pages_note/notes_likes/notes_like'
            );
            $paths[] = new restore_path_element(
                'icontent_page_displayed',
                '/activity/icontent/pages/page/pages_displayeds/pages_displayed'
            );
            $paths[] = new restore_path_element(
                'icontent_question_attempt',
                '/activity/icontent/pages/page/page_questions/page_question/question_attempts/question_attempt'
            );
            $paths[] = new restore_path_element(
                'icontent_grade',
                '/activity/icontent/grades/grade'
            );
        }
        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process the given restore path element data.
     *
     * @param array|object $data
     * @throws base_step_exception
     * @throws dml_exception
     * @throws restore_step_exception
     */
    protected function process_icontent($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        if (empty($data->timecreated)) {
            $data->timecreated = time();
        }

        if (empty($data->timemodified)) {
            $data->timemodified = time();
        }

        $data->timeopen = $this->apply_date_offset($data->timeopen);
        $data->timeclose = $this->apply_date_offset($data->timeclose);

        if ($data->grade < 0) {
            // Scale found, get mapping.
            $data->grade = -($this->get_mappingid('scale', abs($data->grade)));
        }

        // Create the icontent instance.
        $newitemid = $DB->insert_record('icontent', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restore an icontent page.
     * @param array|object $data
     * @throws dml_exception
     * @throws restore_step_exception
     */
    protected function process_icontent_page($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->cmid = $this->get_restored_cmid();
        $data->icontentid = $this->get_new_parentid('icontent');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('icontent_pages', $data);
        $this->set_mapping('icontent_page', $oldid, $newitemid);
    }

    /**
     * Restore an icontent_page_question.
     * @param array|object $data
     * @throws dml_exception
     * @throws restore_step_exception
     */
    protected function process_icontent_page_question($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $expectedqtype = trim((string)($data->qtype ?? ''));
        if ($expectedqtype === '' || $expectedqtype === '0') {
            $expectedqtype = null;
        }

        // Try question_bank_entry mapping first (populated when questions.xml contains the questions).
        $newquestionid = 0;
        if (!empty($data->questionbankentryid)) {
            $newqbeid = (int)$this->get_mappingid('question_bank_entry', (int)$data->questionbankentryid);
            if (!empty($newqbeid)) {
                $newquestionid = (int)$DB->get_field_sql(
                    'SELECT questionid FROM {question_versions}
                      WHERE questionbankentryid = ?
                   ORDER BY version DESC LIMIT 1',
                    [$newqbeid]
                );
            }
        }

        // Fall back to direct question mapping or same-site ID fallback.
        if (empty($newquestionid)) {
            $newquestionid = $this->resolve_questionid((int)$data->questionid, $expectedqtype);
        }

        if (empty($newquestionid)) {
            return;
        }

        $data->pageid = $this->get_new_parentid('icontent_page');
        $data->questionid = $newquestionid;
        $data->cmid = $this->get_restored_cmid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('icontent_pages_questions', $data);
        $this->set_mapping('icontent_page_question', $oldid, $newitemid);
    }

    /**
     * Restore one page tag.
     *
     * @param array|object $data
     * @return void
     */
    protected function process_icontent_page_tag($data) {
        if (!core_tag_tag::is_enabled('mod_icontent', 'icontent_pages')) {
            return;
        }

        $data = (object)$data;
        $tag = trim((string)$data->rawname);
        if ($tag === '') {
            return;
        }

        $pageid = (int)$this->get_new_parentid('icontent_page');
        $context = context_module::instance((int)$this->task->get_moduleid());
        core_tag_tag::add_item_tag('mod_icontent', 'icontent_pages', $pageid, $context, $tag);
    }

    /**
     * Restore an icontent_page_note.
     * @param array|object $data
     * @throws dml_exception
     * @throws restore_step_exception
     */
    protected function process_icontent_page_note($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->pageid = $this->get_new_parentid('icontent_page');
        $data->userid = $this->map_userid_with_fallback($data->userid);
        $data->cmid = $this->get_restored_cmid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('icontent_pages_notes', $data);
        $this->set_mapping('icontent_page_note', $oldid, $newitemid);
    }

    /**
     * Restore an icontent_page_note_like.
     * @param array|object $data
     * @throws dml_exception
     * @throws restore_step_exception
     */
    protected function process_icontent_page_note_like($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->pagenoteid = $this->get_new_parentid('icontent_page_note');
        $data->userid = $this->map_userid_with_fallback($data->userid);
        $data->cmid = $this->get_restored_cmid();
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('icontent_pages_notes_like', $data);
        $this->set_mapping('icontent_page_note_like', $oldid, $newitemid);
    }

    /**
     * Restore an icontent_page_displayed.
     * @param array|object $data
     * @throws dml_exception
     * @throws restore_step_exception
     */
    protected function process_icontent_page_displayed($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->pageid = $this->get_new_parentid('icontent_page');
        $data->cmid = $this->get_restored_cmid();
        $data->userid = $this->map_userid_with_fallback($data->userid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);

        $newitemid = $DB->insert_record('icontent_pages_displayed', $data);
        $this->set_mapping('icontent_page_displayed', $oldid, $newitemid);
    }

    /**
     * Restore an icontent_page_attempt.
     * @param array|object $data
     * @throws dml_exception
     * @throws restore_step_exception
     */
    protected function process_icontent_question_attempt($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $oldresponsefileitemid = !empty($data->responsefileitemid) ? (int)$data->responsefileitemid : 0;
        unset($data->responsefileitemid);
        $data->pagesquestionsid = $this->get_new_parentid('icontent_page_question');
        if (empty($data->pagesquestionsid)) {
            return;
        }

        $mappedpagequestion = $DB->get_record(
            'icontent_pages_questions',
            ['id' => $data->pagesquestionsid],
            'questionid',
            IGNORE_MISSING
        );

        if (!empty($mappedpagequestion->questionid)) {
            $newquestionid = (int)$mappedpagequestion->questionid;
        } else {
            $newquestionid = $this->resolve_questionid((int)$data->questionid);
        }

        if (empty($newquestionid)) {
            return;
        }

        $data->questionid = $newquestionid;
        $data->userid = $this->map_userid_with_fallback($data->userid);
        $data->cmid = $this->get_restored_cmid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);

        $newitemid = $DB->insert_record('icontent_question_attempts', $data);
        $this->set_mapping('icontent_question_attempt', $oldid, $newitemid);
        if (!empty($oldresponsefileitemid)) {
            $this->set_mapping('icontent_question_attempt_responsefile', $oldresponsefileitemid, $oldresponsefileitemid, true);
        }
    }

    /**
     * Restore an icontent_grade.
     * @param array|object $data
     * @throws dml_exception
     * @throws restore_step_exception
     */
    protected function process_icontent_grade($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->icontentid = $this->get_new_parentid('icontent');
        $data->userid = $this->map_userid_with_fallback($data->userid);
        $data->cmid = $this->get_restored_cmid();
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('icontent_grades', $data);
        $this->set_mapping('icontent_grade', $oldid, $newitemid);
    }

    /**
     * Post-execution actions
     */
    protected function after_execute() {
        // Add icontent related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_icontent', 'intro', null);

        // Add page related files, matching by itemname = 'icontent_page'.
        $this->add_related_files('mod_icontent', 'page', 'icontent_page');
        $this->add_related_files('mod_icontent', 'bgpage', 'icontent_page');
        $this->add_related_files('question', 'response_answer', 'icontent_question_attempt_responsefile');
    }
}
