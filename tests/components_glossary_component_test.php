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
 * Testcase class for the tool_ally\componentsupport\glossary_component class.
 *
 * @package   tool_ally
 * @author    Guy Thomas
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_ally\local_content;
use tool_ally\componentsupport\glossary_component;
use tool_ally\testing\traits\component_assertions;

defined('MOODLE_INTERNAL') || die();

/**
 * Testcase class for the tool_ally\componentsupport\glossary_component class.
 *
 * @package   tool_ally
 * @author    Guy Thomas
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_ally_components_glossary_component_testcase extends advanced_testcase {
    use component_assertions;

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
    private $glossary;

    /**
     * @var stdClass
     */
    private $studententry;

    /**
     * @var stdClass
     */
    private $teacherentry;

    /**
     * @var glossary_component
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
        $this->glossary = $gen->create_module('glossary', ['course' => $this->course->id, 'introformat' => FORMAT_HTML]);
        $glossarygenerator = self::getDataGenerator()->get_plugin_generator('mod_glossary');

        // Add an entry by teacher - should show up in results.
        $this->setUser($this->teacher);
        $record = new stdClass();
        $record->course = $this->course->id;
        $record->glossary = $this->glossary->id;
        $record->userid = $this->teacher->id;
        $record->definitionformat = FORMAT_HTML;
        $this->teacherentry = $glossarygenerator->create_content($this->glossary, $record);

        // Add an entry by student - should NOT show up in results.
        $this->setUser($this->student);
        $record = new stdClass();
        $record->course = $this->course->id;
        $record->glossary = $this->glossary->id;
        $record->userid = $this->student->id;
        $record->definitionformat = FORMAT_HTML;
        $this->studententry = $glossarygenerator->create_content($this->glossary, $record);

        $this->component = local_content::component_instance('glossary');
    }

    public function test_get_approved_author_ids_for_context() {
        $authorids = $this->component->get_approved_author_ids_for_context($this->coursecontext);
        $this->assertContains($this->teacher->id, $authorids,
                'Teacher id '.$this->teacher->id.' should be in list of author ids.');
        $this->assertContains($this->admin->id, $authorids,
                'Admin id '.$this->admin->id.' should be in list of author ids.');
        $this->assertNotContains($this->student->id, $authorids,
                'Student id '.$this->student->id.' should NOT be in list of author ids.');
    }

    public function test_user_is_approved_author_type() {
        $this->assertFalse($this->component->user_is_approved_author_type($this->student->id, $this->coursecontext),
            'Student should not be approved author type');
        $this->assertTrue($this->component->user_is_approved_author_type($this->teacher->id, $this->coursecontext),
            'Teacher should be approved author type');
        $this->assertTrue($this->component->user_is_approved_author_type($this->admin->id, $this->coursecontext),
            'Admin should be approved author type');
    }


    public function test_get_entry_html_content_items() {
        $contentitems = phpunit_util::call_internal_method(
            $this->component, 'get_entry_html_content_items', [
                $this->course->id, $this->glossary->id
            ],
            get_class($this->component)
        );

        $this->assert_content_items_contain_item($contentitems,
            $this->teacherentry->id, 'glossary', 'glossary_entries', 'definition');

        $this->assert_content_items_not_contain_item($contentitems,
            $this->studententry->id, 'glossary', 'glossary_entries', 'definition');
    }

    public function test_resolve_module_instance_id_from_glossary() {
        $component = new glossary_component();
        $instanceid = $component->resolve_module_instance_id('glossary', $this->glossary->id);
        $this->assertEquals($this->glossary->id, $instanceid);
    }

    public function test_resolve_module_instance_id_from_entry() {
        $component = new glossary_component();
        $instanceid = $component->resolve_module_instance_id('glossary_entries', $this->studententry->id);
        $this->assertEquals($this->glossary->id, $instanceid);
    }

    public function test_get_all_course_annotation_maps() {
        global $PAGE;

        $cis = $this->component->get_annotation_maps($this->course->id);
        $this->assertEquals('glossary:glossary:intro:'.$this->glossary->id, reset($cis['intros']));
        $this->assertEmpty($cis['entries']);

        $cm = get_coursemodule_from_instance('glossary', $this->glossary->id, $this->course->id);
        $_GET['id'] = $cm->id;
        $PAGE->set_pagetype('mod-glossary-view');
        $cis = $this->component->get_annotation_maps($this->course->id);

        $this->assertEquals('glossary:glossary_entries:definition:'.$this->teacherentry->id, reset($cis['entries']));

    }
}