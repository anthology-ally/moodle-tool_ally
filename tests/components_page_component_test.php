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
 * Testcase class for the tool_ally\componentsupport\page_component class.
 *
 * @package   tool_ally
 * @author    Guy Thomas
 * @copyright Copyright (c) 2019 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_ally\local_content;
use tool_ally\componentsupport\glossary_component;
use tool_ally\testing\traits\component_assertions;
use tool_ally\webservice\course_content;
use tool_ally\models\component;
use tool_ally\models\component_content;

defined('MOODLE_INTERNAL') || die();

require_once('abstract_testcase.php');

/**
 * Testcase class for the tool_ally\componentsupport\page_component class.
 *
 * @package   tool_ally
 * @author    Guy Thomas
 * @copyright Copyright (c) 2019 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_ally_components_page_component_testcase extends tool_ally_abstract_testcase {
    use component_assertions;

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
    private $page;

    /**
     * @var glossary_component
     */
    private $component;

    public function setUp() {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $this->admin = get_admin();
        $this->course = $gen->create_course();
        $this->coursecontext = context_course::instance($this->course->id);
        $this->page = $gen->create_module('page',
            [
                'course' => $this->course->id,
                'introformat' => FORMAT_HTML,
                'intro' => 'Text in intro',
                'contentformat' => FORMAT_HTML,
                'content' => 'Text in content'
            ]
        );

        $this->component = local_content::component_instance('page');
    }

    public function test_list_intro_and_content() {
        $this->setAdminUser();
        $contentitems = course_content::service([$this->course->id]);
        $component = new component(0, 'page', 'page', 'intro', $this->course->id, 0, FORMAT_HTML, $this->page->name);
        $this->assert_component_is_in_array($component, $contentitems);
        $component = new component(0, 'page', 'page', 'content', $this->course->id, 0, FORMAT_HTML, $this->page->name);
        $this->assert_component_is_in_array($component, $contentitems);
    }

    public function test_get_all_html_content() {
        $items = local_content::get_all_html_content($this->page->id, 'page');
        $componentcontent = new component_content(
                $this->page->id, 'page', 'page', 'intro', $this->course->id, 0,
                FORMAT_HTML, $this->page->intro, $this->page->name);
        $this->assertTrue($this->component_content_is_in_array($componentcontent, $items));
    }

    public function test_resolve_module_instance_id() {
        $this->setAdminUser();
        $instanceid = $this->component->resolve_module_instance_id('page', $this->page->id);
        $this->assertEquals($this->page->id, $instanceid);
    }

    public function test_get_all_course_annotation_maps() {
        $cis = $this->component->get_annotation_maps($this->course->id);
        $this->assertEquals('page:page:intro:' . $this->page->id, reset($cis['intros']));
        $this->assertEquals('page:page:content:' . $this->page->id, reset($cis['content']));

        $gen = $this->getDataGenerator();
        $this->admin = get_admin();
        $this->course = $gen->create_course();
        $this->coursecontext = context_course::instance($this->course->id);
        $this->page = $gen->create_module('page',
                                          [
                                              'course' => $this->course->id,
                                          ]
        );

        $cis = $this->component->get_annotation_maps($this->course->id);
        $this->assertEquals([], $cis['intros']);
        $this->assertEquals([], $cis['content']);

    }
}