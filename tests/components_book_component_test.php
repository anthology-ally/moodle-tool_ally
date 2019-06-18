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
 * Testcase class for the tool_ally\componentsupport\book_component class.
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
 * Testcase class for the tool_ally\componentsupport\book_component class.
 *
 * @package   tool_ally
 * @author    Guy Thomas
 * @copyright Copyright (c) 2019 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_ally_components_book_component_testcase extends advanced_testcase {
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
    private $book;

    /**
     * @var stdClass
     */
    private $chapter;

    /**
     * @var book_component
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
        $this->book = $gen->create_module('book', ['course' => $this->course->id, 'introformat' => FORMAT_HTML]);
        $bookgenerator = self::getDataGenerator()->get_plugin_generator('mod_book');
        $this->chapter = $bookgenerator->create_chapter(['bookid' => $this->book->id, 'text' => 'Test book content']);
        $this->component = local_content::component_instance('book');
    }


    public function test_get_all_html_content_items() {
        $contentitems = $this->component->get_all_html_content($this->book->id);

        $this->assert_content_items_contain_item($contentitems,
            $this->book->id, 'book', 'book', 'intro');

        $this->assert_content_items_contain_item($contentitems,
            $this->chapter->id, 'book', 'book_chapters', 'content');
    }

    public function test_resolve_module_instance_id_from_book() {
        $instanceid = $this->component->resolve_module_instance_id('book', $this->book->id);
        $this->assertEquals($this->book->id, $instanceid);
    }

    public function test_resolve_module_instance_id_from_chapter() {
        $instanceid = $this->component->resolve_module_instance_id('book_chapters', $this->chapter->id);
        $this->assertEquals($this->book->id, $instanceid);
    }
}