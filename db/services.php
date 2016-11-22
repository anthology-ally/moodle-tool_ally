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
 * Web service definitions.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'tool_ally_get_files' => [
        'classname'    => 'tool_ally\\webservice\\files',
        'methodname'   => 'service',
        'description'  => 'Get files to process for accessibility',
        'type'         => 'read',
        'capabilities' => 'moodle/course:view, moodle/course:viewhiddencourses',
    ],
    'tool_ally_get_course_files' => [
        'classname'    => 'tool_ally\\webservice\\course_files',
        'methodname'   => 'service',
        'description'  => 'Get course files to process for accessibility',
        'type'         => 'read',
        'capabilities' => 'moodle/course:view, moodle/course:viewhiddencourses',
    ],
    'tool_ally_get_file' => [
        'classname'    => 'tool_ally\\webservice\\file',
        'methodname'   => 'service',
        'description'  => 'Get file information',
        'type'         => 'read',
        'capabilities' => 'moodle/course:view, moodle/course:viewhiddencourses',
    ],
    'tool_ally_get_file_updates' => [
        'classname'    => 'tool_ally\\webservice\\file_updates',
        'methodname'   => 'service',
        'description'  => 'Get file update information',
        'type'         => 'read',
        'capabilities' => 'moodle/course:view, moodle/course:viewhiddencourses',
    ],
];

$services = [
    'Ally integration services' => [
        'functions'       => [
            'core_course_get_courses',
            'core_enrol_get_enrolled_users',
            'tool_ally_get_files',
            'tool_ally_get_course_files',
            'tool_ally_get_file',
            'tool_ally_get_file_updates',
        ],
        'enabled'         => 0,
        'restrictedusers' => 0,
        'shortname'       => 'tool_ally',
        'downloadfiles'   => 1,
        'uploadfiles'     => 1
    ]
];
