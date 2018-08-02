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
 * Logging for file updates push.
 *
 * @package   tool_ally
 * @author    David Castro <david.castro@blackboard.com>
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally\event;

use core\event\base;

defined('MOODLE_INTERNAL') || die();

/**
 * Class push_file_updates_summary.
 * Logging for file updates push.
 *
 * @class      push_file_updates_summary
 * @package   tool_ally
 * @author    David Castro <david.castro@blackboard.com>
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class push_file_updates_summary extends base {

    /**
     * @var string PLUGIN
     */
    const PLUGIN = 'tool_ally';

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
        return get_string('pushfilessummary', self::PLUGIN);
    }

    /**
     * @return string
     */
    public function get_description() {
        return ($this->other['delete'] ? 'Deleted' : 'Pushed' ) .
            ' files (' . implode(',', $this->other['keys']) . ')';
    }

    /**
     * @return string
     */
    public static function get_explanation() {
        return get_string('pushfilessummary:explanation', self::PLUGIN);
    }

    /**
     * Validate event data.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        if (empty($this->other['keys'])) {
            throw new \coding_exception('File keys must be set');
        }
    }

    /**
     * @param array $fileupdates File updates already processed by local_file::to_crud
     * @param boolean $delete Deleted files?
     * @return \core\event\base
     * @throws \coding_exception
     */
    public static function create_from_payload(array $fileupdates, $delete = false) {
        $filekeys = array_map(function ($fileupdate) {
            return $fileupdate['entity_id'];
        }, $fileupdates);

        return self::create([
            'other' => [
                'keys' => $filekeys,
                'delete' => $delete
            ]
        ]);
    }
}