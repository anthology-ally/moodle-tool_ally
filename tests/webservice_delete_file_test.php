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
 * Test for file delete webservice.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2017 Open LMS (https://www.openlms.net) / 2023 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_ally;

use tool_ally\abstract_testcase;
use tool_ally\webservice\delete_file;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/abstract_testcase.php');

/**
 * Test for file delete webservice.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2017 Open LMS (https://www.openlms.net) / 2023 Anthology Inc. and its affiliates
 * @group     tool_ally
 * @group     ally
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @runTestsInSeparateProcesses
 */
class webservice_delete_file_test extends abstract_testcase {
    /**
     * Test the web service.
     *
     */
    public function test_service(): void {
        global $DB;

        $this->resetAfterTest();

        $datagen = $this->getDataGenerator();

        $roleid = $this->assignUserCapability('moodle/course:view', \context_system::instance()->id);
        $this->assignUserCapability('moodle/course:viewhiddencourses', \context_system::instance()->id, $roleid);
        $this->assignUserCapability('moodle/course:managefiles', \context_system::instance()->id, $roleid);

        $teacher = $datagen->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);

        $course      = $datagen->create_course();
        $resource    = $datagen->create_module('resource', ['course' => $course->id]);
        $file        = $this->get_resource_file($resource);

        $datagen->enrol_user($teacher->id, $course->id, $teacherrole->id);

        $return = delete_file::service($file->get_pathnamehash(), $teacher->id);
        $return = \external_api::clean_returnvalue(delete_file::service_returns(), $return);

        $this->assertSame($return['success'], true);

        // Fetching the new deleted file throws an exception.
        $this->expectException(\coding_exception::class);
        $this->get_resource_file($resource);
    }

    public function test_service_invalid_user(): void {
        $this->resetAfterTest();

        $roleid = $this->assignUserCapability('moodle/course:view', \context_system::instance()->id);
        $this->assignUserCapability('moodle/course:viewhiddencourses', \context_system::instance()->id, $roleid);
        $this->assignUserCapability('moodle/course:managefiles', \context_system::instance()->id, $roleid);

        $otheruser = $this->getDataGenerator()->create_user();

        $course      = $this->getDataGenerator()->create_course();
        $resource    = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
        $file        = $this->get_resource_file($resource);

        $this->expectException(\moodle_exception::class);
        $return = delete_file::service($file->get_pathnamehash(), $otheruser->id);
        $return = \external_api::clean_returnvalue(delete_file::service_returns(), $return);

        // Check file hasn't been deleted.
        $this->assertInstanceOf(\stored_file, $this->get_resource_file($resource));
    }

    public function test_service_invalid_file(): void {
        global $DB;

        $this->resetAfterTest();

        $datagen = $this->getDataGenerator();

        $roleid = $this->assignUserCapability('moodle/course:view', \context_system::instance()->id);
        $this->assignUserCapability('moodle/course:viewhiddencourses', \context_system::instance()->id, $roleid);
        $this->assignUserCapability('moodle/course:managefiles', \context_system::instance()->id, $roleid);

        $teacher = $datagen->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);

        $course      = $datagen->create_course();

        $datagen->enrol_user($teacher->id, $course->id, $teacherrole->id);
        $nonexistantfile = 'BADC0FFEE';
        $this->expectException(\moodle_exception::class);
        delete_file::service($nonexistantfile, $teacher->id);
    }

    /**
     * Set up a course with a teacher who is allowed to delete files.
     *
     * @return array [course, teacher]
     */
    private function setup_course_and_teacher(): array {
        $datagen = $this->getDataGenerator();

        $roleid = $this->assignUserCapability('moodle/course:view', \context_system::instance()->id);
        $this->assignUserCapability('moodle/course:viewhiddencourses', \context_system::instance()->id, $roleid);
        $this->assignUserCapability('moodle/course:managefiles', \context_system::instance()->id, $roleid);

        $teacher = $datagen->create_user();
        $course = $datagen->create_course();
        $datagen->enrol_user($teacher->id, $course->id, 'editingteacher');

        return [$course, $teacher];
    }

    /**
     * Deleting an embedded file has to take its img element out of the module intro, otherwise the
     * content is left pointing at a file which no longer exists.
     *
     * @covers \tool_ally\webservice\delete_file::service
     */
    public function test_service_label_html(): void {
        global $DB;

        $this->resetAfterTest();

        [$course, $teacher] = $this->setup_course_and_teacher();

        $label = $this->getDataGenerator()->create_module('label', ['course' => $course->id]);
        $context = \context_module::instance($label->cmid);

        $file = $this->create_test_file($context->id, 'mod_label', 'intro');
        $this->create_test_file($context->id, 'mod_label', 'intro', 0, 'kept logo.png');

        $DB->update_record('label', (object) [
            'id'    => $label->id,
            'intro' => '<p>Test label text' .
                '<img src="@@PLUGINFILE@@/gd%20logo.png" alt="" width="100" height="100">' .
                '<img src="@@PLUGINFILE@@/kept%20logo.png" alt="" width="100" height="100"></p>',
        ]);

        $return = delete_file::service($file->get_pathnamehash(), $teacher->id);
        $return = \external_api::clean_returnvalue(delete_file::service_returns(), $return);

        $this->assertTrue($return['success']);

        $intro = $DB->get_field('label', 'intro', ['id' => $label->id]);
        $this->assertStringNotContainsString('gd%20logo.png', $intro);
        $this->assertStringContainsString('kept%20logo.png', $intro);
        $this->assertStringContainsString('Test label text', $intro);
    }

    /**
     * An image linking to its own file would otherwise be left behind as an empty anchor.
     *
     * @covers \tool_ally\webservice\delete_file::service
     */
    public function test_service_label_html_linked_image(): void {
        global $DB;

        $this->resetAfterTest();

        [$course, $teacher] = $this->setup_course_and_teacher();

        $label = $this->getDataGenerator()->create_module('label', ['course' => $course->id]);
        $context = \context_module::instance($label->cmid);

        $file = $this->create_test_file($context->id, 'mod_label', 'intro');

        $DB->update_record('label', (object) [
            'id'    => $label->id,
            'intro' => '<p><a href="@@PLUGINFILE@@/gd%20logo.png">' .
                '<img src="@@PLUGINFILE@@/gd%20logo.png" alt=""></a></p>',
        ]);

        $return = delete_file::service($file->get_pathnamehash(), $teacher->id);
        $return = \external_api::clean_returnvalue(delete_file::service_returns(), $return);

        $this->assertTrue($return['success']);

        $this->assertSame('<p></p>', $DB->get_field('label', 'intro', ['id' => $label->id]));
    }

    /**
     * Modules without Ally component support still hold embedded images in their intro.
     *
     * @covers \tool_ally\webservice\delete_file::service
     */
    public function test_service_unsupported_module_html(): void {
        global $DB;

        $this->resetAfterTest();

        [$course, $teacher] = $this->setup_course_and_teacher();

        $url = $this->getDataGenerator()->create_module('url', ['course' => $course->id]);
        $context = \context_module::instance($url->cmid);

        $file = $this->create_test_file($context->id, 'mod_url', 'intro');

        $DB->set_field(
            'url',
            'intro',
            '<p>Url text<img src="@@PLUGINFILE@@/gd%20logo.png" alt=""></p>',
            ['id' => $url->id]
        );

        $return = delete_file::service($file->get_pathnamehash(), $teacher->id);
        $return = \external_api::clean_returnvalue(delete_file::service_returns(), $return);

        $this->assertTrue($return['success']);

        $this->assertSame('<p>Url text</p>', $DB->get_field('url', 'intro', ['id' => $url->id]));
    }

    /**
     * Test removal of an embedded file from a course section summary.
     *
     * @covers \tool_ally\webservice\delete_file::service
     */
    public function test_service_course_section_html(): void {
        global $DB;

        $this->resetAfterTest();

        [$course, $teacher] = $this->setup_course_and_teacher();

        $context = \context_course::instance($course->id);
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1], '*', MUST_EXIST);

        $file = $this->create_test_file($context->id, 'course', 'section', $section->id);

        $DB->set_field(
            'course_sections',
            'summary',
            '<p>Section text<img src="@@PLUGINFILE@@/gd%20logo.png" alt=""></p>',
            ['id' => $section->id]
        );

        $return = delete_file::service($file->get_pathnamehash(), $teacher->id);
        $return = \external_api::clean_returnvalue(delete_file::service_returns(), $return);

        $this->assertTrue($return['success']);

        $summary = $DB->get_field('course_sections', 'summary', ['id' => $section->id]);
        $this->assertSame('<p>Section text</p>', $summary);
    }
}
