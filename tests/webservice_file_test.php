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
 * Test for file webservice.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_ally\webservice\file;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/abstract_testcase.php');

/**
 * Test for file webservice.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_ally_webservice_file_testcase extends tool_ally_abstract_testcase {
    /**
     * Test the web service.
     */
    public function test_service() {
        global $CFG;

        $this->resetAfterTest();
        $roleid = $this->assignUserCapability('moodle/course:view', context_system::instance()->id);
        $this->assignUserCapability('moodle/course:viewhiddencourses', context_system::instance()->id, $roleid);

        $course       = $this->getDataGenerator()->create_course();
        $resource     = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
        $expectedfile = $this->get_resource_file($resource);

        $file = file::service($expectedfile->get_pathnamehash());
        $file = external_api::clean_returnvalue(file::service_returns(), $file);

        $timemodified = \DateTime::createFromFormat(\DateTime::ISO8601, $file['timemodified'],
            new \DateTimeZone('UTC'))->getTimestamp();

        $this->assertNotEmpty($file);
        $this->assertEquals($expectedfile->get_pathnamehash(), $file['id']);
        $this->assertEquals($course->id, $file['courseid']);
        $this->assertEquals($expectedfile->get_userid(), $file['userid']);
        $this->assertEquals($expectedfile->get_filename(), $file['name']);
        $this->assertEquals($expectedfile->get_mimetype(), $file['mimetype']);
        $this->assertEquals($expectedfile->get_contenthash(), $file['contenthash']);
        $this->assertEquals($expectedfile->get_timemodified(), $timemodified);
        $this->assertRegExp('/.*pluginfile\.php.*mod_resource.*/', $file['url']);
        $this->assertRegExp('/.*webservice\/pluginfile\.php.*mod_resource.*/', $file['downloadurl']);
        $this->assertEquals($CFG->wwwroot.'/mod/resource/view.php?id='.$resource->cmid, $file['location']);
    }
}