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
 * Behat steps for mod_icontent.
 *
 * @package    mod_icontent
 * @category   test
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * iContent Behat context.
 */
class behat_mod_icontent extends behat_base {

    /**
     * Resolve an iContent course module by activity name.
     *
     * @param string $activityname
     * @return stdClass
     */
    protected function get_icontent_cm_by_name(string $activityname): stdClass {
        global $DB;

        $sql = "SELECT cm.*
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {icontent} i ON i.id = cm.instance
                 WHERE m.name = :modname
                   AND i.name = :activityname";

        return $DB->get_record_sql($sql, ['modname' => 'icontent', 'activityname' => $activityname], MUST_EXIST);
    }

    /**
     * Resolve an iContent page by activity and page title.
     *
     * @param string $activityname
     * @param string $pagetitle
     * @return stdClass
     */
    protected function get_icontent_page_by_title(string $activityname, string $pagetitle): stdClass {
        global $DB;

        $cm = $this->get_icontent_cm_by_name($activityname);
        $icontent = $DB->get_record('icontent', ['id' => $cm->instance], '*', MUST_EXIST);

        return $DB->get_record('icontent_pages', [
            'icontentid' => $icontent->id,
            'cmid' => $cm->id,
            'title' => $pagetitle,
        ], '*', MUST_EXIST);
    }

    /**
     * Create a simple iContent page for a named activity.
     *
     * @Given /^the icontent "(?P<activity_string>(?:[^"\\]|\\.)*)" has a page titled "(?P<title_string>(?:[^"\\]|\\.)*)" with content "(?P<content_string>(?:[^"\\]|\\.)*)"$/
     *
     * @param string $activity
     * @param string $title
     * @param string $content
     */
    public function the_icontent_has_a_page_titled_with_content(string $activity, string $title, string $content): void {
        global $DB;

        $cm = $this->get_icontent_cm_by_name($activity);
        $icontent = $DB->get_record('icontent', ['id' => $cm->instance], '*', MUST_EXIST);

        $maxpagenum = (int)$DB->get_field_sql(
            'SELECT COALESCE(MAX(pagenum), 0) FROM {icontent_pages} WHERE icontentid = ?',
            [$icontent->id]
        );
        $timecreated = time();

        $record = (object)[
            'icontentid' => $icontent->id,
            'cmid' => $cm->id,
            'coverpage' => 0,
            'title' => $title,
            'showtitle' => 1,
            'pageicontent' => $content,
            'pageicontentformat' => FORMAT_HTML,
            'showbgimage' => 0,
            'bgimage' => null,
            'bgcolor' => $icontent->bgcolor ?? '#FCFCFC',
            'layout' => 1,
            'transitioneffect' => '0',
            'bordercolor' => $icontent->bordercolor ?? '#E4E4E4',
            'borderwidth' => (int)($icontent->borderwidth ?? 1),
            'pagenum' => $maxpagenum + 1,
            'hidden' => 0,
            'maxnotesperpages' => (int)($icontent->maxnotesperpages ?? 15),
            'attemptsallowed' => 0,
            'expandnotesarea' => 0,
            'expandquestionsarea' => 0,
            'timecreated' => $timecreated,
            'timemodified' => $timecreated,
        ];

        $DB->insert_record('icontent_pages', $record);
    }

    /**
     * Navigate to the iContent manual review page by activity name.
     *
     * @Given /^I am on the "(?P<activity_string>(?:[^"\\]|\\.)*)" icontent manual review page$/
     *
     * @param string $activity
     */
    public function i_am_on_the_icontent_manual_review_page(string $activity): void {
        $cm = $this->get_icontent_cm_by_name($activity);
        $url = new moodle_url('/mod/icontent/grading.php', ['id' => $cm->id, 'action' => 'grading']);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
    }

    /**
     * Link an existing question to an iContent page and create an evaluated attempt with reviewer comment.
     *
     * @Given /^the icontent "(?P<activity_string>(?:[^"\\]|\\.)*)" page "(?P<pagetitle_string>(?:[^"\\]|\\.)*)" links question "(?P<questionname_string>(?:[^"\\]|\\.)*)" and has an evaluated attempt for "(?P<username_string>(?:[^"\\]|\\.)*)" with answer "(?P<answer_string>(?:[^"\\]|\\.)*)" and teacher comment "(?P<comment_string>(?:[^"\\]|\\.)*)"$/
     *
     * @param string $activity
     * @param string $pagetitle
     * @param string $questionname
     * @param string $username
     * @param string $answer
     * @param string $comment
     */
    public function the_icontent_page_links_question_with_evaluated_attempt(
        string $activity,
        string $pagetitle,
        string $questionname,
        string $username,
        string $answer,
        string $comment
    ): void {
        global $DB;

        $cm = $this->get_icontent_cm_by_name($activity);
        $page = $this->get_icontent_page_by_title($activity, $pagetitle);
        $question = $DB->get_record('question', ['name' => $questionname], '*', MUST_EXIST);
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);

        $timecreated = time();

        $pagesquestion = $DB->get_record('icontent_pages_questions', [
            'pageid' => $page->id,
            'questionid' => $question->id,
            'cmid' => $cm->id,
        ]);

        if (!$pagesquestion) {
            $pagesquestion = (object)[
                'pageid' => $page->id,
                'questionid' => $question->id,
                'cmid' => $cm->id,
                'timecreated' => $timecreated,
                'timemodified' => $timecreated,
                'maxmark' => 1,
                'remake' => 0,
                'qtype' => $question->qtype,
            ];
            $pagesquestion->id = $DB->insert_record('icontent_pages_questions', $pagesquestion);
        }

        $attempt = (object)[
            'pagesquestionsid' => $pagesquestion->id,
            'questionid' => $question->id,
            'userid' => $user->id,
            'cmid' => $cm->id,
            'fraction' => 1,
            'rightanswer' => 'evaluated',
            'answertext' => $answer,
            'reviewercomment' => $comment,
            'reviewercommentformat' => FORMAT_HTML,
            'timecreated' => $timecreated,
        ];
        $DB->insert_record('icontent_question_attempts', $attempt);
    }

    /**
     * Link an existing question to an iContent page and create a pending manual-review attempt.
     *
     * @Given /^the icontent "(?P<activity_string>(?:[^"\\]|\\.)*)" page "(?P<pagetitle_string>(?:[^"\\]|\\.)*)" links question "(?P<questionname_string>(?:[^"\\]|\\.)*)" and has a pending attempt for "(?P<username_string>(?:[^"\\]|\\.)*)" with answer "(?P<answer_string>(?:[^"\\]|\\.)*)"$/
     *
     * @param string $activity
     * @param string $pagetitle
     * @param string $questionname
     * @param string $username
     * @param string $answer
     */
    public function the_icontent_page_links_question_with_pending_attempt(
        string $activity,
        string $pagetitle,
        string $questionname,
        string $username,
        string $answer
    ): void {
        global $DB;

        $cm = $this->get_icontent_cm_by_name($activity);
        $page = $this->get_icontent_page_by_title($activity, $pagetitle);
        $question = $DB->get_record('question', ['name' => $questionname], '*', MUST_EXIST);
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);

        $timecreated = time();

        $pagesquestion = $DB->get_record('icontent_pages_questions', [
            'pageid' => $page->id,
            'questionid' => $question->id,
            'cmid' => $cm->id,
        ]);

        if (!$pagesquestion) {
            $pagesquestion = (object)[
                'pageid' => $page->id,
                'questionid' => $question->id,
                'cmid' => $cm->id,
                'timecreated' => $timecreated,
                'timemodified' => $timecreated,
                'maxmark' => 1,
                'remake' => 0,
                'qtype' => $question->qtype,
            ];
            $pagesquestion->id = $DB->insert_record('icontent_pages_questions', $pagesquestion);
        }

        $attempt = (object)[
            'pagesquestionsid' => $pagesquestion->id,
            'questionid' => $question->id,
            'userid' => $user->id,
            'cmid' => $cm->id,
            'fraction' => 0,
            'rightanswer' => 'toevaluate',
            'answertext' => $answer,
            'reviewercomment' => '',
            'reviewercommentformat' => FORMAT_HTML,
            'timecreated' => $timecreated,
        ];
        $DB->insert_record('icontent_question_attempts', $attempt);
    }

    /**
     * Link an existing question to an iContent page and create an auto-graded attempt.
     *
     * @Given /^the icontent "(?P<activity_string>(?:[^"\\]|\\.)*)" page "(?P<pagetitle_string>(?:[^"\\]|\\.)*)" links question "(?P<questionname_string>(?:[^"\\]|\\.)*)" and has a graded attempt for "(?P<username_string>(?:[^"\\]|\\.)*)" with answer "(?P<answer_string>(?:[^"\\]|\\.)*)"$/
     *
     * @param string $activity
     * @param string $pagetitle
     * @param string $questionname
     * @param string $username
     * @param string $answer
     */
    public function the_icontent_page_links_question_with_graded_attempt(
        string $activity,
        string $pagetitle,
        string $questionname,
        string $username,
        string $answer
    ): void {
        global $DB;

        $cm = $this->get_icontent_cm_by_name($activity);
        $page = $this->get_icontent_page_by_title($activity, $pagetitle);
        $question = $DB->get_record('question', ['name' => $questionname], '*', MUST_EXIST);
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);

        $timecreated = time();

        $pagesquestion = $DB->get_record('icontent_pages_questions', [
            'pageid' => $page->id,
            'questionid' => $question->id,
            'cmid' => $cm->id,
        ]);

        if (!$pagesquestion) {
            $pagesquestion = (object)[
                'pageid' => $page->id,
                'questionid' => $question->id,
                'cmid' => $cm->id,
                'timecreated' => $timecreated,
                'timemodified' => $timecreated,
                'maxmark' => 1,
                'remake' => 0,
                'qtype' => $question->qtype,
            ];
            $pagesquestion->id = $DB->insert_record('icontent_pages_questions', $pagesquestion);
        }

        $attempt = (object)[
            'pagesquestionsid' => $pagesquestion->id,
            'questionid' => $question->id,
            'userid' => $user->id,
            'cmid' => $cm->id,
            'fraction' => 1,
            'rightanswer' => 'correct',
            'answertext' => $answer,
            'reviewercomment' => '',
            'reviewercommentformat' => FORMAT_HTML,
            'timecreated' => $timecreated,
        ];
        $DB->insert_record('icontent_question_attempts', $attempt);
    }

    /**
     * Link an existing question to an iContent page without creating attempts.
     *
     * @Given /^the icontent "(?P<activity_string>(?:[^"\\]|\\.)*)" page "(?P<pagetitle_string>(?:[^"\\]|\\.)*)" links question "(?P<questionname_string>(?:[^"\\]|\\.)*)"$/
     *
     * @param string $activity
     * @param string $pagetitle
     * @param string $questionname
     */
    public function the_icontent_page_links_question(
        string $activity,
        string $pagetitle,
        string $questionname
    ): void {
        global $DB;

        $cm = $this->get_icontent_cm_by_name($activity);
        $page = $this->get_icontent_page_by_title($activity, $pagetitle);
        $question = $DB->get_record('question', ['name' => $questionname], '*', MUST_EXIST);

        $exists = $DB->record_exists('icontent_pages_questions', [
            'pageid' => $page->id,
            'questionid' => $question->id,
            'cmid' => $cm->id,
        ]);

        if ($exists) {
            return;
        }

        $timecreated = time();
        $DB->insert_record('icontent_pages_questions', (object)[
            'pageid' => $page->id,
            'questionid' => $question->id,
            'cmid' => $cm->id,
            'timecreated' => $timecreated,
            'timemodified' => $timecreated,
            'maxmark' => 1,
            'remake' => 0,
            'qtype' => $question->qtype,
        ]);
    }

    /**
     * Remove a linked question from an iContent page as the current user.
     *
     * @When /^I remove question "(?P<questionname_string>(?:[^"\\]|\\.)*)" from page "(?P<pagetitle_string>(?:[^"\\]|\\.)*)" in icontent "(?P<activity_string>(?:[^"\\]|\\.)*)"$/
     *
     * @param string $questionname
     * @param string $pagetitle
     * @param string $activity
     */
    public function i_remove_question_from_page_in_icontent(
        string $questionname,
        string $pagetitle,
        string $activity
    ): void {
        global $DB;

        $cm = $this->get_icontent_cm_by_name($activity);
        $page = $this->get_icontent_page_by_title($activity, $pagetitle);
        $question = $DB->get_record('question', ['name' => $questionname], '*', MUST_EXIST);

        $mapping = $DB->get_record('icontent_pages_questions', [
            'pageid' => $page->id,
            'questionid' => $question->id,
            'cmid' => $cm->id,
        ], '*', MUST_EXIST);

        $url = new moodle_url('/mod/icontent/view.php', [
            'id' => $cm->id,
            'pageid' => $page->id,
            'removeqpid' => $mapping->id,
            'sesskey' => sesskey(),
        ]);

        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
    }

    /**
     * Assert that an iContent activity includes at least one linked question of a given qtype.
     *
     * @Then /^the icontent "(?P<activity_string>(?:[^"\\]|\\.)*)" should include question type "(?P<qtype_string>(?:[^"\\]|\\.)*)"$/
     *
     * @param string $activity
     * @param string $qtype
     */
    public function the_icontent_should_include_question_type(string $activity, string $qtype): void {
        global $DB;

        $cm = $this->get_icontent_cm_by_name($activity);

        $sql = "SELECT 1
                  FROM {icontent_pages_questions} pq
                  JOIN {question} q ON q.id = pq.questionid
                 WHERE pq.cmid = ?
                   AND q.qtype = ?";
        $exists = $DB->record_exists_sql($sql, [$cm->id, $qtype]);

        if (!$exists) {
            throw new ExpectationException(
                'iContent activity "' . $activity . '" does not include question type "' . $qtype . '".',
                $this->getSession()
            );
        }
    }

    /**
     * Assert that an iContent activity includes a specific page/question/qtype mapping.
     *
     * @Then /^the icontent "(?P<activity_string>(?:[^"\\]|\\.)*)" should include page "(?P<pagetitle_string>(?:[^"\\]|\\.)*)" with question "(?P<questionname_string>(?:[^"\\]|\\.)*)" of type "(?P<qtype_string>(?:[^"\\]|\\.)*)"$/
     *
     * @param string $activity
     * @param string $pagetitle
     * @param string $questionname
     * @param string $qtype
     */
    public function the_icontent_should_include_page_with_question_of_type(
        string $activity,
        string $pagetitle,
        string $questionname,
        string $qtype
    ): void {
        global $DB;

        $cm = $this->get_icontent_cm_by_name($activity);

        $sql = "SELECT 1
                  FROM {icontent_pages} p
                  JOIN {icontent_pages_questions} pq ON pq.pageid = p.id
                  JOIN {question} q ON q.id = pq.questionid
                 WHERE p.cmid = ?
                   AND p.title = ?
                   AND q.name = ?
                   AND q.qtype = ?";
        $exists = $DB->record_exists_sql($sql, [$cm->id, $pagetitle, $questionname, $qtype]);

        if (!$exists) {
            throw new ExpectationException(
                'Expected mapping not found in iContent "' . $activity . '": page "' . $pagetitle .
                '", question "' . $questionname . '", qtype "' . $qtype . '".',
                $this->getSession()
            );
        }
    }

    /**
     * Assert that an iContent activity does not include a specific page/question mapping.
     *
     * @Then /^the icontent "(?P<activity_string>(?:[^"\\]|\\.)*)" should not include page "(?P<pagetitle_string>(?:[^"\\]|\\.)*)" with question "(?P<questionname_string>(?:[^"\\]|\\.)*)"$/
     *
     * @param string $activity
     * @param string $pagetitle
     * @param string $questionname
     */
    public function the_icontent_should_not_include_page_with_question(
        string $activity,
        string $pagetitle,
        string $questionname
    ): void {
        global $DB;

        $cm = $this->get_icontent_cm_by_name($activity);

        $sql = "SELECT 1
                  FROM {icontent_pages} p
                  JOIN {icontent_pages_questions} pq ON pq.pageid = p.id
                  JOIN {question} q ON q.id = pq.questionid
                 WHERE p.cmid = ?
                   AND p.title = ?
                   AND q.name = ?";
        $exists = $DB->record_exists_sql($sql, [$cm->id, $pagetitle, $questionname]);

        if ($exists) {
            throw new ExpectationException(
                'Unexpected mapping exists in iContent "' . $activity . '": page "' . $pagetitle .
                '", question "' . $questionname . '".',
                $this->getSession()
            );
        }
    }
}
