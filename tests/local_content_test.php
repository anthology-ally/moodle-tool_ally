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
 * Tests for local content library.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_ally\local_content;
use tool_ally\models\component;
use tool_ally\models\component_content;
use tool_ally\componentsupport\label_component;
use tool_ally\componentsupport\course_component;
use tool_ally\componentsupport\interfaces\html_content as iface_html_content;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/abstract_testcase.php');

/**
 * Tests for local content library.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_ally_local_content_testcase extends tool_ally_abstract_testcase {

    public function test_component_supports_html_content() {

        $supported = phpunit_util::call_internal_method(
                null, 'component_supports_html_content', ['label'],
                tool_ally\local_content::class);

        $this->assertEquals(true, $supported);

        $supported = phpunit_util::call_internal_method(
            null, 'component_supports_html_content', ['unknowncomponent'],
            tool_ally\local_content::class);

        $this->assertEquals(false, $supported);
    }

    public function test_list_html_content_supported_components() {
        $list = local_content::list_html_content_supported_components();
        $this->assertContains('course', $list);
        $this->assertContains('mod_label', $list);
    }

    public function test_component_instance() {
        $labelcomp = local_content::component_instance('label');
        $this->assertInstanceOf(label_component::class, $labelcomp);

        $coursecomp = local_content::component_instance('course');
        $this->assertInstanceOf(course_component::class, $coursecomp);
    }

    public function test_get_course_html_content_items() {
        global $DB;

        $this->resetAfterTest();
        $roleid = $this->assignUserCapability('moodle/course:view', context_system::instance()->id);
        $this->assignUserCapability('moodle/course:viewhiddencourses', context_system::instance()->id, $roleid);

        $coursesummary = '<p>My course summary</p>';
        $course = $this->getDataGenerator()->create_course(
            ['summary' => $coursesummary, 'summaryformat' => FORMAT_HTML]);
        $expectedcourse = new component(
            $course->id,
            'course',
            'course',
            'summary',
            $course->id,
            $course->timemodified,
            $course->summaryformat,
            $course->fullname
        );

        $section0summary = '<p>First section summary</p>';
        $section = $this->getDataGenerator()->create_course_section(
            ['section' => 0, 'course' => $course->id]);
        $DB->update_record('course_sections', (object) [
            'id' => $section->id,
            'summary' => $section0summary,
            'summaryformat' => FORMAT_HTML
        ]);
        $section = $DB->get_record('course_sections', ['id' => $section->id]);
        $expectedsection = new component(
            $section->id,
            'course',
            'course_sections',
            'summary',
            $course->id,
            $section->timemodified,
            $section->summaryformat,
            'Topic 0' // Default section name for section 0 where no section name set.
        );

        $labelintro = '<p>My original intro content</p>';
        $label = $this->getDataGenerator()->create_module('label',
            ['course' => $course->id, 'intro' => $labelintro, 'introformat' => FORMAT_HTML]);
        $expectedlabel = new component(
            $label->id,
            'label',
            'label',
            'intro',
            $course->id,
            $label->timemodified,
            $label->introformat,
            'My original intro content'
        );

        $contents = local_content::get_course_html_content_items('course', $course->id);
        $this->assertTrue(in_array($expectedcourse, $contents));
        $this->assertTrue(in_array($expectedsection, $contents));

        $contents = local_content::get_course_html_content_items('label', $course->id);
        $this->assertTrue(in_array($expectedlabel, $contents));

        $contents = local_content::get_course_html_content_items('course', $course->id);
        $expectedtitle = $contents[1]->title;
        $contents = local_content::get_html_content($section->id, 'course', 'course_sections', 'summary', $course->id, true);
        $this->assertEquals($expectedtitle, $contents->title);

        $section2 = $this->getDataGenerator()->create_course_section(
            ['section' => 1, 'course' => $course->id]);
        $DB->update_record('course_sections', (object) [
            'id' => $section2->id,
            'summary' => $section0summary,
            'summaryformat' => FORMAT_HTML,
            'title' => '',
            'timemodified' => 0,
        ]);

        $contents = local_content::get_course_html_content_items('course', $course->id);
        // Default title.
        $expectedtitle = $contents[2]->title;
        $this->assertEquals('Topic 1', $expectedtitle);

        $expectedtimemodified = $course->timecreated;
        $contents = local_content::get_html_content($section2->id, 'course', 'course_sections', 'summary', $course->id, true);
        $this->assertEquals($expectedtitle, $contents->title);
        $this->assertEquals($expectedtimemodified, $contents->timemodified);
    }

    public function test_get_replace_html_content() {

        $this->resetAfterTest();

        $roleid = $this->assignUserCapability('moodle/course:view', context_system::instance()->id);
        $this->assignUserCapability('moodle/course:viewhiddencourses', context_system::instance()->id, $roleid);

        $content = local_content::get_html_content(9999, 'course', 'course', 'summary');
        $this->assertEquals(null, $content);

        $coursesummary = '<p>My course summary</p>';
        $course = $this->getDataGenerator()->create_course(
            ['summary' => $coursesummary, 'summaryformat' => FORMAT_HTML]);
        $expectedcourse = new component_content(
            $course->id,
            'course',
            'course',
            'summary',
            null,
            $course->timemodified,
            $course->summaryformat,
            $coursesummary,
            $course->fullname,
            new moodle_url('/course/edit.php?id='.$course->id).''
        );

        $content = local_content::get_html_content($course->id, 'course', 'course', 'summary');
        $this->assertEquals($expectedcourse, $content);

        $replacement = '<p>Content replaced</p>';
        $result = local_content::replace_html_content($course->id, 'course', 'course', 'summary', $replacement);
        $this->assertTrue($result);

        $expectedcourse->content = $replacement;
        $expectedcourse->contenthash = sha1($replacement);
        $content = local_content::get_html_content($course->id, 'course', 'course', 'summary');
        $this->assertEquals($expectedcourse, $content);
    }

    public function test_get_annotation() {
        $this->resetAfterTest();

        $coursesummary = '<p>My course summary</p>';
        $course = $this->getDataGenerator()->create_course(
            ['summary' => $coursesummary, 'summaryformat' => FORMAT_HTML]);
        $context = context_course::instance($course->id);
        $annotation = local_content::get_annotation($context);
        $this->assertEmpty($annotation); // Course summaries / sections can't be annotated via php.

        $label = $this->getDataGenerator()->create_module('label',
            ['course' => $course->id]);
        $context = context_module::instance($label->cmid);
        $annotation = local_content::get_annotation($context);
        $expected = 'label:label:intro:'.$label->id;
        $this->assertEquals($expected, $annotation);
    }

    public function test_get_null_content() {
        $this->resetAfterTest();
        // These components may add things to the generic html component_content object or null.
        // When it is null, it is converted to stdClass, this should be avoided.
        $compdata = [
            'assign' => (object) [
                'table' => 'assign',
                'area' => 'intro'
            ],
            'book' => (object) [
                'table' => 'book',
                'area' => 'intro'
            ],
            'label' => (object) [
                'table' => 'label',
                'area' => 'intro'
            ],
            'page' => (object) [
                'table' => 'page',
                'area' => 'intro'
            ],
        ];

        foreach ($compdata as $compkey => $meta) {
            /** @var iface_html_content $comp */
            $comp = local_content::component_instance($compkey);
            // Id -1 does not exist.
            $content = $comp->get_html_content(-1, $meta->table, $meta->area);
            $this->assertNull($content, 'Invalid content for ' . $compkey . ' should be null.');
        }
    }
}
