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
 * Test files iterator.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_ally\files_iterator;
use tool_ally\local;
use tool_ally\role_assignments;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/abstract_testcase.php');

/**
 * Test files iterator.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_ally_files_iterator_testcase extends tool_ally_abstract_testcase {
    /**
     * Test get_files.
     */
    public function test_get_files() {
        global $DB;

        $this->resetAfterTest();

        $course    = $this->getDataGenerator()->create_course();
        $user      = $this->getDataGenerator()->create_user();
        $roleid    = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $managerid = $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);

        $this->getDataGenerator()->enrol_user($user->id, $course->id, $roleid);
        $this->setUser($user);

        $resource1 = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
        $resource2 = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
        $file1     = $this->get_resource_file($resource1);
        $file2     = $this->get_resource_file($resource2);
        $hashes    = [$file1->get_pathnamehash(), $file2->get_pathnamehash()];

        // Set owner to our user.
        $DB->set_field('files', 'userid', $user->id, ['id' => $file1->get_id()]);

        // Check that if a role or user did not make content, that we only get files with null user ID.
        $files = new files_iterator([get_admin()->id], new role_assignments([$managerid]));
        foreach ($files as $file) {
            $this->assertStoredFileEquals($file2, $file);
            $this->assertNull($file->get_userid());
        }

        // Ensure user role works.
        $files = new files_iterator([], new role_assignments([$roleid]));
        foreach ($files as $file) {
            $this->assertContains($file->get_pathnamehash(), $hashes);
        }

        // Ensure user ID works.
        $files = new files_iterator([$user->id]);
        foreach ($files as $file) {
            $this->assertContains($file->get_pathnamehash(), $hashes);
        }
    }

    /**
     * Test get_files when there are no files to fetch.
     */
    public function test_get_no_files() {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $files = new files_iterator(local::get_adminids(), new role_assignments(local::get_roleids()));
        $this->assertEmpty(iterator_to_array($files));
    }

    /**
     * Test get_files using the since parameter.
     */
    public function test_get_files_since() {
        global $DB;

        $this->resetAfterTest();

        $course    = $this->getDataGenerator()->create_course();
        $user      = $this->getDataGenerator()->create_user();
        $roleid    = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);

        $this->getDataGenerator()->enrol_user($user->id, $course->id, $roleid);
        $this->setUser($user);

        $resource1 = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
        $resource2 = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
        $resource3 = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
        $file1     = $this->get_resource_file($resource1);
        $file2     = $this->get_resource_file($resource2);
        $file3     = $this->get_resource_file($resource3);

        $datetime = new \DateTimeImmutable('October 21 2015', new \DateTimeZone('UTC'));
        $earlier  = $datetime->sub(new \DateInterval('P2D'));
        $later    = $datetime->add(new \DateInterval('P2D'));

        $file1->set_timemodified($earlier->getTimestamp());
        $file2->set_timemodified($datetime->getTimestamp());
        $file3->set_timemodified($later->getTimestamp());

        $files = new files_iterator([], null, null, $datetime->getTimestamp());
        foreach ($files as $file) {
            $this->assertStoredFileEquals($file3, $file);
        }
    }
}