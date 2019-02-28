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
 * Course processor for Ally.
 * @package   tool_ally
 * @copyright Copyright (c) 2019 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_ally;

defined('MOODLE_INTERNAL') || die();

use tool_ally\logging\logger;

/**
 * Course processor for Ally.
 * Can be used to process individual or groups of course events.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2019 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_processor extends traceable_processor {

    protected static $pushtrace = [];

    public static function build_payload($event, $eventname) {
        return [local_course::to_crud($event)];
    }

    /**
     * Adds event to course event queue.
     *
     * @param push_config $config
     * @param \stdClass $event
     * @throws \dml_exception
     */
    private static function add_event_to_queue(push_config $config, $event) {
        global $DB;
        logger::get()->info('logger:addingcourseevttoqueue', [
            'configvalid' => $config->is_valid(),
            'configclionly' => $config->is_cli_only(),
            'event' => $event
        ]);
        $DB->insert_record_raw('tool_ally_course_event', (object) [
            'name' => $event->name,
            'time' => $event->time,
            'courseid' => $event->courseid
        ]);
    }

    /**
     * Push course event.
     *
     * @param string $name
     * @param int $time
     * @param int $courseid
     * @return bool Successfully pushed event.
     * @throws \dml_exception
     */
    public static function push_course_event($name, $time, $courseid) {
        $config = self::get_config();
        $event = (object) [
            'name' => $name,
            'time' => $time,
            'courseid' => $courseid
        ];
        if (!$config->is_valid() || $config->is_cli_only()) {
            self::add_event_to_queue($config, $event);
            return false;
        }
        $updates = new push_course_updates($config);
        $success = self::push_update($updates, $event, $name);
        if (!$success) {
            self::add_event_to_queue($config, $event);
        }
        return $success;
    }
}
