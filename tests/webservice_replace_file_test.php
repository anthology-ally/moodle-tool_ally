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
 * Test for file replace webservice.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2017 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_ally\webservice\replace_file;
use tool_ally\local;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__.'/abstract_testcase.php');
require_once($CFG->dirroot . '/files/externallib.php');
require_once($CFG->dirroot . '/mod/assign/tests/base_test.php');

/**
 * Test for file replace webservice.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2017 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_ally_webservice_replace_file_testcase extends tool_ally_abstract_testcase {

    /**
     * @var stdClass
     */
    private $course;

    /**
     * @var stdClass
     */
    private $teacher;

    /**
     * @throws dml_exception
     */
    public function setUp() {
        $this->resetAfterTest();

        $datagen = $this->getDataGenerator();

        $roleid = $this->assignUserCapability('moodle/course:view', context_system::instance()->id);
        $this->assignUserCapability('moodle/course:viewhiddencourses', context_system::instance()->id, $roleid);
        $this->assignUserCapability('moodle/course:managefiles', context_system::instance()->id, $roleid);
        $this->teacher = $datagen->create_user();
        $this->course = $datagen->create_course();

        $datagen->enrol_user($this->teacher->id, $this->course->id, 'editingteacher');
    }

    /**
     * Create and return draft file.
     * @return array
     * @throws coding_exception
     * @throws moodle_exception
     */
    private function create_draft_file($filename = 'red dot.png') {
        global $USER;
        $usercontext = context_user::instance($USER->id);
        $filecontent = "iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHElEQVQI12P4//8/w38"
            . "GIAXDIBKE0DHxgljNBAAO9TXL0Y4OHwAAAABJRU5ErkJggg==";
        $draftfile = core_files_external::upload($usercontext->id, 'user', 'draft', 0, '/', $filename, $filecontent, null, null);
        $draftfile['filecontent'] = $filecontent;
        return $draftfile;
    }

    /**
     * Create test file.
     * @param int $contextid
     * @param string $component
     * @param string $filearea
     * @return stored_file
     * @throws file_exception
     * @throws stored_file_creation_exception
     */
    private function create_test_file($contextid, $component, $filearea, $itemid = 0, $filename = 'gd logo.png') {
        global $CFG;
        $filepath = $CFG->libdir.'/tests/fixtures/gd-logo.png';
        $filerecord = array(
            'contextid' => $contextid,
            'component' => $component,
            'filearea'  => $filearea,
            'itemid'    => $itemid,
            'filepath'  => '/',
            'filename'  => $filename,
        );
        $fs = \get_file_storage();
        $file = $fs->create_file_from_pathname($filerecord, $filepath);
        return $file;
    }

    /**
     * Test the web service.
     */
    public function test_service() {
        $datagen = $this->getDataGenerator();

        $resource = $datagen->create_module('resource', ['course' => $this->course->id]);
        $file = $this->get_resource_file($resource);

        $draftfile = $this->create_draft_file();

        $return = replace_file::service($file->get_pathnamehash(), $this->teacher->id, $draftfile['itemid']);
        $return = external_api::clean_returnvalue(replace_file::service_returns(), $return);

        $this->assertSame($return['success'], true);
        $this->assertNotSame($return['newid'], $file->get_itemid());

        $file = $this->get_resource_file($resource);
        $this->assertSame($file->get_filename(), $draftfile['filename']);
        $this->assertSame($file->get_content(), base64_decode($draftfile['filecontent']));
        // This should test that the userid of the file creator gets copied,
        // but the mod resource generator always sets the userid to null,
        // Can still test it copies the null value correctly though.
        $this->assertSame($file->get_userid(), null);
    }

    public function test_service_invalid_user() {
        $otheruser = $this->getDataGenerator()->create_user();

        $resource    = $this->getDataGenerator()->create_module('resource', ['course' => $this->course->id]);
        $file        = $this->get_resource_file($resource);

        // Can use fake as user check will fail before it is used.
        $fakeitemid = '123';

        $this->expectException(\moodle_exception::class);
        $return = replace_file::service($file->get_pathnamehash(), $otheruser->id, $fakeitemid);
        external_api::clean_returnvalue(replace_file::service_returns(), $return);

        // Check file has not been changed.
        $newfile = $this->get_resource_file($resource);
        $this->assertInstanceOf(\stored_file, $newfile);
        $this->assertSame($file->get_filename(), $newfile->get_filename());
        $this->assertSame($file->get_content(), $newfile->get_content());
    }

    public function test_service_invalid_file() {
        // Can use fake as file check will fail before it is used.
        $fakeitemid = '123';

        $nonexistantfile = 'BADC0FFEE';
        $this->expectException(\moodle_exception::class);
        replace_file::service($nonexistantfile, $this->teacher->id, $fakeitemid);
    }

    /**
     * Test replacing files within label module intro.
     */
    public function test_service_label_html() {
        global $DB;

        $datagen = $this->getDataGenerator();

        $label = $datagen->create_module('label', ['course' => $this->course->id]);
        $context = context_module::instance($label->cmid);

        $file = $this->create_test_file($context->id, 'mod_label', 'intro');

        $dobj = (object) [
            'id' => $label->id
        ];
        $dobj->intro = '<img src="@@PLUGINFILE@@/gd%20logo.png" alt="" width="100" height="100">';
        $DB->update_record('label', $dobj);

        $draftfile = $this->create_draft_file();

        $return = replace_file::service($file->get_pathnamehash(), $this->teacher->id, $draftfile['itemid']);
        $return = external_api::clean_returnvalue(replace_file::service_returns(), $return);

        $this->assertSame($return['success'], true);
        $this->assertNotSame($return['newid'], $file->get_itemid());

        $label = $DB->get_record('label', ['id' => $label->id]);
        $this->assertNotContains('gd%20logo.png', $label->intro);
        $this->assertContains('red%20dot.png', $label->intro);
    }

    /**
     * Test replacing files within page module intro.
     */
    public function test_service_page_html() {
        global $DB;

        $datagen = $this->getDataGenerator();

        $page = $datagen->create_module('page', ['course' => $this->course->id]);
        $context = context_module::instance($page->cmid);

        $introfile = $this->create_test_file($context->id, 'mod_page', 'intro');
        $contentfile = $this->create_test_file($context->id, 'mod_page', 'content');

        $dobj = (object) [
            'id' => $page->id
        ];
        $dobj->intro = '<img src="@@PLUGINFILE@@/gd%20logo.png" alt="" width="100" height="100">';
        $dobj->content = '<img src="@@PLUGINFILE@@/gd%20logo.png" alt="" width="100" height="100">';
        $DB->update_record('page', $dobj);

        $this->replace_file($introfile);

        // Make sure only the intro field was updated in the page module instance.
        $page = $DB->get_record('page', ['id' => $page->id]);
        $this->assertNotContains('gd%20logo.png', $page->intro);
        $this->assertContains('red%20dot.png', $page->intro);
        $this->assertContains('gd%20logo.png', $page->content);
        $this->assertNotContains('red%20dot.png', $page->content);

        $this->replace_file($contentfile);

        // Make sure that the content field was update in the page module instance.
        $page = $DB->get_record('page', ['id' => $page->id]);
        $this->assertNotContains('gd%20logo.png', $page->content);
        $this->assertContains('red%20dot.png', $page->content);
    }

    /**
     * Test replacing files within course summary.
     */
    public function test_service_course_html() {
        global $DB;

        $context = context_course::instance($this->course->id);
        $file = $this->create_test_file($context->id, 'course', 'summary');

        $dobj = (object) [
            'id' => $this->course->id
        ];
        $dobj->summary = '<img src="@@PLUGINFILE@@/gd%20logo.png" alt="" width="100" height="100">';
        $DB->update_record('course', $dobj);

        $draftfile = $this->create_draft_file();

        $return = replace_file::service($file->get_pathnamehash(), $this->teacher->id, $draftfile['itemid']);
        $return = external_api::clean_returnvalue(replace_file::service_returns(), $return);

        $this->assertSame($return['success'], true);
        $this->assertNotSame($return['newid'], $file->get_itemid());

        $course = $DB->get_record('course', ['id' => $this->course->id]);
        $this->assertNotContains('gd%20logo.png', $course->summary);
        $this->assertContains('red%20dot.png', $course->summary);
    }

    /**
     * Test replacing files within course section html.
     */
    public function test_service_course_section_html() {
        global $DB;

        $datagen = $this->getDataGenerator();

        $course = (object) ['numsections' => 2];
        $course = $datagen->create_course($course, ['createsections' => true]);

        $datagen->enrol_user($this->teacher->id, $course->id, 'editingteacher');

        $context = context_course::instance($course->id);
        $file = $this->create_test_file($context->id, 'course', 'section');

        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1]);
        $section->summary = '<img src="@@PLUGINFILE@@/gd%20logo.png" alt="" width="100" height="100">';
        $DB->update_record('course_sections', $section);
        $draftfile = $this->create_draft_file();

        $return = replace_file::service($file->get_pathnamehash(), $this->teacher->id, $draftfile['itemid']);
        $return = external_api::clean_returnvalue(replace_file::service_returns(), $return);

        $this->assertSame($return['success'], true);
        $this->assertNotSame($return['newid'], $file->get_itemid());

        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1]);
        $this->assertNotContains('gd%20logo.png', $section->summary);
        $this->assertContains('red%20dot.png', $section->summary);
    }

    /**
     * Test replacing files within course section html.
     */
    public function test_service_block_html() {
        global $DB;

        $configdata = (object) [
            'text' => '',
            'title' => 'test block',
            'format' => FORMAT_HTML
        ];

        $blockinsert = (object) [
            'blockname' => 'html',
            'parentcontextid' => context_course::instance($this->course->id)->id,
            'pagetypepattern' => 'course-view-*',
            'defaultregion' => 'side-pre',
            'defaultweight' => 1,
            'configdata' => base64_encode(serialize($configdata)),
            'showinsubcontexts' => 1
        ];
        $blockid = $DB->insert_record('block_instances', $blockinsert);
        $block = $DB->get_record('block_instances', ['id' => $blockid]);

        $context = context_block::instance($block->id);
        $file = $this->create_test_file($context->id, 'block_html', 'content');

        $configdata = (object) [
            'text' => '<img src="@@PLUGINFILE@@/gd logo.png" alt="" width="100" height="100">',
            'title' => 'test block',
            'format' => FORMAT_HTML
        ];
        $block->configdata = base64_encode(serialize($configdata));

        $DB->update_record('block_instances', $block);

        $draftfile = $this->create_draft_file();

        $return = replace_file::service($file->get_pathnamehash(), $this->teacher->id, $draftfile['itemid']);
        $return = external_api::clean_returnvalue(replace_file::service_returns(), $return);

        $this->assertSame($return['success'], true);
        $this->assertNotSame($return['newid'], $file->get_itemid());

        $block = $DB->get_record('block_instances', ['id' => $block->id]);
        $blockconfig = unserialize(base64_decode($block->configdata));
        $blockhtml = $blockconfig->text;
        $this->assertNotContains('gd logo.png', $blockhtml);
        $this->assertContains('red dot.png', $blockhtml);
    }

    /**
     * Replace file.
     * @param stored_file $originalfile
     * @param stdClass | bool $user
     * @throws invalid_response_exception
     * @throws moodle_exception
     */
    protected function replace_file(\stored_file $originalfile, $user = null) {
        if (empty($user)) {
            $user = $this->teacher;
        }
        $draftfile = $this->create_draft_file();
        $return = replace_file::service($originalfile->get_pathnamehash(), $user->id, $draftfile['itemid']);
        $return = external_api::clean_returnvalue(replace_file::service_returns(), $return);
        $this->assertSame($return['success'], true);
        $this->assertNotSame($return['newid'], $originalfile->get_itemid());
    }

    /**
     * Test replacing files within forum module intro / discussion / posts.
     */
    public function test_service_forum_html($forumtype = 'forum') {
        global $DB;

        $datagen = $this->getDataGenerator();

        $forum = $datagen->create_module($forumtype, ['course' => $this->course->id]);
        $context = context_module::instance($forum->cmid);
        $forumfile = $this->create_test_file($context->id, 'mod_'.$forumtype, 'intro');
        $dobj = (object) [
            'id' => $forum->id
        ];
        $dobj->intro = '<img src="@@PLUGINFILE@@/gd%20logo.png" alt="" width="100" height="100">';
        $dobj->content = '<img src="@@PLUGINFILE@@/gd%20logo.png" alt="" width="100" height="100">';
        $DB->update_record($forumtype, $dobj);

        $fdg = $datagen->get_plugin_generator('mod_'.$forumtype);

        // Create discussion / post.
        $record = new stdClass();
        $record->course = $this->course->id;
        $record->userid = $this->teacher->id;
        $record->forum = $forum->id;
        // Add file to discussion post.
        $discussion = $fdg->create_discussion($record);
        $discussionpost = $DB->get_record($forumtype.'_posts', ['discussion' => $discussion->id]);
        $discussionfile = $this->create_test_file($context->id, 'mod_'.$forumtype, 'post', $discussionpost->id);
        $discussionpost->message = '<img src="@@PLUGINFILE@@/gd%20logo.png" alt="" width="100" height="100">';
        $DB->update_record($forumtype.'_posts', $discussionpost);

        // Create post replying to discussion.
        $record = new stdClass();
        $record->discussion = $discussionpost->discussion;
        $record->parent = $discussionpost->id;
        $record->userid = $this->teacher->id;
        $post = $fdg->create_post($record);
        // Add file to reply.
        $postfile = $this->create_test_file($context->id, 'mod_'.$forumtype, 'post', $post->id);
        $post->message = '<img src="@@PLUGINFILE@@/gd%20logo.png" alt="" width="100" height="100">';
        $DB->update_record($forumtype.'_posts', $post);

        // Replace main forum file.
        $this->replace_file($forumfile);

        // Ensure that forum main record has had file link replaced in HTML.
        $forum = $DB->get_record($forumtype, ['id' => $forum->id]);
        $this->assertNotContains('gd%20logo.png', $forum->intro);
        $this->assertContains('red%20dot.png', $forum->intro);

        // Ensure that both discussion post and reply post have NOT had file link replaced in HTML.
        $discussionpost = $DB->get_record($forumtype.'_posts', ['id' => $discussionpost->id, 'parent' => 0]);
        $post = $DB->get_record($forumtype.'_posts', ['id' => $post->id]);
        $this->assertContains('gd%20logo.png', $discussionpost->message);
        $this->assertNotContains('red%20dot.png', $discussionpost->message);
        $this->assertContains('gd%20logo.png', $post->message);
        $this->assertNotContains('red%20dot.png', $post->message);

        // Replace discussion file.
        $this->replace_file($discussionfile);

        // Ensure that discussion post has had file link replaced but reply post has not.
        $discussionpost = $DB->get_record($forumtype.'_posts', ['id' => $discussionpost->id, 'parent' => 0]);
        $post = $DB->get_record($forumtype.'_posts', ['id' => $post->id]);
        $this->assertNotContains('gd%20logo.png', $discussionpost->message);
        $this->assertContains('red%20dot.png', $discussionpost->message);
        $this->assertContains('gd%20logo.png', $post->message);
        $this->assertNotContains('red%20dot.png', $post->message);

        // Replace reply post file.
        $this->replace_file($postfile);

        // Ensure that reply post has had file links replaced.
        $post = $DB->get_record($forumtype.'_posts', ['id' => $post->id]);
        $this->assertNotContains('gd%20logo.png', $post->message);
        $this->assertContains('red%20dot.png', $post->message);
    }

    /**
     * Test replacing files within hsuforum module intro / discussion / posts.
     */
    public function test_service_hsuforum_html() {
        $this->test_service_forum_html('hsuforum');
    }

    /**
     * Test replacing files within questions.
     */
    public function test_service_question_html() {
        global $DB;

        $datagen = $this->getDataGenerator();
        $qgen = $datagen->get_plugin_generator('core_question');

        $cat = $qgen->create_question_category();
        $question = $qgen->create_question('shortanswer', null, array('category' => $cat->id));
        $questionrow = $DB->get_record('question', ['id' => $question->id]);

        $quiz = $this->getDataGenerator()->create_module('quiz', array('course' => $this->course->id));
        $context = context_course::instance($this->course->id);
        quiz_add_quiz_question($question->id, $quiz);

        $qfile = $this->create_test_file($context->id, 'question', 'questiontext', $question->id);
        $questionrow->questiontext = '<img src="@@PLUGINFILE@@/gd%20logo.png" alt="" width="100" height="100">';
        $DB->update_record('question', $questionrow);

        // Replace file.
        $this->replace_file($qfile);

        $questionrow = $DB->get_record('question', ['id' => $question->id]);
        $this->assertNotContains('gd%20logo.png', $questionrow->questiontext);
        $this->assertContains('red%20dot.png', $questionrow->questiontext);
    }

    /**
     * Test replacing files within lesson module intro / pages.
     */
    public function test_service_lesson_html() {
        global $DB;

        // Lesson intro file replacement testing.
        $lesson = $this->getDataGenerator()->create_module('lesson', array('course' => $this->course->id));
        $dobj = (object)[
            'id' => $lesson->id
        ];
        $dobj->intro = '<img src="@@PLUGINFILE@@/gd%20logo.png" alt="" width="100" height="100">';
        $context = context_module::instance($lesson->cmid);
        $DB->update_record('lesson', $dobj);

        $lfile = $this->create_test_file($context->id, 'mod_lesson', 'intro');

        // Replace file.
        $this->replace_file($lfile);

        $lessonrow = $DB->get_record('lesson', ['id' => $lesson->id]);
        $this->assertNotContains('gd%20logo.png', $lessonrow->intro);
        $this->assertContains('red%20dot.png', $lessonrow->intro);

        // Lesson page content file replacement testing.
        $lessongenerator = $this->getDataGenerator()->get_plugin_generator('mod_lesson');

        $page = $lessongenerator->create_content($lesson, array('title' => 'Simple page'));
        $dobj = (object)[
            'id' => $page->id
        ];
        $dobj->contents = '<img src="@@PLUGINFILE@@/gd%20logo.png" alt="" width="100" height="100">';
        $DB->update_record('lesson_pages', $dobj);

        $pfile = $this->create_test_file($context->id, 'mod_lesson', 'page_contents', $page->id);

        // Replace file.
        $this->replace_file($pfile);

        $pagerow = $DB->get_record('lesson_pages', ['id' => $page->id]);
        $this->assertNotContains('gd%20logo.png', $pagerow->contents);
        $this->assertContains('red%20dot.png', $pagerow->contents);
    }

    /**
     * Test replacing file where filename already exists.
     */
    public function test_service_replace_existing_filename() {
        global $DB;

        $datagen = $this->getDataGenerator();

        $label = $datagen->create_module('label', ['course' => $this->course->id]);
        $context = context_module::instance($label->cmid);

        $filetoreplacename = 'file to replace.png';
        $filetoreplace = $this->create_test_file($context->id, 'mod_label', 'intro', 0, $filetoreplacename);

        $filename = 'name to increment.png';
        $this->create_test_file($context->id, 'mod_label', 'intro', 0, $filename);

        $dobj = (object) [
            'id' => $label->id
        ];
        $dobj->intro = '<img src="@@PLUGINFILE@@/'.rawurlencode($filename).'" alt="" width="100" height="100">'.
                '<img src="@@PLUGINFILE@@/'.rawurlencode($filetoreplacename).'" alt="" width="100" height="100">';
        $DB->update_record('label', $dobj);

        // Draft file will have the same filename.
        $draftfile = $this->create_draft_file($filename);

        $return = replace_file::service($filetoreplace->get_pathnamehash(), $this->teacher->id, $draftfile['itemid']);
        $return = external_api::clean_returnvalue(replace_file::service_returns(), $return);

        $this->assertSame($return['success'], true);
        $this->assertNotSame($return['newid'], $filetoreplace->get_itemid());

        $label = $DB->get_record('label', ['id' => $label->id]);
        $this->assertContains(rawurlencode($filename), $label->intro);
        $this->assertContains(rawurlencode('name to increment (1).png'), $label->intro);
        $this->assertNotContains(rawurlencode($filetoreplacename), $label->intro);
    }
}
