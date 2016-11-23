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
 * File library.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally;

defined('MOODLE_INTERNAL') || die();

/**
 * File library.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_file {
    /**
     * Get a file's course context if it exists.
     *
     * @param \stored_file $file
     * @param int $strictness Throw an exception if set to MUST_EXIST
     * @return \context_course|null
     */
    public static function course_context(\stored_file $file, $strictness = MUST_EXIST) {
        $context       = \context::instance_by_id($file->get_contextid());
        $coursecontext = $context->get_course_context(false);
        if (!$coursecontext instanceof \context_course) {
            if ($strictness === MUST_EXIST) {
                throw new \moodle_exception('filecoursenotfound', 'tool_ally');
            }
            return null;
        }

        return $coursecontext;
    }

    /**
     * Get a file's course ID if it exists.
     *
     * @param \stored_file $file
     * @param int $strictness Throw an exception if set to MUST_EXIST
     * @return int
     */
    public static function courseid(\stored_file $file, $strictness = MUST_EXIST) {
        return self::course_context($file, $strictness)->instanceid;
    }

    /**
     * Plugin file URL from stored file.
     *
     * @param \stored_file $file
     * @return \moodle_url
     */
    public static function url(\stored_file $file) {
        return \moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(),
            $file->get_itemid(), $file->get_filepath(), $file->get_filename());
    }

    /**
     * Webservice plugin file URL from stored file.
     *
     * @param \stored_file $file
     * @return \moodle_url
     */
    public static function webservice_url(\stored_file $file) {
        return \moodle_url::make_webservice_pluginfile_url($file->get_contextid(), $file->get_component(),
            $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename());
    }
}