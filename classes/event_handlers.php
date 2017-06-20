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
 * Event handlers for Ally.
 * @package   tool_ally
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_ally;

defined('MOODLE_INTERNAL') || die();

use core\event\course_module_created,
    core\event\course_module_updated,
    tool_ally\task\file_updates_task,
    tool_ally\push_config,
    tool_ally\push_file_updates,
    tool_ally\local_file,
    tool_ally\files_iterator;

/**
 * Event handlers for Ally.
 * @package   tool_ally
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event_handlers {

    /**
     * Push file updates to Ally without batching, etc.
     *
     * @param push_file_updates $updates
     * @param files_iterator $files
     * @throws \Exception
     */
    private static function push_updates(push_file_updates $updates, files_iterator $files) {
        $payload = [];
        try {
            foreach ($files as $file) {
                $payload[] = local_file::to_crud($file);
            }
            if (!empty($payload)) {
                $updates->send($payload);
            }
        } catch (\Exception $e) {
            // Don't throw any errors - if it fails then the scheduled task will take care of it.
            unset($payload);
        }
    }

    /**
     * Get ally config.
     * @return null|\tool_ally\push_config
     */
    private static function get_config() {
        static $config = null;
        if ($config === null) {
            $config = new push_config();
        }
        return $config;
    }

    /**
     * Deal with course module creation / updates.
     * @param \core\event\base $event
     * @throws \Exception
     */
    private static function course_module_createdorupdated(\core\event\base $event) {
        $config = self::get_config();
        if (!$config->is_valid()) {
            return;
        }
        $updates = new push_file_updates($config);
        // Time since is event time - 5 minutes padding (in case files took a long time to upload).
        $time = $event->timecreated - (MINSECS * 5);
        $files = local_file::iterator()->in_context($event->get_context()); // Just get files for this module.
        $files = $files->since($time)->sort_by('timemodified');
        self::push_updates($updates, $files);
    }

    /**
     * Handle course module created.
     * @param course_module_created $event
     */
    public static function course_module_created(course_module_created $event) {
        self::course_module_createdorupdated($event);
    }

    /**
     * Handle course module updated.
     * @param course_module_updated $event
     */
    public static function course_module_updated(course_module_updated $event) {
        self::course_module_createdorupdated($event);
    }
}
