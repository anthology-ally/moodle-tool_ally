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
 * Testcase class for the tool_ally\componentsupport\question_component class.
 *
 * @package   tool_ally
 * @author    Guy Thomas
 * @copyright Copyright (c) 2019 Open LMS (https://www.openlms.net) / 2023 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_ally;

use tool_ally\local_content;
use tool_ally\testing\traits\component_assertions;
use tool_ally\webservice\course_content;
use tool_ally\componentsupport\question_component;
use tool_ally\componentsupport\component_base;

defined('MOODLE_INTERNAL') || die();

require_once('abstract_testcase.php');

/**
 * Testcase class for the tool_ally\componentsupport\page_component class.
 *
 * @package   tool_ally
 * @author    Guy Thomas
 * @copyright Copyright (c) 2019 Open LMS (https://www.openlms.net) / 2023 Anthology Inc. and its affiliates
 * @group     tool_ally
 * @group     ally
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class components_question_component_test extends abstract_testcase {
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

    /**
     * @var stdClass
     */
    private object $quest1;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $this->admin = get_admin();
        $this->course = $gen->create_course();
        $this->coursecontext = \context_course::instance($this->course->id);
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $qcat1 = $generator->create_question_category([
            'name' => 'My category', 'sortorder' => 1, 'idnumber' => 'myqcat', ]);
        $this->quest1 = $generator->create_question(
            'shortanswer',
            null,
            ['name' => 'sa1', 'category' => $qcat1->id, 'idnumber' => 'myquest_3']
        );

        $this->component = local_content::component_instance('question');
    }

    /**
     * Test question component type.
     *
     * @covers \tool_ally\componentsupport\question_component::component_type
     */
    public function test_component_type(): void {
        $type = question_component::component_type();
        $this->assertEquals(component_base::TYPE_CORE, $type);
    }

    /**
     * Test file URL properties.
     *
     * @covers \tool_ally\componentsupport\question_component::replace_file_links
     */
    public function test_fileurlproperties(): void {
        $pluginfileurl = 'http://moodle.test/pluginfile.php/16/question/questiontext/1/1/1/test.odt';
        $urlprops = question_component::fileurlproperties($pluginfileurl);

        $this->assertEquals(16, $urlprops->contextid);
        $this->assertEquals('question', $urlprops->component);
        $this->assertEquals('questiontext', $urlprops->filearea);
        $this->assertEquals('1', $urlprops->itemid);
        $this->assertEquals('test.odt', $urlprops->filename);
    }

    /**
     * Test getting question data.
     *
     * @covers \tool_ally\componentsupport\question_component::get_question
     */
    public function test_get_question(): void {
        $quest = \phpunit_util::call_internal_method(
            $this->component,
            'get_question',
            [$this->quest1->id],
            question_component::class
        );
        $this->assertEquals((int) $this->quest1->id, (int) $quest->id);
        $this->assertEquals($this->quest1->name, $quest->name);
        $this->assertEquals($this->quest1->idnumber, $quest->idnumber);
    }

    /**
     * @dataProvider question_type_file_link_target_provider
     * @param string $qtype
     * @param string $area
     * @param array|null $expected
     */
    public function test_resolve_question_type_file_link_target($qtype, $area, ?array $expected): void {
        $target = \phpunit_util::call_internal_method(
            $this->component,
            'resolve_question_type_file_link_target',
            [$qtype, $area],
            question_component::class
        );

        $this->assertSame($expected, $target);
    }

    /**
     * @return array
     */
    public static function question_type_file_link_target_provider(): array {
        return [
            'ddimageortext' => ['ddimageortext', 'correctfeedback', ['qtype_ddimageortext', 'questionid']],
            'ddmarker' => ['ddmarker', 'correctfeedback', ['qtype_ddmarker', 'questionid']],
            'ddmatch' => ['ddmatch', 'correctfeedback', ['qtype_ddmatch_options', 'questionid']],
            'ddmatch unsupported area' => ['ddmatch', 'questiontext', null],
            'ddwtos' => ['ddwtos', 'correctfeedback', ['question_ddwtos', 'questionid']],
            'gapfill' => ['gapfill', 'correctfeedback', ['question_gapfill', 'question']],
            'gapselect' => ['gapselect', 'correctfeedback', ['question_gapselect', 'questionid']],
            'match' => ['match', 'correctfeedback', ['qtype_match_options', 'questionid']],
            'multichoice' => ['multichoice', 'correctfeedback', ['qtype_multichoice_options', 'questionid']],
            'randomsamatch' => ['randomsamatch', 'correctfeedback', ['qtype_randomsamatch_options', 'questionid']],
            'unsupported' => ['shortanswer', 'correctfeedback', null],
        ];
    }

    /**
     * A deleted answer record should be ignored when its file cleanup runs.
     *
     * @covers \tool_ally\componentsupport\question_component::resolve_file_link_target
     * @covers \tool_ally\componentsupport\question_component::remove_file_links
     */
    public function test_remove_file_links_ignores_missing_answer(): void {
        $file = get_file_storage()->create_file_from_string([
            'contextid' => $this->coursecontext->id,
            'component' => 'question',
            'filearea' => 'answer',
            'itemid' => 999999,
            'filepath' => '/',
            'filename' => 'missing-answer.png',
        ], 'test');

        $this->component->setup_file_and_validate($file->get_filename(), $file);

        $this->assertFalse($this->component->remove_file_links_with_result(['/missing-answer.png']));
    }

    /**
     * Test listing intro and content.
     *
     * @covers \tool_ally\componentsupport\question_component::list_intro_and_content
     */
    public function test_list_intro_and_content(): void {
        $this->markTestSkipped('HTML content not yet supported');
    }

    /**
     * Test getting all HTML content.
     *
     * @covers \tool_ally\componentsupport\question_component::get_all_html_content
     */
    public function test_get_all_html_content(): void {
        $this->markTestSkipped('HTML content not yet supported');
    }

    /**
     * Test getting all course annotation maps.
     *
     * @covers \tool_ally\componentsupport\question_component::get_all_course_annotation_maps
     */
    public function test_get_all_course_annotation_maps(): void {
        $this->markTestSkipped('HTML content not yet supported');
    }
}
