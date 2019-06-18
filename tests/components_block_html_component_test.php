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
 * Testcase class for the tool_ally\componentsupport\block_html_component class.
 *
 * @package   tool_ally
 * @author    Guy Thomas
 * @copyright Copyright (c) 2019 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_ally\local_content;
use tool_ally\models\component;
use tool_ally\componentsupport\block_html_component;
use tool_ally\webservice\course_content;
use tool_ally\testing\traits\component_assertions;
use block_html\search_content_testcase;

defined('MOODLE_INTERNAL') || die();

require_once('abstract_testcase.php');

/**
 * Testcase class for the tool_ally\componentsupport\block_html_component class.
 *
 * @package   tool_ally
 * @author    Guy Thomas
 * @copyright Copyright (c) 2019 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_ally_components_block_html_component_testcase extends tool_ally_abstract_testcase {
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
     * @var block_html_component
     */
    private $component;

    private $block;

    public function setUp() {
        global $CFG, $USER;

        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $this->admin = get_admin();
        $this->setAdminUser();
        $this->course = $gen->create_course();
        $this->coursecontext = context_course::instance($this->course->id);
        require_once($CFG->dirroot.'/blocks/html/tests/search_content_test.php');

        $sctc = new search_content_testcase();

        $this->block = phpunit_util::call_internal_method($sctc, 'create_block', ['course' => $this->course], get_class($sctc));
        // Change block settings to add some text and a file.
        $itemid = file_get_unused_draft_itemid();
        $fs = get_file_storage();
        $usercontext = \context_user::instance($USER->id);
        $fs->create_file_from_string(['component' => 'user', 'filearea' => 'draft',
            'contextid' => $usercontext->id, 'itemid' => $itemid, 'filepath' => '/',
            'filename' => 'file.txt'], 'File content');
        $data = (object)['title' => 'Block title', 'text' => ['text' => '<div>Block html</div>',
            'itemid' => $itemid, 'format' => FORMAT_HTML]];
        $this->block->instance_config_save($data);
        $page = phpunit_util::call_internal_method($sctc, 'construct_page',  ['course' => $this->course], get_class($sctc));
        $blocks = $page->blocks->get_blocks_for_region($page->blocks->get_default_region());
        $this->block = end($blocks);
        $this->component = local_content::component_instance('block_html');
    }

    public function test_list_content() {
        $this->setAdminUser();
        $id = $this->block->context->instanceid;
        $contentitems = course_content::service([$this->course->id]);
        $component = new component(
            $id, 'block_html', 'block_instances', 'configdata',
            $this->course->id, 0, FORMAT_HTML, $this->block->title);
        $this->assert_component_is_in_array($component, $contentitems);

    }

    public function test_get_all_html_content_items() {
        $contentitems = $this->component->get_all_html_content($this->block->context->instanceid);

        $this->assert_content_items_contain_item($contentitems,
            $this->block->context->instanceid, 'block_html', 'block_instances', 'configdata');
    }
}