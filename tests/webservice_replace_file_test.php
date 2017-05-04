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
 * Test for file replace webservice.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2017 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_ally\webservice\replace_file;
use tool_ally\local;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__.'/abstract_testcase.php');
require_once($CFG->dirroot . '/files/externallib.php');

/**
 * Test for file replace webservice.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2017 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_ally_webservice_replace_file_testcase extends tool_ally_abstract_testcase {
    /**
     * Test the web service.
     *
     */
    public function test_service() {
        global $DB, $USER;

        $this->resetAfterTest();

        $datagen = $this->getDataGenerator();

        $roleid = $this->assignUserCapability('moodle/course:view', context_system::instance()->id);
        $this->assignUserCapability('moodle/course:viewhiddencourses', context_system::instance()->id, $roleid);
        $this->assignUserCapability('moodle/course:managefiles', context_system::instance()->id, $roleid);

        $teacher = $datagen->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);

        $course = $datagen->create_course();

        $datagen->enrol_user($teacher->id, $course->id, $teacherrole->id);

        $resource = $datagen->create_module('resource', ['course' => $course->id]);
        $file = $this->get_resource_file($resource);

        $usercontext = context_user::instance($USER->id);
        $filename = "reddot.png";
        $filecontent = "iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHElEQVQI12P4//8/w38"
            . "GIAXDIBKE0DHxgljNBAAO9TXL0Y4OHwAAAABJRU5ErkJggg==";
        $draftfile = core_files_external::upload($usercontext->id, 'user', 'draft', 0, '/', $filename, $filecontent, null, null);

        $return = replace_file::service($file->get_pathnamehash(), $teacher->id, $draftfile['itemid']);
        $return = external_api::clean_returnvalue(replace_file::service_returns(), $return);

        $this->assertSame($return['success'], true);
        $this->assertNotSame($return['newid'], $file->get_itemid());

        $file = $this->get_resource_file($resource);
        $this->assertSame($file->get_filename(), $filename);
        $this->assertSame($file->get_content(), base64_decode($filecontent));
        // This should test that the userid of the file creator gets copied,
        // but the mod resource generator always sets the userid to null,
        // Can still test it copies the null value correctly though.
        $this->assertSame($file->get_userid(), null);
    }

    public function test_service_invalid_user() {
        $this->resetAfterTest();

        $roleid = $this->assignUserCapability('moodle/course:view', context_system::instance()->id);
        $this->assignUserCapability('moodle/course:viewhiddencourses', context_system::instance()->id, $roleid);
        $this->assignUserCapability('moodle/course:managefiles', context_system::instance()->id, $roleid);

        $otheruser = $this->getDataGenerator()->create_user();

        $course      = $this->getDataGenerator()->create_course();
        $resource    = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
        $file        = $this->get_resource_file($resource);

        // Can use fake as user check will fail before it is used.
        $fakeitemid = '123';

        $this->expectException(\moodle_exception::class);
        $return = replace_file::service($file->get_pathnamehash(), $otheruser->id, $fakeitemid);
        $return = external_api::clean_returnvalue(replace_file::service_returns(), $return);

        // Check file has not been changed.
        $newfile = $this->get_resource_file($resource);
        $this->assertInstanceOf(\stored_file, $newfile);
        $this->assertSame($file->get_filename(), $newfile->get_filename());
        $this->assertSame($file->get_content(), $newfile->get_content());
    }

    public function test_service_invalid_file() {
        global $DB;

        $this->resetAfterTest();

        $datagen = $this->getDataGenerator();

        $roleid = $this->assignUserCapability('moodle/course:view', context_system::instance()->id);
        $this->assignUserCapability('moodle/course:viewhiddencourses', context_system::instance()->id, $roleid);
        $this->assignUserCapability('moodle/course:managefiles', context_system::instance()->id, $roleid);

        $teacher = $datagen->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);

        $course      = $datagen->create_course();

        $datagen->enrol_user($teacher->id, $course->id, $teacherrole->id);

        // Can use fake as file check will fail before it is used.
        $fakeitemid = '123';

        $nonexistantfile = 'BADC0FFEE';
        $this->expectException(\moodle_exception::class);
        replace_file::service($nonexistantfile, $teacher->id, $fakeitemid);
    }
}
