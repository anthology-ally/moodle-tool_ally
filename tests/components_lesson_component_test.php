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
 * Testcase class for the tool_ally\componentsupport\lesson_component class.
 *
 * @package   tool_ally
 * @author    Guy Thomas
 * @copyright Copyright (c) 2019 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_ally\local_content;
use tool_ally\componentsupport\glossary_component;
use tool_ally\testing\traits\component_assertions;

defined('MOODLE_INTERNAL') || die();

/**
 * Testcase class for the tool_ally\componentsupport\lesson_component class.
 *
 * @package   tool_ally
 * @author    Guy Thomas
 * @copyright Copyright (c) 2019 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_ally_components_lesson_component_testcase extends advanced_testcase {
    use component_assertions;

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
    private $lesson;

    /**
     * @var stdClass
     */
    private $lessonpage;

    /**
     * @var stdClass
     */
    private $lessonquestion;

    /**
     * @var lesson_component
     */
    private $component;

    public function setUp() {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $this->teacher = $gen->create_user();
        $this->admin = get_admin();
        $this->setAdminUser();
        $this->course = $gen->create_course();
        $this->coursecontext = context_course::instance($this->course->id);
        $gen->enrol_user($this->teacher->id, $this->course->id, 'editingteacher');
        $this->lesson = $gen->create_module('lesson', ['course' => $this->course->id, 'introformat' => FORMAT_HTML]);
        $lessongenerator = self::getDataGenerator()->get_plugin_generator('mod_lesson');
        $this->lessonpage = $lessongenerator->create_content($this->lesson, ['text' => 'Test lesson content']);
        $this->lessonquestion = $lessongenerator->create_question_truefalse($this->lesson);

        $this->component = local_content::component_instance('lesson');
    }


    public function test_get_all_html_content_items() {
        $contentitems = $this->component->get_all_html_content($this->lesson->id);

        $this->assert_content_items_contain_item($contentitems,
            $this->lesson->id, 'lesson', 'lesson', 'intro');

        $this->assert_content_items_contain_item($contentitems,
            $this->lessonpage->id, 'lesson', 'lesson_pages', 'contents');

        $this->assert_content_items_contain_item($contentitems,
            $this->lessonquestion->id, 'lesson', 'lesson_pages', 'contents');

    }

    public function test_resolve_module_instance_id_from_lesson() {
        $instanceid = $this->component->resolve_module_instance_id('lesson', $this->lesson->id);
        $this->assertEquals($this->lesson->id, $instanceid);
    }

    public function test_resolve_module_instance_id_from_page() {
        $instanceid = $this->component->resolve_module_instance_id('lesson_pages', $this->lessonpage->id);
        $this->assertEquals($this->lesson->id, $instanceid);
    }

    public function test_resolve_module_instance_id_from_question() {
        $instanceid = $this->component->resolve_module_instance_id('lesson_pages', $this->lessonquestion->id);
        $this->assertEquals($this->lesson->id, $instanceid);
    }

    public function test_resolve_module_instance_id_from_answer() {
        global $DB;

        $answers = $DB->get_records('lesson_answers', ['pageid' => $this->lessonquestion->id]);
        foreach ($answers as $answer) {
            $instanceid = $this->component->resolve_module_instance_id('lesson_answers', $answer->id);
            $this->assertEquals($this->lesson->id, $instanceid);
        }
    }

    public function test_get_all_course_annotation_maps() {
        global $DB;

        $cis = $this->component->get_annotation_maps($this->course->id);
        $this->assertEquals('lesson:lesson:intro:' . $this->lesson->id, reset($cis['intros']));
        $this->assertEquals('lesson:lesson_pages:contents:' . $this->lessonquestion->id, reset($cis['lesson_pages']));

        $answers = $DB->get_records('lesson_answers', ['pageid' => $this->lessonquestion->id]);
        $a = 0;
        foreach ($answers as $answer) {
            $a++;
            $key = $this->lessonquestion->id.'_'.$answer->id.'_'.$a;
            $this->assertEquals('lesson:lesson_answers:answer:'.$answer->id, $cis['lesson_answers'][$key]);
        }
    }
}