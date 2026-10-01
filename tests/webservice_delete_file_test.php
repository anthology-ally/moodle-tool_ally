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
 * Test for file delete webservice.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2017 Open LMS (https://www.openlms.net) / 2023 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_ally;

use tool_ally\abstract_testcase;
use tool_ally\webservice\delete_file;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/abstract_testcase.php');

/**
 * Test for file delete webservice.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2017 Open LMS (https://www.openlms.net) / 2023 Anthology Inc. and its affiliates
 * @group     tool_ally
 * @group     ally
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @runTestsInSeparateProcesses
 */
final class webservice_delete_file_test extends abstract_testcase {
    /**
     * Test the web service.
     *
     * @covers \tool_ally\webservice\delete_file::service
     */
    public function test_service(): void {
        global $DB;

        $this->resetAfterTest();

        $datagen = $this->getDataGenerator();

        $roleid = $this->assignUserCapability('moodle/course:view', \context_system::instance()->id);
        $this->assignUserCapability('moodle/course:viewhiddencourses', \context_system::instance()->id, $roleid);
        $this->assignUserCapability('moodle/course:managefiles', \context_system::instance()->id, $roleid);

        $teacher = $datagen->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);

        $course      = $datagen->create_course();
        $resource    = $datagen->create_module('resource', ['course' => $course->id]);
        $file        = $this->get_resource_file($resource);

        $datagen->enrol_user($teacher->id, $course->id, $teacherrole->id);

        $return = delete_file::service($file->get_pathnamehash(), $teacher->id);
        $return = \external_api::clean_returnvalue(delete_file::service_returns(), $return);

        $this->assertSame($return['success'], true);

        // Fetching the new deleted file throws an exception.
        $this->expectException(\coding_exception::class);
        $this->get_resource_file($resource);
    }

    /**
     * Test service invalid user.
     *
     * @covers \tool_ally\webservice\delete_file::service
     */
    public function test_service_invalid_user(): void {
        $this->resetAfterTest();

        $roleid = $this->assignUserCapability('moodle/course:view', \context_system::instance()->id);
        $this->assignUserCapability('moodle/course:viewhiddencourses', \context_system::instance()->id, $roleid);
        $this->assignUserCapability('moodle/course:managefiles', \context_system::instance()->id, $roleid);

        $otheruser = $this->getDataGenerator()->create_user();

        $course      = $this->getDataGenerator()->create_course();
        $resource    = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
        $file        = $this->get_resource_file($resource);

        $this->expectException(\moodle_exception::class);
        $return = delete_file::service($file->get_pathnamehash(), $otheruser->id);
        $return = \external_api::clean_returnvalue(delete_file::service_returns(), $return);

        // Check file hasn't been deleted.
        $this->assertInstanceOf(\stored_file, $this->get_resource_file($resource));
    }

    /**
     * Test service invalid file.
     *
     * @covers \tool_ally\webservice\delete_file::service
     */
    public function test_service_invalid_file(): void {
        global $DB;

        $this->resetAfterTest();

        $datagen = $this->getDataGenerator();

        $roleid = $this->assignUserCapability('moodle/course:view', \context_system::instance()->id);
        $this->assignUserCapability('moodle/course:viewhiddencourses', \context_system::instance()->id, $roleid);
        $this->assignUserCapability('moodle/course:managefiles', \context_system::instance()->id, $roleid);

        $teacher = $datagen->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);

        $course      = $datagen->create_course();

        $datagen->enrol_user($teacher->id, $course->id, $teacherrole->id);
        $nonexistantfile = 'BADC0FFEE';
        $this->expectException(\moodle_exception::class);
        delete_file::service($nonexistantfile, $teacher->id);
    }

    /**
     * Set up a course with a teacher who is allowed to delete files.
     *
     * @return array [course, teacher]
     */
    private function setup_course_and_teacher(): array {
        $datagen = $this->getDataGenerator();

        $roleid = $this->assignUserCapability('moodle/course:view', \context_system::instance()->id);
        $this->assignUserCapability('moodle/course:viewhiddencourses', \context_system::instance()->id, $roleid);
        $this->assignUserCapability('moodle/course:managefiles', \context_system::instance()->id, $roleid);

        $teacher = $datagen->create_user();
        $course = $datagen->create_course();
        $datagen->enrol_user($teacher->id, $course->id, 'editingteacher');

        return [$course, $teacher];
    }

    /**
     * Deleting an embedded file has to take its img element out of the module intro, otherwise the
     * content is left pointing at a file which no longer exists.
     *
     * @covers \tool_ally\webservice\delete_file::service
     */
    public function test_service_label_html(): void {
        global $DB;

        $this->resetAfterTest();

        [$course, $teacher] = $this->setup_course_and_teacher();

        $label = $this->getDataGenerator()->create_module('label', ['course' => $course->id]);
        $context = \context_module::instance($label->cmid);

        $file = $this->create_test_file($context->id, 'mod_label', 'intro');
        $this->create_test_file($context->id, 'mod_label', 'intro', 0, 'kept logo.png');

        $DB->update_record('label', (object) [
            'id'    => $label->id,
            'intro' => '<p>Test label text' .
                '<img src="@@PLUGINFILE@@/gd%20logo.png" alt="" width="100" height="100">' .
                '<img src="@@PLUGINFILE@@/kept%20logo.png" alt="" width="100" height="100"></p>',
        ]);

        $return = delete_file::service($file->get_pathnamehash(), $teacher->id);
        $return = \external_api::clean_returnvalue(delete_file::service_returns(), $return);

        $this->assertTrue($return['success']);

        $intro = $DB->get_field('label', 'intro', ['id' => $label->id]);
        $this->assertStringNotContainsString('gd%20logo.png', $intro);
        $this->assertStringContainsString('kept%20logo.png', $intro);
        $this->assertStringContainsString('Test label text', $intro);
    }

    /**
     * An image linking to its own file would otherwise be left behind as an empty anchor.
     *
     * @covers \tool_ally\webservice\delete_file::service
     */
    public function test_service_label_html_linked_image(): void {
        global $DB;

        $this->resetAfterTest();

        [$course, $teacher] = $this->setup_course_and_teacher();

        $label = $this->getDataGenerator()->create_module('label', ['course' => $course->id]);
        $context = \context_module::instance($label->cmid);

        $file = $this->create_test_file($context->id, 'mod_label', 'intro');

        $DB->update_record('label', (object) [
            'id'    => $label->id,
            'intro' => '<p><a href="@@PLUGINFILE@@/gd%20logo.png">' .
                '<img src="@@PLUGINFILE@@/gd%20logo.png" alt=""></a></p>',
        ]);

        $return = delete_file::service($file->get_pathnamehash(), $teacher->id);
        $return = \external_api::clean_returnvalue(delete_file::service_returns(), $return);

        $this->assertTrue($return['success']);

        $this->assertSame('', $DB->get_field('label', 'intro', ['id' => $label->id]));
    }

    /**
     * Modules without Ally component support still hold embedded images in their intro.
     *
     * @covers \tool_ally\webservice\delete_file::service
     */
    public function test_service_unsupported_module_html(): void {
        global $DB;

        $this->resetAfterTest();

        [$course, $teacher] = $this->setup_course_and_teacher();

        $url = $this->getDataGenerator()->create_module('url', ['course' => $course->id]);
        $context = \context_module::instance($url->cmid);

        $file = $this->create_test_file($context->id, 'mod_url', 'intro');

        $DB->set_field(
            'url',
            'intro',
            '<p>Url text<img src="@@PLUGINFILE@@/gd%20logo.png" alt=""></p>',
            ['id' => $url->id]
        );

        $return = delete_file::service($file->get_pathnamehash(), $teacher->id);
        $return = \external_api::clean_returnvalue(delete_file::service_returns(), $return);

        $this->assertTrue($return['success']);

        $this->assertSame('<p>Url text</p>', $DB->get_field('url', 'intro', ['id' => $url->id]));
    }

    /**
     * Test removal of an embedded file from a course section summary.
     *
     * @covers \tool_ally\webservice\delete_file::service
     */
    public function test_service_course_section_html(): void {
        global $DB;

        $this->resetAfterTest();

        [$course, $teacher] = $this->setup_course_and_teacher();

        $context = \context_course::instance($course->id);
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1], '*', MUST_EXIST);

        $file = $this->create_test_file($context->id, 'course', 'section', $section->id);

        $DB->set_field(
            'course_sections',
            'summary',
            '<p>Section text<img src="@@PLUGINFILE@@/gd%20logo.png" alt=""></p>',
            ['id' => $section->id]
        );

        $return = delete_file::service($file->get_pathnamehash(), $teacher->id);
        $return = \external_api::clean_returnvalue(delete_file::service_returns(), $return);

        $this->assertTrue($return['success']);

        $summary = $DB->get_field('course_sections', 'summary', ['id' => $section->id]);
        $this->assertSame('<p>Section text</p>', $summary);
    }

    /**
     * Html with some text and the embedded test file.
     *
     * @param string $text
     * @return string
     */
    private function img_html(string $text): string {
        return '<p>' . $text . '<img src="@@PLUGINFILE@@/gd%20logo.png" alt="" width="100" height="100"></p>';
    }

    /**
     * Delete a file through the service and check it succeeded.
     *
     * @param \stored_file $file
     * @param \stdClass $user
     */
    private function delete_file(\stored_file $file, \stdClass $user): void {
        $return = delete_file::service($file->get_pathnamehash(), $user->id);
        $return = \external_api::clean_returnvalue(delete_file::service_returns(), $return);
        $this->assertTrue($return['success']);
    }

    /**
     * Test removal of an embedded file from the course summary.
     *
     * @covers \tool_ally\webservice\delete_file::service
     */
    public function test_service_course_html(): void {
        global $DB;

        $this->resetAfterTest();

        [$course, $teacher] = $this->setup_course_and_teacher();

        $context = \context_course::instance($course->id);
        $file = $this->create_test_file($context->id, 'course', 'summary');
        $DB->set_field('course', 'summary', $this->img_html('Course text'), ['id' => $course->id]);

        $this->delete_file($file, $teacher);

        $this->assertSame('<p>Course text</p>', $DB->get_field('course', 'summary', ['id' => $course->id]));
    }

    /**
     * Test removal of an embedded file from a html block.
     *
     * @covers \tool_ally\webservice\delete_file::service
     */
    public function test_service_block_html(): void {
        global $DB;

        $this->resetAfterTest();

        [$course, $teacher] = $this->setup_course_and_teacher();

        $configdata = (object) [
            'text' => '<p>Block text<img src="@@PLUGINFILE@@/gd logo.png" alt=""></p>',
            'title' => 'test block',
            'format' => FORMAT_HTML,
        ];
        $blockid = $DB->insert_record('block_instances', (object) [
            'blockname' => 'html',
            'parentcontextid' => \context_course::instance($course->id)->id,
            'pagetypepattern' => 'course-view-*',
            'defaultregion' => 'side-pre',
            'defaultweight' => 1,
            'configdata' => base64_encode(serialize($configdata)),
            'showinsubcontexts' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $context = \context_block::instance($blockid);
        $file = $this->create_test_file($context->id, 'block_html', 'content');

        $this->delete_file($file, $teacher);

        $config = unserialize(base64_decode($DB->get_field('block_instances', 'configdata', ['id' => $blockid])));
        $this->assertSame('<p>Block text</p>', $config->text);
    }

    /**
     * Deleting a page intro file must only touch the intro, and vice versa for content.
     *
     * @covers \tool_ally\webservice\delete_file::service
     */
    public function test_service_page_html(): void {
        global $DB;

        $this->resetAfterTest();

        [$course, $teacher] = $this->setup_course_and_teacher();

        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $context = \context_module::instance($page->cmid);

        $introfile = $this->create_test_file($context->id, 'mod_page', 'intro');
        $contentfile = $this->create_test_file($context->id, 'mod_page', 'content');

        $DB->update_record('page', (object) [
            'id' => $page->id,
            'intro' => $this->img_html('Intro text'),
            'content' => $this->img_html('Content text'),
        ]);

        $this->delete_file($introfile, $teacher);

        $row = $DB->get_record('page', ['id' => $page->id]);
        $this->assertSame('<p>Intro text</p>', $row->intro);
        $this->assertSame($this->img_html('Content text'), $row->content);

        $this->delete_file($contentfile, $teacher);

        $this->assertSame('<p>Content text</p>', $DB->get_field('page', 'content', ['id' => $page->id]));
    }

    /**
     * Test removal of embedded files from forum intro, discussion and reply posts.
     *
     * @param string $forumtype
     * @covers \tool_ally\webservice\delete_file::service
     */
    public function test_service_forum_html($forumtype = 'forum'): void {
        global $DB;

        $this->resetAfterTest();

        [$course, $teacher] = $this->setup_course_and_teacher();

        $poststable = $forumtype . '_posts';

        $forum = $this->getDataGenerator()->create_module($forumtype, ['course' => $course->id]);
        $context = \context_module::instance($forum->cmid);
        $forumfile = $this->create_test_file($context->id, 'mod_' . $forumtype, 'intro');
        $DB->set_field($forumtype, 'intro', $this->img_html('Forum text'), ['id' => $forum->id]);

        $fdg = $this->getDataGenerator()->get_plugin_generator('mod_' . $forumtype);
        $discussion = $fdg->create_discussion((object) [
            'course' => $course->id,
            'userid' => $teacher->id,
            'forum' => $forum->id,
        ]);
        $discussionpost = $DB->get_record($poststable, ['discussion' => $discussion->id]);
        $discussionfile = $this->create_test_file($context->id, 'mod_' . $forumtype, 'post', $discussionpost->id);
        $DB->set_field($poststable, 'message', $this->img_html('Discussion text'), ['id' => $discussionpost->id]);

        $reply = $fdg->create_post((object) [
            'discussion' => $discussion->id,
            'parent' => $discussionpost->id,
            'userid' => $teacher->id,
        ]);
        $replyfile = $this->create_test_file($context->id, 'mod_' . $forumtype, 'post', $reply->id);
        $DB->set_field($poststable, 'message', $this->img_html('Reply text'), ['id' => $reply->id]);

        $this->delete_file($forumfile, $teacher);

        $this->assertSame('<p>Forum text</p>', $DB->get_field($forumtype, 'intro', ['id' => $forum->id]));
        $this->assertSame(
            $this->img_html('Discussion text'),
            $DB->get_field($poststable, 'message', ['id' => $discussionpost->id])
        );
        $this->assertSame($this->img_html('Reply text'), $DB->get_field($poststable, 'message', ['id' => $reply->id]));

        $this->delete_file($discussionfile, $teacher);

        $this->assertSame(
            '<p>Discussion text</p>',
            $DB->get_field($poststable, 'message', ['id' => $discussionpost->id])
        );
        $this->assertSame($this->img_html('Reply text'), $DB->get_field($poststable, 'message', ['id' => $reply->id]));

        $this->delete_file($replyfile, $teacher);

        $this->assertSame('<p>Reply text</p>', $DB->get_field($poststable, 'message', ['id' => $reply->id]));
    }

    /**
     * Test removal of embedded files from hsuforum intro, discussion and reply posts.
     *
     * @covers \tool_ally\webservice\delete_file::service
     */
    public function test_service_hsuforum_html(): void {
        global $CFG;

        if (!file_exists($CFG->dirroot . '/mod/hsuforum')) {
            $this->markTestSkipped('mod_hsuforum is not installed');
        }
        $this->test_service_forum_html('hsuforum');
    }

    /**
     * Test removal of embedded files from glossary intro and entries.
     *
     * @covers \tool_ally\webservice\delete_file::service
     */
    public function test_service_glossary_html(): void {
        global $DB;

        $this->resetAfterTest();

        [$course, $teacher] = $this->setup_course_and_teacher();

        $this->setAdminUser();
        $glossary = $this->getDataGenerator()->create_module('glossary', ['course' => $course->id]);
        $context = \context_module::instance($glossary->cmid);

        $introfile = $this->create_test_file($context->id, 'mod_glossary', 'intro');
        $DB->set_field('glossary', 'intro', $this->img_html('Glossary text'), ['id' => $glossary->id]);

        $entry = $this->getDataGenerator()->get_plugin_generator('mod_glossary')->create_content($glossary);
        $entryfile = $this->create_test_file($context->id, 'mod_glossary', 'entry', $entry->id);
        $DB->set_field('glossary_entries', 'definition', $this->img_html('Entry text'), ['id' => $entry->id]);

        $this->delete_file($introfile, $teacher);

        $this->assertSame('<p>Glossary text</p>', $DB->get_field('glossary', 'intro', ['id' => $glossary->id]));
        $this->assertSame($this->img_html('Entry text'), $DB->get_field('glossary_entries', 'definition', ['id' => $entry->id]));

        $this->delete_file($entryfile, $teacher);

        $this->assertSame('<p>Entry text</p>', $DB->get_field('glossary_entries', 'definition', ['id' => $entry->id]));
    }

    /**
     * Test removal of embedded files from lesson intro and pages.
     *
     * @covers \tool_ally\webservice\delete_file::service
     */
    public function test_service_lesson_html(): void {
        global $DB;

        $this->resetAfterTest();

        [$course, $teacher] = $this->setup_course_and_teacher();

        $lesson = $this->getDataGenerator()->create_module('lesson', ['course' => $course->id]);
        $context = \context_module::instance($lesson->cmid);

        $introfile = $this->create_test_file($context->id, 'mod_lesson', 'intro');
        $DB->set_field('lesson', 'intro', $this->img_html('Lesson text'), ['id' => $lesson->id]);

        $page = $this->getDataGenerator()->get_plugin_generator('mod_lesson')
            ->create_content($lesson, ['title' => 'Simple page']);
        $pagefile = $this->create_test_file($context->id, 'mod_lesson', 'page_contents', $page->id);
        $DB->set_field('lesson_pages', 'contents', $this->img_html('Page text'), ['id' => $page->id]);

        $this->delete_file($introfile, $teacher);

        $this->assertSame('<p>Lesson text</p>', $DB->get_field('lesson', 'intro', ['id' => $lesson->id]));
        $this->assertSame($this->img_html('Page text'), $DB->get_field('lesson_pages', 'contents', ['id' => $page->id]));

        $this->delete_file($pagefile, $teacher);

        $this->assertSame('<p>Page text</p>', $DB->get_field('lesson_pages', 'contents', ['id' => $page->id]));
    }

    /**
     * Test removal of embedded files from question text, combined feedback and answers.
     *
     * @covers \tool_ally\webservice\delete_file::service
     */
    public function test_service_question_html(): void {
        global $DB;

        $this->resetAfterTest();

        [$course, $teacher] = $this->setup_course_and_teacher();

        $qgen = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $qgen->create_question_category();
        $question = $qgen->create_question('multichoice', null, ['category' => $cat->id]);
        $context = \context_course::instance($course->id);

        $DB->set_field('question', 'questiontext', $this->img_html('Question text'), ['id' => $question->id]);
        $DB->set_field('question', 'generalfeedback', $this->img_html('General'), ['id' => $question->id]);
        foreach (['correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'] as $fbfield) {
            $DB->set_field('qtype_multichoice_options', $fbfield, $this->img_html($fbfield),
                ['questionid' => $question->id]);
        }

        $answer = (object) [
            'question' => $question->id,
            'answer' => $this->img_html('Answer'),
            'answerformat' => FORMAT_HTML,
            'fraction' => 0,
            'feedback' => $this->img_html('Feedback'),
            'feedbackformat' => FORMAT_HTML,
        ];
        $ans1id = $DB->insert_record('question_answers', $answer);
        $ans2id = $DB->insert_record('question_answers', $answer);

        $questionfile = $this->create_test_file($context->id, 'question', 'questiontext', $question->id);
        $generalfile = $this->create_test_file($context->id, 'question', 'generalfeedback', $question->id);
        $answerfile = $this->create_test_file($context->id, 'question', 'answer', $ans1id);
        $feedbackfile = $this->create_test_file($context->id, 'question', 'answerfeedback', $ans1id);

        $this->delete_file($questionfile, $teacher);
        $row = $DB->get_record('question', ['id' => $question->id]);
        $this->assertSame('<p>Question text</p>', $row->questiontext);
        $this->assertSame($this->img_html('General'), $row->generalfeedback);

        $this->delete_file($generalfile, $teacher);
        $this->assertSame('<p>General</p>', $DB->get_field('question', 'generalfeedback', ['id' => $question->id]));

        // Each combined feedback field has its own file area, so only that field may change.
        $fbfields = ['correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'];
        foreach ($fbfields as $i => $fbfield) {
            $this->delete_file($this->create_test_file($context->id, 'question', $fbfield, $question->id), $teacher);

            $options = $DB->get_record('qtype_multichoice_options', ['questionid' => $question->id]);
            foreach ($fbfields as $j => $other) {
                $expected = $j <= $i ? '<p>' . $other . '</p>' : $this->img_html($other);
                $this->assertSame($expected, $options->$other);
            }
        }

        $this->delete_file($answerfile, $teacher);
        $ans1 = $DB->get_record('question_answers', ['id' => $ans1id]);
        $this->assertSame('<p>Answer</p>', $ans1->answer);
        $this->assertSame($this->img_html('Feedback'), $ans1->feedback);

        $this->delete_file($feedbackfile, $teacher);
        $ans1 = $DB->get_record('question_answers', ['id' => $ans1id]);
        $this->assertSame('<p>Feedback</p>', $ans1->feedback);

        // The other answer holds its own files under the same name, so it must be left alone.
        $ans2 = $DB->get_record('question_answers', ['id' => $ans2id]);
        $this->assertSame($this->img_html('Answer'), $ans2->answer);
        $this->assertSame($this->img_html('Feedback'), $ans2->feedback);
    }

    /**
     * Test removal of embedded files from ddmatch question text, sub questions, sub answers and feedback.
     *
     * @covers \tool_ally\webservice\delete_file::service
     */
    public function test_service_qtype_ddmatch_html(): void {
        global $CFG, $DB, $USER;

        if (!file_exists($CFG->dirroot . '/question/type/ddmatch')) {
            $this->markTestSkipped('qtype_ddmatch is not installed');
        }

        $this->resetAfterTest();

        [$course, $teacher] = $this->setup_course_and_teacher();

        $qgen = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $qgen->create_question_category();
        $context = \context_course::instance($course->id);

        // There is no ddmatch question generator, so the question is built by hand.
        $questionid = $DB->insert_record('question', (object) [
            'category' => $cat->id,
            'parent' => 0,
            'name' => 'DD match test',
            'questiontext' => $this->img_html('Question text'),
            'questiontextformat' => FORMAT_HTML,
            'generalfeedback' => '',
            'generalfeedbackformat' => FORMAT_HTML,
            'defaultmark' => 1,
            'penalty' => 1,
            'qtype' => 'ddmatch',
            'length' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $USER->id,
            'modifiedby' => $USER->id,
            'stamp' => make_unique_id_code(),
        ]);
        $bankentryid = $DB->insert_record('question_bank_entries', (object) [
            'questioncategoryid' => $cat->id,
            'ownerid' => $USER->id,
        ]);
        $DB->insert_record('question_versions', (object) [
            'questionbankentryid' => $bankentryid,
            'version' => 0,
            'questionid' => $questionid,
        ]);

        $questionfile = $this->create_test_file($context->id, 'question', 'questiontext', $questionid);
        $this->delete_file($questionfile, $teacher);
        $this->assertSame('<p>Question text</p>', $DB->get_field('question', 'questiontext', ['id' => $questionid]));

        $subquestion = (object) [
            'questionid' => $questionid,
            'questiontext' => $this->img_html('Sub question'),
            'questiontextformat' => FORMAT_HTML,
            'answertext' => $this->img_html('Sub answer'),
            'answertextformat' => FORMAT_HTML,
        ];
        $subqaid = $DB->insert_record('qtype_ddmatch_subquestions', $subquestion);
        $subqbid = $DB->insert_record('qtype_ddmatch_subquestions', $subquestion);

        $questionfile = $this->create_test_file($context->id, 'qtype_ddmatch', 'subquestion', $subqaid);
        $answerfile = $this->create_test_file($context->id, 'qtype_ddmatch', 'subanswer', $subqaid);

        $this->delete_file($questionfile, $teacher);
        $subqa = $DB->get_record('qtype_ddmatch_subquestions', ['id' => $subqaid]);
        $this->assertSame('<p>Sub question</p>', $subqa->questiontext);
        $this->assertSame($this->img_html('Sub answer'), $subqa->answertext);

        $this->delete_file($answerfile, $teacher);
        $this->assertSame('<p>Sub answer</p>', $DB->get_field('qtype_ddmatch_subquestions', 'answertext', ['id' => $subqaid]));

        // The other sub question holds its own files under the same name, so it must be left alone.
        $subqb = $DB->get_record('qtype_ddmatch_subquestions', ['id' => $subqbid]);
        $this->assertSame($this->img_html('Sub question'), $subqb->questiontext);
        $this->assertSame($this->img_html('Sub answer'), $subqb->answertext);

        $fbfields = ['correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'];
        $options = (object) ['questionid' => $questionid, 'shuffleanswers' => 1];
        foreach ($fbfields as $fbfield) {
            $options->$fbfield = $this->img_html($fbfield);
        }
        $optionsid = $DB->insert_record('qtype_ddmatch_options', $options);

        foreach ($fbfields as $i => $fbfield) {
            $this->delete_file($this->create_test_file($context->id, 'question', $fbfield, $questionid), $teacher);

            $optionsrow = $DB->get_record('qtype_ddmatch_options', ['id' => $optionsid]);
            foreach ($fbfields as $j => $other) {
                $expected = $j <= $i ? '<p>' . $other . '</p>' : $this->img_html($other);
                $this->assertSame($expected, $optionsrow->$other);
            }
        }
    }
}
