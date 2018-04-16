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

use tool_ally\content_processor;
use tool_ally\event_handlers;
use tool_ally\task\content_deletion_task;
/**
 * Tests for event handlers.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_ally_event_handlers_testcase extends advanced_testcase {

    public function setUp() {
        $this->resetAfterTest();

        set_config('pushurl', 'url', 'tool_ally');
        set_config('key', 'key', 'tool_ally');
        set_config('secret', 'secret', 'tool_ally');
        content_processor::clear_push_traces();
    }

    /**
     * @param string $eventname
     * @param string $entityid
     * @return array
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
                    return $row;
                }
            }
        }
        $this->fail('Push trace does not contain an entity id of '.$entityid);
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

        // Get sections for course and make sure push trace contains entity ids.
        $sections = $DB->get_records('course_sections', ['course' => $course->id]);
        foreach ($sections as $section) {
            $entityid = 'course:course_sections:summary:'.$section->id;
            $this->assert_pushtrace_contains_entity_id(event_handlers::API_CREATED, $entityid);
        }
    }

    public function test_course_updated() {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $course->fullname = 'Modified';
        $course->summary = 'Summary modified';
        $course->summaryformat = FORMAT_HTML;
        $DB->update_record('course', $course);

        course_updated::create([
            'objectid' => $course->id,
            'context' => context_course::instance($course->id),
            'other' => ['shortname' => $course->shortname]
        ])->trigger();

        $entityid = 'course:course:summary:'.$course->id;

        $this->assert_pushtrace_contains_entity_id(event_handlers::API_UPDATED, $entityid);
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
        $sections = $DB->get_records('course_sections', ['course' => $course->id]);
        $section = reset($sections);

        $section->summaryformat = FORMAT_HTML;
        course_section_updated::create([
            'objectid' => $section->id,
            'courseid' => $course->id,
            'context' => context_course::instance($course->id),
            'other' => array(
                'sectionnum' => $section->section
            )
        ])->trigger();

        $entityid = 'course:course_sections:summary:'.$section->id;
        $this->assert_pushtrace_contains_entity_id(event_handlers::API_UPDATED, $entityid);
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
        $course = $this->getDataGenerator()->create_course();

        $label = $this->getDataGenerator()->create_module('label',
            ['course' => $course->id, 'introformat' => FORMAT_HTML]);
        $entityid = 'label:label:intro:'.$label->id;
        list ($course, $cm) = get_course_and_cm_from_cmid($label->cmid);
        $label->intro = 'Updated intro';
        course_module_updated::create_from_cm($cm)->trigger();

        $this->assert_pushtrace_contains_entity_id(event_handlers::API_UPDATED, $entityid);
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

        $row = $DB->get_record('tool_ally_deleted_content', [
            'component' => 'label',
            'comptable' => 'label',
            'courseid' => (int) $course->id,
            'instanceid' => (int) $label->id
        ]);
        $this->assertNotEmpty($row);

        $cdt = new content_deletion_task();
        $cdt->execute();
        $cdt->execute(); // We have to execute again because first time just sets exec window.

        $row = $DB->get_record('tool_ally_deleted_content', [
            'component' => 'label',
            'comptable' => 'label',
            'courseid' => (int) $course->id,
            'instanceid' => (int) $label->id
        ]);
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
}