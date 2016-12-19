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
 * Observer.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally;

defined('MOODLE_INTERNAL') || die();

/**
 * Observer.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Flag for enabling file deletion hook.
     *
     * @var null|bool
     */
    public static $deletionenabled = null;

    /**
     * Store a record of deleted files.
     *
     * @param \stored_file $file
     */
    public static function file_deleted(\stored_file $file) {
        global $DB;

        if (self::$deletionenabled === null) {
            if (PHPUNIT_TEST || defined('BEHAT_SITE_RUNNING') || defined('BEHAT_TEST')) {
                return;
            }
            self::$deletionenabled = (new push_config())->is_valid();
        }

        if (self::$deletionenabled) {
            $courseid = local_file::courseid($file, IGNORE_MISSING);
            if (empty($courseid)) {
                return; // Ally does not support files outside of a course.
            }

            $DB->insert_record_raw('tool_ally_deleted_files', [
                'courseid'     => $courseid,
                'pathnamehash' => $file->get_pathnamehash(),
                'contenthash'  => $file->get_contenthash(),
                'mimetype'     => $file->get_mimetype(),
                'timedeleted'  => time(),
            ], false);
        }
    }
}