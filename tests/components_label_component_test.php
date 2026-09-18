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
 * Testcase class for the tool_ally\componentsupport\label_component class.
 *
 * @package   tool_ally
 * @author    Eric Merrill
 * @copyright Copyright (c) 2021 Open LMS (https://www.openlms.net) / 2023 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_ally;

use tool_ally\componentsupport\label_component;
use tool_ally\local_content;

defined('MOODLE_INTERNAL') || die();

require_once('abstract_testcase.php');

/**
 * Testcase class for the tool_ally\componentsupport\label_component class.
 *
 * @package   tool_ally
 * @author    Eric Merrill
 * @copyright Copyright (c) 2021 Open LMS (https://www.openlms.net) / 2023 Anthology Inc. and its affiliates
 * @group     tool_ally
 * @group     ally
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class components_label_component_test extends abstract_testcase {
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
    private $label;

    /**
     * @var label_component
     */
    private $component;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $this->admin = get_admin();
        $this->course = $gen->create_course();
        $this->coursecontext = \context_course::instance($this->course->id);
        $this->label = $gen->create_module(
            'label',
            [
                'course' => $this->course->id,
                'introformat' => FORMAT_HTML,
                'intro' => 'Text in intro',
            ]
        );

        $this->component = local_content::component_instance('label');
    }

    /**
     * Test if file in use detection is working with this module.
     *
     * @covers \tool_ally\componentsupport\label_component::check_file_in_use
     */
    public function test_check_file_in_use(): void {
        $context = \context_module::instance($this->label->cmid);

        $usedfiles = [];
        $unusedfiles = [];

        // Check the intro.
        [$usedfiles[], $unusedfiles[]] = $this->check_html_files_in_use(
            $context,
            'mod_label',
            $this->label->id,
            'label',
            'intro'
        );

        // This will double check that file iterator is working as expected.
        $this->check_file_iterator_exclusion($context, $usedfiles, $unusedfiles);
    }

    /**
     * A label's "Title in course index" must be used as-is, regardless of its content - even
     * image-only content whose alt text would otherwise produce a garbled derived title
     * (AB#191345).
     *
     * @covers \tool_ally\componentsupport\label_component::get_html_content
     */
    public function test_title_uses_course_index_name_regardless_of_content(): void {
        global $DB;

        $DB->update_record('label', (object) [
            'id' => $this->label->id,
            'name' => 'My Custom Title',
            'intro' => '<img src="@@PLUGINFILE@@/pic.png" alt="A random gibberish alt description" />',
        ]);

        $content = $this->component->get_html_content($this->label->id, 'label', 'intro', $this->course->id);

        $this->assertEquals('My Custom Title', $content->title);
    }

    /**
     * A label with no "Title in course index" whose content is only an image with a non-empty alt
     * attribute must not surface that alt text (bracketed by html_to_text()) as if it were a real
     * title (AB#191345).
     *
     * @covers \tool_ally\componentsupport\label_component::get_html_content
     * @covers \tool_ally\componentsupport\label_component::get_label_title_from_content
     */
    public function test_title_falls_back_when_content_is_only_an_image_with_alt_text(): void {
        global $DB;

        $DB->update_record('label', (object) [
            'id' => $this->label->id,
            'name' => '',
            'intro' => '<img src="@@PLUGINFILE@@/pic.png" alt="A random gibberish alt description" />',
        ]);

        $content = $this->component->get_html_content($this->label->id, 'label', 'intro', $this->course->id);

        $this->assertEquals(get_string('modulename', 'label'), $content->title);
    }

    /**
     * A label with no "Title in course index" whose content is only an image with an empty alt
     * attribute must fall back to the module's generic display name, not an empty or bracketed
     * title (AB#191345).
     *
     * @covers \tool_ally\componentsupport\label_component::get_html_content
     * @covers \tool_ally\componentsupport\label_component::get_label_title_from_content
     */
    public function test_title_falls_back_when_content_is_only_an_image_with_no_alt_text(): void {
        global $DB;

        $DB->update_record('label', (object) [
            'id' => $this->label->id,
            'name' => '',
            'intro' => '<img src="@@PLUGINFILE@@/pic.png" alt="" />',
        ]);

        $content = $this->component->get_html_content($this->label->id, 'label', 'intro', $this->course->id);

        $this->assertEquals(get_string('modulename', 'label'), $content->title);
    }

    /**
     * A label with no "Title in course index" whose content is genuine text alongside an image
     * must still derive its title from that text, ignoring the image, exactly as it did before
     * AB#191345 was fixed.
     *
     * @covers \tool_ally\componentsupport\label_component::get_html_content
     * @covers \tool_ally\componentsupport\label_component::get_label_title_from_content
     */
    public function test_title_derived_from_text_alongside_an_image(): void {
        global $DB;

        $DB->update_record('label', (object) [
            'id' => $this->label->id,
            'name' => '',
            'intro' => '<p>A real description</p><img src="@@PLUGINFILE@@/pic.png" alt="A random gibberish alt description" />',
        ]);

        $content = $this->component->get_html_content($this->label->id, 'label', 'intro', $this->course->id);

        $this->assertEquals('A real description', $content->title);
    }

    /**
     * A label created through the normal Moodle flow with no explicit "Title in course index" and
     * plain text content - core's own label_add_instance() (mod/label/lib.php) derives and
     * persists a name from that text at creation time - must keep using that name unaffected by
     * AB#191345.
     *
     * @covers \tool_ally\componentsupport\label_component::get_html_content
     */
    public function test_title_unaffected_for_plain_text_content(): void {
        $content = $this->component->get_html_content($this->label->id, 'label', 'intro', $this->course->id);

        $this->assertEquals('Text in intro', $content->title);
    }
}
