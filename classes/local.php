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
 * Local library.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally;

defined('MOODLE_INTERNAL') || die();

/**
 * Local library.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local {
    /**
     * Get list of role IDs from admin settings.
     *
     * @return array
     */
    public static function get_roleids() {
        $roles = get_config('tool_ally', 'roles');
        if (empty($roles)) {
            return [];
        }
        $roleids = explode(',', $roles);

        return array_combine($roleids, $roleids);
    }

    /**
     * Get list of admin user IDs.
     *
     * @return array
     */
    public static function get_adminids() {
        $userids = array_keys(get_admins());

        return array_combine($userids, $userids);
    }

    /**
     * Load all course contexts into context cache.
     */
    public static function preload_course_contexts() {
        global $DB;

        $fields = \context_helper::get_preload_record_columns_sql('c');
        $rs     = $DB->get_recordset_sql("SELECT $fields FROM {context} c WHERE c.contextlevel = ?", [CONTEXT_COURSE]);
        foreach ($rs as $context) {
            \context_helper::preload_from_record($context);
        }
        $rs->close();
    }

    /**
     * Format timestamp using ISO-8601 standard.
     *
     * @param int $timestamp
     * @return string
     */
    public static function iso_8601($timestamp) {
        $date = new \DateTime('', new \DateTimeZone('UTC'));
        $date->setTimestamp($timestamp);

        return $date->format('c');
    }

    /**
     * Plugin file URL from stored file.
     *
     * @param \stored_file $file
     * @return \moodle_url
     */
    public static function file_url(\stored_file $file) {
        return \moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(),
            $file->get_itemid(), $file->get_filepath(), $file->get_filename());
    }

    /**
     * Webservice plugin file URL from stored file.
     *
     * @param \stored_file $file
     * @return \moodle_url
     */
    public static function webservice_file_url(\stored_file $file) {
        return \moodle_url::make_webservice_pluginfile_url($file->get_contextid(), $file->get_component(),
            $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename());
    }
}