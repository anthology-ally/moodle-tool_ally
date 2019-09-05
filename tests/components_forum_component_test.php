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
 * Testcase class for the tool_ally\components\forum_component class.
 *
 * @package   tool_ally
 * @author    Guy Thomas
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_ally\local_content;
use tool_ally\componentsupport\forum_component;
use tool_ally\testing\traits\component_assertions;

defined('MOODLE_INTERNAL') || die();

/**
 * Testcase class for the tool_ally\components\forum_component class.
 *
 * @package   tool_ally
 * @author    Guy Thomas
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_ally_components_forum_component_testcase extends advanced_testcase {
    use component_assertions;

    /**
     * @var string
     */
    private $forumtype = 'forum';

    /**
     * @var stdClass
     */
    private $student;

    /**
     * @var stdClass
     */
    private $teacher;

    /**
     * @var stdClass
     */
    private $admin;

    /**
     * @var stdClass
     */
    private $course;

    /**
     * @var context_course
     */
    private $coursecontext;

    /**
     * @var stdClass
     */
    private $forum;

    /**
     * @var stdClass
     */
    private $studentdiscussion;

    /**
     * @var stdClass
     */
    private $teacherdiscussion;

    /**
     * @var forum_component
     */
    private $component;

    public function setUp() {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $this->student = $gen->create_user();
        $this->teacher = $gen->create_user();
        $this->admin = get_admin();
        $this->course = $gen->create_course();
        $this->coursecontext = context_course::instance($this->course->id);
        $gen->enrol_user($this->student->id, $this->course->id, 'student');
        $gen->enrol_user($this->teacher->id, $this->course->id, 'editingteacher');
        $forumdata = [
            'course' => $this->course->id,
            'introformat' => FORMAT_HTML,
            'intro' => '<p>My intro for forum type '.$this->forumtype.'</p>'
        ];
        $this->forum = $gen->create_module($this->forumtype, $forumdata);

        // Add a discussion / post by teacher - should show up in results.
        $this->setUser($this->teacher);
        $record = new stdClass();
        $record->course = $this->course->id;
        $record->forum = $this->forum->id;
        $record->userid = $this->teacher->id;
        $this->teacherdiscussion = self::getDataGenerator()->get_plugin_generator(
            'mod_'.$this->forumtype)->create_discussion($record);

        // Add a discussion / post by student - should NOT show up in results.
        $this->setUser($this->student);
        $record = new stdClass();
        $record->course = $this->course->id;
        $record->forum = $this->forum->id;
        $record->userid = $this->student->id;
        $this->studentdiscussion = self::getDataGenerator()->get_plugin_generator(
            'mod_'.$this->forumtype)->create_discussion($record);

        $this->component = local_content::component_instance($this->forumtype);
    }

    private function assert_content_items_contain_discussion_post(array $items, $discussionid) {
        global $DB;

        $post = $DB->get_record($this->forumtype.'_posts', ['discussion' => $discussionid, 'parent' => 0]);
        $this->assert_content_items_contain_item($items,
            $post->id, $this->forumtype, $this->forumtype.'_posts', 'message');
    }

    private function assert_content_items_not_contain_discussion_post(array $items, $discussionid) {
        global $DB;

        $post = $DB->get_record($this->forumtype.'_posts', ['discussion' => $discussionid, 'parent' => 0]);
        $this->assert_content_items_not_contain_item($items,
            $post->id, $this->forumtype, $this->forumtype.'_posts', 'message');
    }

    public function test_get_discussion_html_content_items() {
        $contentitems = phpunit_util::call_internal_method(
            $this->component, 'get_discussion_html_content_items', [
                $this->course->id, $this->forum->id
            ],
            get_class($this->component)
        );

        $this->assert_content_items_contain_discussion_post($contentitems, $this->teacherdiscussion->id);
        $this->assert_content_items_not_contain_discussion_post($contentitems, $this->studentdiscussion->id);
    }

    public function test_resolve_module_instance_id_from_forum() {
        $component = new forum_component();
        $instanceid = $component->resolve_module_instance_id($this->forumtype, $this->forum->id);
        $this->assertEquals($this->forum->id, $instanceid);
    }

    public function test_resolve_module_instance_id_from_post() {
        global $DB;

        $discussion = $this->studentdiscussion;
        $post = $DB->get_record($this->forumtype.'_posts', ['discussion' => $discussion->id, 'parent' => 0]);
        $component = new forum_component();
        $instanceid = $component->resolve_module_instance_id($this->forumtype.'_posts', $post->id);
        $this->assertEquals($this->forum->id, $instanceid);
    }

    public function test_get_all_course_annotation_maps() {
        global $PAGE, $DB;

        $cis = $this->component->get_annotation_maps($this->course->id);
        $expectedannotation = $this->forumtype.':'.$this->forumtype.':intro:'.$this->forum->id;
        $this->assertEquals($expectedannotation, reset($cis['intros']));
        $this->assertEmpty($cis['posts']);

        // Make sure teacher post shows up in annotation maps.
        $PAGE->set_pagetype('mod-'.$this->forumtype.'-discuss');
        $_GET['d'] = $this->teacherdiscussion->id;
        $cis = $this->component->get_annotation_maps($this->course->id);
        $post = $DB->get_record($this->forumtype.'_posts', ['discussion' => $this->teacherdiscussion->id, 'parent' => 0]);
        $expectedannotation = $this->forumtype.':'.$this->forumtype.'_posts:message:'.$post->id;
        $this->assertEquals($expectedannotation, $cis['posts'][$post->id]);

        // Make sure student post does not show up in annotation maps.
        $_GET['d'] = $this->studentdiscussion->id;
        $cis = $this->component->get_annotation_maps($this->course->id);
        $this->assertEmpty($cis['posts']);
    }
}