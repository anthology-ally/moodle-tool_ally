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
     * Assert that two stored files are the same.
     *
     * @param stored_file $expected
     * @param stored_file $actual
     */
    public function assertStoredFileEquals(stored_file $expected, stored_file $actual) { // @codingStandardsIgnoreLine
        $this->assertEquals($expected->get_pathnamehash(), $actual->get_pathnamehash(), 'Stored files should be the same');
    }
}