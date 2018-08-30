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
 * Content processor for Ally.
 * @package   tool_ally
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_ally;

use tool_ally\models\component_content;

defined('MOODLE_INTERNAL') || die();

/**
 * Content processor for Ally.
 * Can be used to process individual or groups of content.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_processor {

    protected static $pushtrace = [];

    protected static $updates;

    /**
     * Get push trace for PHP unit testing.
     * @param null|string $eventname
     * @param string $regex
     * @return bool|mixed
     */
    public static function get_push_traces($eventname = null, $regex = null) {
        if ($eventname === null) {
            return self::$pushtrace;
        }
        if (!PHPUNIT_TEST) {
            throw new \coding_exception('This is only supposed to be used for PHP Unit testing!');
        }
        if (isset(self::$pushtrace[$eventname])) {
            if ($regex === null) {
                return self::$pushtrace[$eventname];
            } else {
                foreach (self::$pushtrace[$eventname] as &$pushtrace) {
                    $pushtrace = array_filter($pushtrace, function($row) use($regex) {
                        return preg_match($regex, $row['entity_id']) === 1;
                    });
                }
                return $pushtrace;
            }
        }
        return false;
    }

    public static function clear_push_traces() {
        self::$pushtrace = [];
    }

    /**
     * Push content update to Ally without batching, etc.
     * @param push_content_updates $updates
     * @param component_content[] | component_content $content
     * @param string $eventname
     * @return bool true on success
     */
    public static function push_update(push_content_updates $updates, $content, $eventname) {
        if (!is_array($content)) {
            $content = [$content];
        }

        $payload = [];
        foreach ($content as $item) {
            if (!$item instanceof component_content) {
                throw new \coding_exception('$content array should only contain instances of component_content');
            }
            if (strval($item->contentformat) !== FORMAT_HTML) {
                // Only HTML formatted content is supported.
                continue;
            }
            $payload[] = local_content::to_crud($item, $eventname);
        }

        if (empty($payload)) {
            return true;
        }

        if (PHPUNIT_TEST) {
            if (!isset(self::$pushtrace[$eventname])) {
                self::$pushtrace[$eventname] = [];
            }
            self::$pushtrace[$eventname][] = $payload;

            // If we aren't using a mock version of $updates service then return now.
            if ($updates instanceof \Prophecy\Prophecy\ProphecySubjectInterface) {
                $updates->send($payload);
            }
            return true; // Return true always for PHPUNIT_TEST.
        }
        return $updates->send($payload);
    }

    /**
     * Get ally config.
     * @param boolean $reset
     * @return null|push_config
     */
    public static function get_config($reset = false) {
        static $config = null;
        if ($config === null || $reset) {
            $config = new push_config();
        }
        return $config;
    }

    /**
     * @param component_content[]|component_content $content
     * @param string $eventname
     */
    private static function add_to_content_queue($content, $eventname) {
        global $DB;

        if (!array($content)) {
            $content = [$content];
        }
        $dataobjects = [];
        foreach ($content as $contentitem) {
            if (empty($contentitem->content)) {
                continue;
            }
            $dataobjects[] = (object) [
                'comprowid' => $contentitem->id,
                'component' => $contentitem->component,
                'comptable' => $contentitem->table,
                'compfield' => $contentitem->field,
                'courseid' => $contentitem->get_courseid(),
                'eventtime' => time(),
                'eventname' => $eventname,
                'content' => $contentitem->content
            ];
        }
        $DB->insert_records('tool_ally_content_queue', $dataobjects);
    }

    /**
     * Push update(s) for content.
     * @param component_content[]|component_content $content
     * @param string $eventname
     * @return bool
     */
    public static function push_content_update($content, $eventname) {
        $config = self::get_config();
        if (!$config->is_valid() || $config->is_cli_only()) {
            self::add_to_content_queue($content, $eventname);
            return false;
        }
        if (empty(self::$updates)) {
            self::$updates = new push_content_updates($config);
        }
        return self::push_update(self::$updates, $content, $eventname);
    }

}
