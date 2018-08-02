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
        $this->forum = $gen->create_module($this->forumtype, ['course' => $this->course->id]);

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
}