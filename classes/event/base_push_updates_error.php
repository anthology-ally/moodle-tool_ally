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
 * Error event logging for updates push.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally\event;
use core\event\base;
defined('MOODLE_INTERNAL') || die();

/**
 * Class base_push_file_updates_error.
 *
 * @package   tool_ally
 * @author    David Castro <david.castro@blackboard.com>
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class base_push_updates_error extends base {

    /**
     * @var string PLUGIN
     */
    const PLUGIN = 'tool_ally';

    /**
     * @var string ERRORKEY
     */
    const ERRORKEY = '';

    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->context = \context_system::instance();
    }

    /**
     * Returns the event name.
     *
     * @return string
     */
    public static function get_name() {
        static $name = null;

        if (empty($name)) {
            $name = get_string(static::ERRORKEY, self::PLUGIN);
        }

        return $name;
    }

    /**
     * @return string
     */
    public function get_description() {
        return 'Unexpected error: '.$this->other['message'];
    }

    /**
     * @return string
     */
    public static function get_explanation() {
        static $explanation = null;

        if (empty($explanation)) {
            $explanation = get_string(static::ERRORKEY.':explanation', self::PLUGIN);
        }

        return $explanation;
    }

    /**
     * Validate event data.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        if (empty($this->other['message'])) {
            throw new \coding_exception('The error message must be set');
        }
        if (!array_key_exists('code', $this->other)) {
            throw new \coding_exception('The error code must be set');
        }
    }

    /**
     * @param \Exception $exception
     * @return \core\event\base
     * @throws \coding_exception
     */
    public static function create_from_exception(\Exception $exception) {
        return self::create([
            'other' => [
                'message' => $exception->getMessage(),
                'code'    => $exception->getCode(),
                'trace'   => $exception->getTraceAsString(),
            ]
        ]);
    }

    /**
     * @param string $msg
     * @return \core\event\base
     * @throws \coding_exception
     */
    public static function create_from_msg($msg) {
        return self::create([
            'other' => [
                'message' => $msg,
                'code'    => 0,
            ]
        ]);
    }
}