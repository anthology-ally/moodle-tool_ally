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
use tool_ally\componentsupport\book_component;
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
    private $books;

    /**
     * @var stdClass
     */
    private $chapters;

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
        $this->component = local_content::component_instance('book');
    }

    private function setup_books($books = [], $chapters = [], $amount = 1, $emptyintro = false) {
        global $DB;
        $gen = $this->getDataGenerator();
        /** @var mod_book_generator $bookgenerator */
        $bookgenerator = self::getDataGenerator()->get_plugin_generator('mod_book');

        $this->books = $books;
        $this->chapters = $chapters;
        for ($i = 0; $i < $amount; $i++) {
            $book = $gen->create_module('book', ['course' => $this->course->id, 'introformat' => FORMAT_HTML]);
            if ($emptyintro) {
                $book->intro = '';
                $DB->update_record('book', $book);
            }
            $chapter = $bookgenerator->create_chapter([
                'bookid' => $book->id,
                'content' => "Test book $i content",
                'contentformat' => FORMAT_HTML]);
            $this->books[] = $book;
            $this->chapters[] = $chapter;
        }
    }

    public function test_get_all_html_content_items() {
        $this->setup_books();
        $contentitems = $this->component->get_all_html_content($this->books[0]->id);

        $this->assert_content_items_contain_item($contentitems,
            $this->books[0]->id, 'book', 'book', 'intro');

        $this->assert_content_items_contain_item($contentitems,
            $this->chapters[0]->id, 'book', 'book_chapters', 'content');
    }

    public function test_resolve_module_instance_id_from_book() {
        $this->setup_books();
        $instanceid = $this->component->resolve_module_instance_id('book', $this->books[0]->id);
        $this->assertEquals($this->books[0]->id, $instanceid);
    }

    public function test_resolve_module_instance_id_from_chapter() {
        $this->setup_books();
        $instanceid = $this->component->resolve_module_instance_id('book_chapters', $this->chapters[0]->id);
        $this->assertEquals($this->books[0]->id, $instanceid);
    }

    public function test_get_all_course_annotation_maps() {
        global $PAGE;
        $PAGE->set_pagetype('mod-book-view');

        $amount = 5;
        // Add books with intro.
        $this->setup_books([], [], $amount);
        // Add books without intro.
        $this->setup_books($this->books, $this->chapters, $amount, true);

        $cis = $this->component->get_annotation_maps($this->course->id);
        $intros = $cis['intros'];
        $chapters = $cis['chapters'];

        $introids = array_keys($intros);
        $chapterids = array_keys($chapters);
        $this->assertEquals(10, count($introids));
        $this->assertEquals(10, count($chapterids));
        for ($i = 0; $i < $amount; $i++) {
            $this->assertEquals('book:book:intro:' . $this->books[$i]->id, $intros[$introids[$i]]);
            $this->assertEquals('book:book_chapters:content:' . $this->chapters[$i]->id, $chapters[$chapterids[$i]]);
        }
    }
}