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
 * @package    tool_ally
 * @copyright  Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally\event;
use core\event\base;
defined('MOODLE_INTERNAL') || die();

// Note to code reviewer - In my opinion (guy), we shouldn't be using events to log errors in the standard log store.
// Event's are supposed to reflect actions taken by users - e.g. course created, assignment graded, etc.
// They are not supposed to be used for API logging.
// If you take a look at the collaborate module you'll notice that it uses a PSR logger for API call logging.
// I think this is the correct approach as it is separate and distinct from user events.
// However, since the log store was already being used to record API push failures for files, I thought I'd do the same
// for content updates. It's up to you if you want to use a PSR logger for API failures, in which case these events
// should be removed and the code refactored.

/**
 * Class base_push_file_updates_error.
 *
 * @package    tool_ally
 * @author     David Castro <david.castro@blackboard.com>
 * @copyright  Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        return get_string(self::ERRORKEY, self::PLUGIN);
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
        return get_string(self::ERRORKEY.':explanation', self::PLUGIN);
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