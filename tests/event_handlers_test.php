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
use \mod_glossary\event\entry_deleted;

use \mod_book\event\chapter_created;
use \mod_book\event\chapter_updated;
use \mod_book\event\chapter_deleted;

use tool_ally\content_processor;
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
        $this->resetAfterTest();

        set_config('pushurl', 'url', 'tool_ally');
        set_config('key', 'key', 'tool_ally');
        set_config('secret', 'secret', 'tool_ally');
        set_config('push_cli_only', 0, 'tool_ally');
        content_processor::clear_push_traces();
        content_processor::get_config(true);
    }

    /**
     * @param string $eventname
     * @param string $entityid
     * @return void
     * @throws coding_exception
     */
    private function assert_pushtrace_contains_entity_id($eventname, $entityid) {
        $pushtraces = content_processor::get_push_traces($eventname);
        $this->assertNotEmpty($pushtraces);
        if (!$pushtraces) {
            $this->fail('Push trace does not contain an entity id of '.$entityid);
        }
        foreach ($pushtraces as $pushtrace) {
            foreach ($pushtrace as $row) {
                if ($row['entity_id'] === $entityid) {
                    return;
                }
            }
        }
        $this->fail('Push trace does not contain an entity id of '.$entityid."\n\n".var_export($pushtraces, true));
        return;
    }

    /**
     * @param string $eventname
     * @param string $entityid
     * @throws coding_exception
     */
    private function assert_pushtrace_not_contains_entity_id($eventname, $entityid) {
        $pushtraces = content_processor::get_push_traces($eventname);
        if (!$pushtraces) {
            return;
        }
        foreach ($pushtraces as $pushtrace) {
            foreach ($pushtrace as $row) {
                if ($row['entity_id'] === $entityid) {
                    $this->fail('Push trace contains an entity id of '.$entityid);
                }
            }
        }
        return;
    }

    private function assert_pushtrace_not_contains_entity_regex($regex) {
        $eventtypes = content_processor::get_push_traces();
        if (!$eventtypes) {
            return;
        }

        foreach ($eventtypes as $pushtraces) {
            foreach ($pushtraces as $pushtrace) {
                foreach ($pushtrace as $row) {
                    if (preg_match($regex, $row['entity_id']) === 1) {
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

    private function assert_pushtrace_entity_contains_embeddedfileinfo($eventname, $entityid, $filename) {
        $pushtraces = content_processor::get_push_traces($eventname);
        $this->assertNotEmpty($pushtraces);
        if (!$pushtraces) {
            $this->fail('Push trace is empty');
        }
        foreach ($pushtraces as $pushtrace) {
            foreach ($pushtrace as $row) {
                if ($row['entity_id'] === $entityid) {
                    if (!empty($row['embedded_files']) && isset($row['embedded_files'][$filename])) {
                        return;
                    }
                }
            }
        }
        $this->fail('Push trace does not contain an entity id of '.$entityid);
        return;
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
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_CREATED, $entityid);
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
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_UPDATED, $entityid);

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
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_CREATED, $entityid);
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
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_UPDATED, $entityid0);

        // Make sure section 1 isn't in push trace as we haven't updated it.
        $entityid1 = 'course:course_sections:summary:'.$section1->id;
        $this->assert_pushtrace_not_contains_entity_id(event_handlers::API_UPDATED, $entityid1);

        // Get content for section 0 and check it contains default section name 'General' as title for intro section.
        $content = local_content::get_html_content_by_entity_id($entityid0);
        $this->assertEquals('General', $content->title);

        // Get content for section 1 and check it contains default section name 'Topic 1' as title for section 1.
        $content = local_content::get_html_content_by_entity_id($entityid1);
        $this->assertEquals('Topic 1', $content->title);

        // Update section1's title and content.
        $section1->name = 'Altered section name';

        $context = context_course::instance($course->id);
        $filename = 'testimage.png';
        $this->create_test_file($context->id, 'course', 'section', $section1->id, $filename);
        $section1->summary = 'Updated summary with img <img src="@@PLUGINFILE@@/'.rawurlencode($filename).'" alt="test alt" />';
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
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_UPDATED, $entityid1);

        // Ensure embedded file info is in push trace.
        $this->assert_pushtrace_entity_contains_embeddedfileinfo(event_handlers::API_UPDATED, $entityid1, $filename);

        // Get content for section 1 and check it contains custom section name as title for section 1.
        $content = local_content::get_html_content_by_entity_id($entityid1);
        $this->assertEquals($section1->name, $content->title);
    }

    public function test_module_created() {
        $course = $this->getDataGenerator()->create_course();
        // Assert that label with non FORMAT_HTML intro does not push.
        $label = $this->getDataGenerator()->create_module('label', ['course' => $course->id]);
        $entityid = 'label:label:intro:'.$label->id;
        list ($course, $cm) = get_course_and_cm_from_cmid($label->cmid);
        course_module_created::create_from_cm($cm)->trigger();
        $this->assert_pushtrace_not_contains_entity_id(event_handlers::API_CREATED, $entityid);

        // Assert that label with FORMAT_HTML intro pushes.
        $label = $this->getDataGenerator()->create_module('label',
                ['course' => $course->id, 'introformat' => FORMAT_HTML]);
        $entityid = 'label:label:intro:'.$label->id;
        list ($course, $cm) = get_course_and_cm_from_cmid($label->cmid);
        course_module_created::create_from_cm($cm)->trigger();
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_CREATED, $entityid);
    }

    public function test_module_updated() {
        global $DB;

        $course = $this->getDataGenerator()->create_course();

        $label = $this->getDataGenerator()->create_module('label',
            ['course' => $course->id, 'introformat' => FORMAT_HTML]);
        $context = context_module::instance($label->cmid);

        list ($course, $cm) = get_course_and_cm_from_cmid($label->cmid);

        context_module::instance($label->cmid);

        $filename = 'testimage.png';
        $this->create_test_file($context->id, 'mod_label', 'intro', 0, $filename);

        $label->intro = 'Updated intro with img <img src="@@PLUGINFILE@@/'.rawurlencode($filename).'" alt="test alt" />';
        $DB->update_record('label', $label);

        course_module_updated::create_from_cm($cm)->trigger();

        $entityid = 'label:label:intro:'.$label->id;
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_UPDATED, $entityid);
        $this->assert_pushtrace_entity_contains_embeddedfileinfo(event_handlers::API_UPDATED, $entityid, $filename);
    }

    public function test_module_deleted() {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $label = $this->getDataGenerator()->create_module('label',
            ['course' => $course->id, 'introformat' => FORMAT_HTML]);
        $entityid = 'label:label:intro:'.$label->id;
        list ($course, $cm) = get_course_and_cm_from_cmid($label->cmid);
        course_delete_module($cm->id);

        // Push should not have happened - it needs cron task to make it happen.
        $this->assert_pushtrace_not_contains_entity_id(event_handlers::API_DELETED, $entityid);

        $delfilter = [
            'component' => 'label',
            'comptable' => 'label',
            'courseid' => (int) $course->id,
            'comprowid' => (int) $label->id
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

        $this->assert_pushtrace_contains_entity_id(event_handlers::API_DELETED, $entityid);

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

        $this->assert_pushtrace_contains_entity_id(event_handlers::API_CREATED, $entityid);

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
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_CREATED, $entityid);

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
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_UPDATED, $entityid);
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
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_CREATED, $introentityid);
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_CREATED, $postentityid);
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

        $this->assert_pushtrace_contains_entity_id(event_handlers::API_CREATED, $glossaryentityid);

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
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_CREATED, $entityid);

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
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_UPDATED, $entityid);

        course_delete_module($glossary->cmid);

        // Note, there shouldn't be any deletion events at this point because deletes need the task to be dealt with.
        $this->assert_pushtrace_not_contains_entity_id(event_handlers::API_DELETED, $glossaryentityid);

        $cdt = new content_updates_task();
        $cdt->execute();
        $cdt->execute(); // We have to execute again because first time just sets exec window.

        // After running the task it has pushed the deletion event.
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_DELETED, $glossaryentityid);
    }

    public function test_book_events() {
        global $USER;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $book = $this->getDataGenerator()->create_module('book',
            ['course' => $course->id, 'introformat' => FORMAT_HTML]);
        $bookentityid = 'book:book:intro:'.$book->id;
        list ($course, $cm) = get_course_and_cm_from_cmid($book->cmid);
        course_module_created::create_from_cm($cm)->trigger();

        $this->assert_pushtrace_contains_entity_id(event_handlers::API_CREATED, $bookentityid);

        // Add a chapter.
        $record = new stdClass();
        $record->course = $course->id;
        $record->bookid = $book->id;
        $record->userid = $USER->id;
        $record->content = 'Test chapter content';
        $record->contentformat = FORMAT_HTML;

        $bookgenerator = self::getDataGenerator()->get_plugin_generator('mod_book');

        $chapter = $bookgenerator->create_chapter($record);


        $params = array(
            'context' => $cm->context,
            'objectid' => $chapter->id,
            'other' => array(
                'bookid' => $book->id
            )
        );
        $event = chapter_created::create($params);
        $event->add_record_snapshot('book_chapters', $chapter);
        $event->trigger();

        $entityid = 'book:book_chapters:content:'.$chapter->id;

        // Assert pushtrace contains chapter.
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_CREATED, $entityid);

        // Modify chapter.
        $chapter->content .= 'modified !!!';
        $params = array(
            'context' => $cm->context,
            'objectid' => $chapter->id,
            'other' => array(
                'bookid' => $book->id
            )
        );
        $event = chapter_updated::create($params);
        $event->add_record_snapshot('book_chapters', $chapter);
        $event->trigger();

        // Assert pushtrace contains updated chapter.
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_UPDATED, $entityid);

        course_delete_module($book->cmid);

        // Note, there shouldn't be any deletion events at this point because deletes need the task to be dealt with.
        $this->assert_pushtrace_not_contains_entity_id(event_handlers::API_DELETED, $bookentityid);

        $cdt = new content_updates_task();
        $cdt->execute();
        $cdt->execute(); // We have to execute again because first time just sets exec window.

        // After running the task it has pushed the deletion event.
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_DELETED, $bookentityid);
    }
}
