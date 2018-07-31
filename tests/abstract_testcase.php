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
 * Base test case.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/webservice/tests/helpers.php');

/**
 * Base test case.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class tool_ally_abstract_testcase extends externallib_advanced_testcase {
    /**
     * Given a resource activity, return its associated file.
     *
     * @param stdClass $resource
     * @return stored_file
     * @throws coding_exception
     */
    protected function get_resource_file($resource) {
        $context = context_module::instance($resource->cmid);
        $files   = get_file_storage()->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false);

        if (count($files) < 1) {
            throw new coding_exception('Failed to find any files associated to resource activity');
        }

        return reset($files);
    }

    /**
     * Given an assign activity, return an associated file in a whitelisted filearea.
     *
     * @param stdClass $module
     * @return stored_file
     * @throws coding_exception
     */
    protected function create_whitelisted_assign_file($module) {
        return $this->create_assign_file($module, 'intro');

    }

    /**
     * Given an assign activity, return an associated file in not whitelisted filearea.
     *
     * @param stdClass $module
     * @return stored_file
     * @throws coding_exception
     */
    protected function create_notwhitelisted_assign_file($module) {
        return $this->create_assign_file($module, 'notwhitelisted');
    }

    /**
     * Creates a file for a given mod_assign and filearea.
     *
     * @param stdClass $module
     * @param string $filearea
     * @return stored_file
     * @throws coding_exception
     */
    private function create_assign_file($module, $filearea) {
        $context = context_module::instance($module->cmid);

        $fs = get_file_storage();

        // Prepare file record object.
        $fileinfo = array(
            'contextid' => $context->id,
            'component' => 'mod_assign',
            'filearea' => $filearea,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'myfile.txt');

        // Create file containing text 'hello world'.
        return $fs->create_file_from_string($fileinfo, 'hello world');

    }

    /**
     * Assert that two stored files are the same.
     *
     * @param stored_file $expected
     * @param stored_file $actual
     */
    public function assertStoredFileEquals(stored_file $expected, stored_file $actual) { // @codingStandardsIgnoreLine
        $this->assertEquals($expected->get_pathnamehash(), $actual->get_pathnamehash(), 'Stored files should be the same');
    }
}