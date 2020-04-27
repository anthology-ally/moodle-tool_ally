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
 * Tests for event handlers.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once(__DIR__.'/abstract_testcase.php');

use core\event\course_created;
use core\event\course_updated;
use core\event\course_section_created;
use core\event\course_section_updated;
use core\event\course_module_created;
use core\event\course_module_updated;

use \mod_forum\event\discussion_created;
use \mod_forum\event\post_updated;

use \mod_glossary\event\entry_created;
use \mod_glossary\event\entry_updated;

use \mod_book\event\chapter_created;
use \mod_book\event\chapter_updated;

use tool_ally\content_processor;
use tool_ally\course_processor;
use tool_ally\traceable_processor;

use tool_ally\event_handlers;
use tool_ally\task\content_updates_task;
use tool_ally\local_content;
/**
 * Tests for event handlers.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_ally_event_handlers_testcase extends tool_ally_abstract_testcase {

    public function setUp() {
        global $CFG;

        $this->resetAfterTest();

        require_once($CFG->dirroot.'/mod/lesson/locallib.php');
        require_once($CFG->dirroot.'/mod/lesson/pagetypes/multichoice.php');

        set_config('pushurl', 'url', 'tool_ally');
        set_config('key', 'key', 'tool_ally');
        set_config('secret', 'secret', 'tool_ally');
        set_config('push_cli_only', 0, 'tool_ally');
        content_processor::clear_push_traces();
        course_processor::clear_push_traces();
        content_processor::get_config(true);
        course_processor::get_config(true);
    }

    /**
     * Checks if push traces have a key-pair set for a specific Ally processor.
     *
     * @param $processor
     * @param $eventname
     * @param $key
     * @param $value
     * @return bool
     */
    private function check_pushtrace_contains_key_value($processor, $eventname, $key, $value) {
        $pushtraces = call_user_func(['tool_ally\\' . $processor, 'get_push_traces'], $eventname);
        if (!$pushtraces) {
            return false;
        }
        foreach ($pushtraces as $pushtrace) {
            foreach ($pushtrace as $row) {
                if ($row[$key] === $value) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Checks push traces in content processor for the entity_id key.
     *
     * @param $eventname
     * @param $entityid
     * @return bool
     */
    private function check_pushtrace_contains_entity_id($eventname, $entityid) {
        return $this->check_pushtrace_contains_key_value('content_processor', $eventname, 'entity_id', $entityid);
    }

    /**
     * Asserts inclusion of an entity id in content processor push traces.
     *
     * @param string $eventname
     * @param string $entityid
     * @return void
     * @throws coding_exception
     */
    private function assert_pushtrace_contains_entity_id($eventname, $entityid) {
        $pushtraces = content_processor::get_push_traces($eventname);
        $contains = $this->check_pushtrace_contains_entity_id($eventname, $entityid);
        $msg = 'Push trace does not contain an entity id of '.$entityid."\n\n".
                var_export($pushtraces, true);
        $this->assertTrue($contains, $msg);
    }

    /**
     * @param string $eventname
     * @param string $entityid
     * @throws coding_exception
     */
    private function assert_pushtrace_not_contains_entity_id($eventname, $entityid) {
        $pushtraces = content_processor::get_push_traces($eventname);
        $contains = $this->check_pushtrace_contains_entity_id($eventname, $entityid);
        $msg = 'Push trace does not contain an entity id of '.$entityid."\n\n".
            var_export($pushtraces, true);
        $this->assertFalse($contains, $msg);
    }

    private function assert_pushtrace_not_contains_entity_regex($regex) {
        $eventtypes = content_processor::get_push_traces();
        if (!$eventtypes) {
            return;
        }

        foreach ($eventtypes as $pushtraces) {
            foreach ($pushtraces as $pushtrace) {
                foreach ($pushtrace as $row) {
                    if (isset($row['entity_id']) && preg_match($regex, $row['entity_id']) === 1) {
                        $rowstr = var_export($row, true);
                        $msg = <<<MSG
Push trace contains an entity id which matches regular expression $regex

$rowstr
MSG;

                        $this->fail($msg);
                    }
                }
            }
        }
        return;
    }

    /**
     * Checks push traces in course processor for the context_id key.
     *
     * @param $eventname
     * @param $contextid
     * @return bool
     */
    private function check_pushtrace_contains_context_id($eventname, $contextid) {
        return $this->check_pushtrace_contains_key_value('course_processor', $eventname, 'context_id', $contextid);
    }

    /**
     * Asserts inclusion of a context id (course id really) in course processor push traces.
     *
     * @param string $eventname
     * @param string $contextid
     * @return void
     * @throws coding_exception
     */
    private function assert_pushtrace_contains_context_id($eventname, $contextid) {
        $pushtraces = course_processor::get_push_traces($eventname);
        $contains = $this->check_pushtrace_contains_context_id($eventname, $contextid);
        $msg = 'Push trace does not contain a context id (course id) of '.$contextid."\n\n".
            var_export($pushtraces, true);
        $this->assertTrue($contains, $msg);
    }

    /**
     * Test pushes on course creation.
     */
    public function test_course_created() {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['summaryformat' => FORMAT_HTML]);

        // Set all section summary formats to HTML.
        $sections = $DB->get_records('course_sections', ['course' => $course->id]);
        foreach ($sections as $section) {
            $section->summaryformat = FORMAT_HTML;
            $DB->update_record('course_sections', $section);
        }

        // Trigger a course created event.
        course_created::create([
            'objectid' => $course->id,
            'context' => context_course::instance($course->id),
            'other' => [
                'shortname' => $course->shortname,
                'fullname' => $course->fullname
            ]
        ])->trigger();

        $entityid = 'course:course:summary:'.$course->id;
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_RICH_CNT_CREATED, $entityid);
    }

    public function test_course_updated() {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $course->fullname = 'Modified';
        $course->summary = 'Summary modified';
        $course->summaryformat = FORMAT_HTML;
        content_processor::clear_push_traces();

        $DB->update_record('course', $course);

        course_updated::create([
            'objectid' => $course->id,
            'context' => context_course::instance($course->id),
            'other' => ['shortname' => $course->shortname]
        ])->trigger();

        $entityid = 'course:course:summary:'.$course->id;
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_RICH_CNT_UPDATED, $entityid);

        // Ensure section information is not included.
        $this->assert_pushtrace_not_contains_entity_regex('/course:course_sections:summary:/');
    }

    public function test_course_section_created() {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $section = $this->getDataGenerator()->create_course_section([
            'section' => 0,
            'course' => $course->id,
            'summaryformat' => FORMAT_HTML
        ]);
        $section = $DB->get_Record('course_sections', ['id' => $section->id]);
        course_section_created::create_from_section($section)->trigger();

        $entityid = 'course:course_sections:summary:'.$section->id;
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_RICH_CNT_CREATED, $entityid);
    }

    public function test_course_section_updated() {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $section0 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0]);
        $section0->summaryformat = FORMAT_HTML;
        $section1 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1]);
        $section1->summaryformat = FORMAT_HTML;

        content_processor::clear_push_traces();
        course_section_updated::create([
            'objectid' => $section0->id,
            'courseid' => $course->id,
            'context' => context_course::instance($course->id),
            'other' => array(
                'sectionnum' => $section0->section
            )
        ])->trigger();

        $entityid0 = 'course:course_sections:summary:'.$section0->id;
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_RICH_CNT_UPDATED, $entityid0);

        // Make sure section 1 isn't in push trace as we haven't updated it.
        $entityid1 = 'course:course_sections:summary:'.$section1->id;
        $this->assert_pushtrace_not_contains_entity_id(event_handlers::API_RICH_CNT_UPDATED, $entityid1);

        // Get content for section 0 and check it contains default section name 'General' as title for intro section.
        $content = local_content::get_html_content_by_entity_id($entityid0);
        $this->assertEquals('Topic 0', $content->title);

        // Get content for section 1 and check it contains default section name 'Topic 1' as title for section 1.
        $content = local_content::get_html_content_by_entity_id($entityid1);
        $this->assertEquals('Topic 1', $content->title);

        // Update section1's title and content.
        $section1->name = 'Altered section name';

        $section1->summary = 'Updated summary with some text';
        $DB->update_record('course_sections', $section1);

        content_processor::clear_push_traces();
        course_section_updated::create([
            'objectid' => $section1->id,
            'courseid' => $course->id,
            'context' => context_course::instance($course->id),
            'other' => array(
                'sectionnum' => $section1->section
            )
        ])->trigger();

        // Ensure section 1 is now in push trace.
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_RICH_CNT_UPDATED, $entityid1);

        // Get content for section 1 and check it contains custom section name as title for section 1.
        $content = local_content::get_html_content_by_entity_id($entityid1);
        $this->assertEquals($section1->name, $content->title);
    }


    /**
     * @param string $modname
     * @param string $modtable
     * @param string $modfield
     * @return stdClass
     * @throws coding_exception
     * @throws moodle_exception
     */
    private function check_module_created_pushtraces($modname, $modtable, $modfield) {
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        // Assert that label with non FORMAT_HTML intro does not push.
        $mod = $this->getDataGenerator()->create_module($modname, ['course' => $course->id]);
        $entityid = $modname.':'.$modtable.':'.$modfield.':'.$mod->id;
        list ($course, $cm) = get_course_and_cm_from_cmid($mod->cmid);
        course_module_created::create_from_cm($cm)->trigger();
        $this->assert_pushtrace_not_contains_entity_id(event_handlers::API_RICH_CNT_CREATED, $entityid);

        // Assert that module with FORMAT_HTML intro pushes.
        $mod = $this->getDataGenerator()->create_module($modname,
            ['course' => $course->id, $modfield.'format' => FORMAT_HTML]);
        $entityid = $modname.':'.$modtable.':'.$modfield.':'.$mod->id;
        list ($course, $cm) = get_course_and_cm_from_cmid($mod->cmid);
        course_module_created::create_from_cm($cm)->trigger();
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_RICH_CNT_CREATED, $entityid);

        return $mod;
    }

    /**
     * @param string $modname
     * @param string $modtable
     * @param string $modfield
     * @param string $filearea
     * @return stdClass
     * @throws coding_exception
     * @throws dml_exception
     * @throws file_exception
     * @throws moodle_exception
     * @throws stored_file_creation_exception
     */
    private function check_module_updated_pushtraces($modname, $modtable, $modfield, $filearea) {
        global $DB;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $mod = $this->getDataGenerator()->create_module($modname,
            ['course' => $course->id, $modfield.'format' => FORMAT_HTML]);
        list ($course, $cm) = get_course_and_cm_from_cmid($mod->cmid);

        $mod->$modfield = 'Updated '.$modfield.' with some text';

        $DB->update_record($modtable, $mod);

        course_module_updated::create_from_cm($cm)->trigger();

        $entityid = $modname.':'.$modtable.':'.$modfield.':'.$mod->id;
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_RICH_CNT_UPDATED, $entityid);

        return $mod;
    }

    private function check_module_deleted_pushtraces($modname, $modtable, $modfield) {
        global $DB;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $mod = $this->getDataGenerator()->create_module($modname,
            ['course' => $course->id, $modfield.'format' => FORMAT_HTML, $modfield => 'Some content']);
        $entityid = $modname.':'.$modtable.':'.$modfield.':'.$mod->id;
        list ($course, $cm) = get_course_and_cm_from_cmid($mod->cmid);
        course_delete_module($cm->id);

        // Push should not have happened - it needs cron task to make it happen.
        $this->assert_pushtrace_not_contains_entity_id(event_handlers::API_RICH_CNT_DELETED, $entityid);

        $delfilter = [
            'component' => $modname,
            'comptable' => $modtable,
            'courseid' => (int) $course->id,
            'comprowid' => (int) $mod->id,
            'compfield' => $modfield
        ];

        $row = $DB->get_record('tool_ally_deleted_content', $delfilter);
        $this->assertNotEmpty($row);
        $this->assertEmpty($row->timeprocessed);

        $cdt = new content_updates_task();
        $cdt->execute();
        $cdt->execute(); // We have to execute again because first time just sets exec window.

        $row = $DB->get_record('tool_ally_deleted_content', $delfilter);
        $this->assertNotEmpty($row);
        $this->assertNotEmpty($row->timeprocessed);

        // Execute again to purge deletion queue of processed items.
        $cdt->execute();
        $row = $DB->get_record('tool_ally_deleted_content', $delfilter);
        $this->assertEmpty($row);

        $this->assert_pushtrace_contains_entity_id(event_handlers::API_RICH_CNT_DELETED, $entityid);
    }

    public function test_assign_created() {
        $this->check_module_created_pushtraces('assign', 'assign', 'intro');
    }

    public function test_assign_updated() {
        $this->check_module_updated_pushtraces('assign', 'assign', 'intro', 'intro');
    }

    public function test_assign_deleted() {
        $this->check_module_deleted_pushtraces('assign', 'assign', 'intro');
    }

    public function test_book_created() {
        $this->check_module_created_pushtraces('book', 'book', 'intro');
    }

    public function test_book_updated() {
        global $DB;

        $this->setAdminUser();

        $mod = $this->check_module_updated_pushtraces('book', 'book', 'intro', 'intro');
        $context = context_module::instance($mod->cmid);
        $bookgenerator = self::getDataGenerator()->get_plugin_generator('mod_book');

        $data = [
            'bookid' => $mod->id,
            'title' => 'Test chapter',
            'content' => 'Test content',
            'contentformat' => FORMAT_HTML
        ];

        $chapter = $bookgenerator->create_chapter($data);
        $entityid = 'book:book_chapters:content:'.$chapter->id;

        $params = array(
            'context' => $context,
            'objectid' => $chapter->id,
            'other' => array(
                'bookid' => $mod->id
            )
        );
        $event = chapter_created::create($params);
        $event->add_record_snapshot('book_chapters', $chapter);
        $event->trigger();

        $chapter = $DB->get_record('book_chapters', ['id' => $chapter->id]);
        $context = context_module::instance($mod->cmid);

        $this->assert_pushtrace_contains_entity_id(event_handlers::API_RICH_CNT_CREATED, $entityid);

        // Modify chapter.
        $chapter->content = 'Updated chapter '.$chapter->id.' with some text';
        $DB->update_record('book_chapters', $chapter);
        $params = array(
            'context' => $context,
            'objectid' => $chapter->id,
            'other' => array(
                'bookid' => $mod->id
            )
        );
        $event = chapter_updated::create($params);
        $event->add_record_snapshot('book_chapters', $chapter);
        $event->trigger();

        // Assert pushtrace contains updated chapter.
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_RICH_CNT_UPDATED, $entityid);

    }

    public function test_book_deleted() {
        $this->check_module_deleted_pushtraces('book', 'book', 'intro');
    }

    public function test_forum_created() {
        $this->check_module_created_pushtraces('forum', 'forum', 'intro');
    }

    public function test_forum_updated() {
        $this->check_module_updated_pushtraces('forum', 'forum', 'intro', 'intro');
    }

    public function test_forum_deleted() {
        $this->check_module_deleted_pushtraces('forum', 'forum', 'intro');
    }

    public function test_label_created() {
        $this->check_module_created_pushtraces('label', 'label', 'intro');
    }

    public function test_label_updated() {
        $this->check_module_updated_pushtraces('label', 'label', 'intro', 'intro');
    }

    public function test_label_deleted() {
        $this->check_module_deleted_pushtraces('label', 'label', 'intro');
    }

    public function test_lesson_created() {
        global $DB;

        $dg = $this->getDataGenerator();

        $lesson = $this->check_module_created_pushtraces('lesson', 'lesson', 'intro');

        $pdg = $dg->get_plugin_generator('mod_lesson');

        // Test that question page results in push to ally.
        $questionpage = $pdg->create_question_multichoice($lesson);
        $entityid = 'lesson:lesson_pages:contents:'.$questionpage->id;
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_RICH_CNT_CREATED, $entityid);

        $answers = $DB->get_records('lesson_answers', ['pageid' => $questionpage->id]);
        $this->assertNotEmpty($answers);

        foreach ($answers as $answer) {
            $entityid = 'lesson:lesson_answers:answer:'.$answer->id;
            $this->assert_pushtrace_contains_entity_id(event_handlers::API_RICH_CNT_CREATED, $entityid);
        }
    }

    public function test_lesson_updated() {
        global $DB;
        $this->markTestSkipped('Started to fail after the 3.7.3 merge, to be fixed in INT-15837');

        $dg = $this->getDataGenerator();

        $lesson = $this->check_module_updated_pushtraces('lesson', 'lesson', 'intro', 'intro');
        $context = context_module::instance($lesson->cmid);
        $lesson = new lesson($lesson);

        $pdg = $dg->get_plugin_generator('mod_lesson');

        $questionpage = $pdg->create_question_multichoice($lesson);
        $questionpage->pageid = $questionpage->id;
        $questionpage->contents_editor = ['text' => 'some text', 'format' => FORMAT_HTML];
        $questionpage->answer_editor = ['text' => 'Cats', 'format' => FORMAT_PLAIN, 'score' => 1];
        $mcpage = lesson_page_type_multichoice::create($questionpage, $lesson, $context, 0);
        $mcpage->id = $questionpage->id;
        $mcpage->update($questionpage, $context);

        $answers = $DB->get_records('lesson_answers', ['pageid' => $questionpage->pageid]);
        $this->assertNotEmpty($answers);

        foreach ($answers as $answer) {
            $entityid = 'lesson:lesson_answers:answer:'.$answer->id;
            $this->assert_pushtrace_contains_entity_id(event_handlers::API_RICH_CNT_UPDATED, $entityid);
        }
    }

    public function test_lesson_deleted() {
        $this->check_module_deleted_pushtraces('lesson', 'lesson', 'intro');
    }

    public function test_page_created() {
        $this->check_module_created_pushtraces('page', 'page', 'intro');
        $this->check_module_created_pushtraces('page', 'page', 'content');
    }

    public function test_page_updated() {
        $this->check_module_updated_pushtraces('page', 'page', 'intro', 'intro');
        $this->check_module_updated_pushtraces('page', 'page', 'content', 'content');
    }

    public function test_page_deleted_intro() {
        $this->check_module_deleted_pushtraces('page', 'page', 'intro');
    }

    public function test_page_deleted_content() {
        $this->check_module_deleted_pushtraces('page', 'page', 'content');
    }

    public function test_forum_discussion_created() {
        global $USER, $DB;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $forum = $this->getDataGenerator()->create_module('forum',
            ['course' => $course->id, 'introformat' => FORMAT_HTML]);
        $entityid = 'forum:forum:intro:'.$forum->id;
        list ($course, $cm) = get_course_and_cm_from_cmid($forum->cmid);
        course_module_created::create_from_cm($cm)->trigger();

        $this->assert_pushtrace_contains_entity_id(event_handlers::API_RICH_CNT_CREATED, $entityid);

        // Add a discussion.
        $record = new stdClass();
        $record->forum = $forum->id;
        $record->userid = $USER->id;
        $record->course = $forum->course;
        $discussion = self::getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion($record);

        $params = array(
            'context' => $cm->context,
            'objectid' => $discussion->id,
            'other' => array(
                'forumid' => $forum->id,
            )
        );
        $event = discussion_created::create($params);
        $event->add_record_snapshot('forum_discussions', $discussion);
        $event->trigger();

        // Get discussion post.
        $post = $DB->get_record('forum_posts', ['discussion' => $discussion->id]);
        $entityid = 'forum:forum_posts:message:'.$post->id;

        // Assert pushtrace contains discussion post.
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_RICH_CNT_CREATED, $entityid);

        // Modify post.
        $post->message .= 'message!!!';
        $params = array(
            'context' => $cm->context,
            'objectid' => $post->id,
            'other' => array(
                'discussionid' => $discussion->id,
                'forumid' => $forum->id,
                'forumtype' => $forum->type,
            )
        );
        $event = \mod_forum\event\post_updated::create($params);
        $event->add_record_snapshot('forum_discussions', $discussion);
        $event->trigger();

        // Assert pushtrace contains discussion post.
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_RICH_CNT_UPDATED, $entityid);
        post_updated::create($params);
    }

    public function test_forum_single_discussion_created() {
        global $USER, $DB;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        // Create forum.
        $forum = $this->getDataGenerator()->create_module('forum',
            ['course' => $course->id, 'introformat' => FORMAT_HTML]);
        $introentityid = 'forum:forum:intro:'.$forum->id;

        // Add a discussion.
        $record = new stdClass();
        $record->forum = $forum->id;
        $record->userid = $USER->id;
        $record->course = $forum->course;
        $discussion = self::getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion($record);

        // Get discussion post.
        $post = $DB->get_record('forum_posts', ['discussion' => $discussion->id]);
        $postentityid = 'forum:forum_posts:message:'.$post->id;

        list ($course, $cm) = get_course_and_cm_from_cmid($forum->cmid);
        course_module_created::create_from_cm($cm)->trigger();

        // Both entities should be traced.
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_RICH_CNT_CREATED, $introentityid);
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_RICH_CNT_CREATED, $postentityid);
    }

    public function test_glossary_events() {
        global $USER;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $glossary = $this->getDataGenerator()->create_module('glossary',
            ['course' => $course->id, 'introformat' => FORMAT_HTML]);
        $glossaryentityid = 'glossary:glossary:intro:'.$glossary->id;
        list ($course, $cm) = get_course_and_cm_from_cmid($glossary->cmid);
        course_module_created::create_from_cm($cm)->trigger();

        $this->assert_pushtrace_contains_entity_id(event_handlers::API_RICH_CNT_CREATED, $glossaryentityid);

        // Add an entry.
        $record = new stdClass();
        $record->course = $course->id;
        $record->glossary = $glossary->id;
        $record->userid = $USER->id;
        $record->definitionformat = FORMAT_HTML;
        $entry = self::getDataGenerator()->get_plugin_generator('mod_glossary')->create_content($glossary, $record);

        $params = array(
            'context' => $cm->context,
            'objectid' => $entry->id,
            'other' => array(
                'glossaryid' => $glossary->id
            )
        );
        $event = entry_created::create($params);
        $event->add_record_snapshot('glossary_entries', $entry);
        $event->trigger();

        $entityid = 'glossary:glossary_entries:definition:'.$entry->id;

        // Assert pushtrace contains entry.
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_RICH_CNT_CREATED, $entityid);

        // Modify entry.
        $entry->definition .= 'modified !!!';
        $params = array(
            'context' => $cm->context,
            'objectid' => $entry->id,
            'other' => array(
                'glossaryid' => $glossary->id
            )
        );
        $event = entry_updated::create($params);
        $event->add_record_snapshot('glossary_entries', $entry);
        $event->trigger();

        // Assert pushtrace contains updated entry.
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_RICH_CNT_UPDATED, $entityid);

        course_delete_module($glossary->cmid);

        // Note, there shouldn't be any deletion events at this point because deletes need the task to be dealt with.
        $this->assert_pushtrace_not_contains_entity_id(event_handlers::API_RICH_CNT_DELETED, $glossaryentityid);

        $cdt = new content_updates_task();
        $cdt->execute();
        $cdt->execute(); // We have to execute again because first time just sets exec window.

        // After running the task it has pushed the deletion event.
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_RICH_CNT_DELETED, $glossaryentityid);
    }

    /**
     * Verifies course event processing for the course event push handling.
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function test_course_events() {
        $course = $this->getDataGenerator()->create_course();
        course_processor::clear_push_traces();

        // Course creation triggers course_updated event.
        $createevent = \core\event\course_created::create([
            'objectid' => $course->id,
            'context' => context_course::instance($course->id),
            'other' => [
                'shortname' => $course->shortname,
                'fullname' => $course->fullname,
                'idnumber' => $course->idnumber
            ]
        ]);
        $createevent->add_record_snapshot('course', $course);
        $createevent->trigger();

        // Course creation triggers course_updated event.
        $this->assert_pushtrace_contains_context_id(event_handlers::API_COURSE_UPDATED, $course->id);

        // Course update triggers the same event, so we have to clear the push traces.
        course_processor::clear_push_traces();
        $course->summary = 'Awesome!';
        update_course($course);
        $this->assert_pushtrace_contains_context_id(event_handlers::API_COURSE_UPDATED, $course->id);

        // Course deletion triggers the event, so creating the Moodle course deletion event.
        $delevent = \core\event\course_deleted::create([
            'objectid' => $course->id,
            'context' => context_course::instance($course->id),
            'other' => [
                'shortname' => $course->shortname,
                'fullname' => $course->fullname,
                'idnumber' => $course->idnumber
            ]
        ]);
        $course->relativedatesmode = 0;
        $delevent->add_record_snapshot('course', $course);
        $delevent->trigger();
        $this->assert_pushtrace_contains_context_id(event_handlers::API_COURSE_DELETED, $course->id);
    }
}
