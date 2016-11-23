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
 * File updates task.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally\task;

use core\task\scheduled_task;
use tool_ally\local_file;
use tool_ally\push_config;
use tool_ally\push_file_updates;

defined('MOODLE_INTERNAL') || die();

/**
 * File updates task.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_updates_task extends scheduled_task {
    /**
     * @var push_config
     */
    public $config;

    /**
     * @var push_file_updates
     */
    public $updates;

    public function get_name() {
        return get_string('fileupdatestask', 'tool_ally');
    }

    public function execute() {
        $config = $this->config ?: new push_config();
        if (!$config->is_valid()) {
            return;
        }
        $time = clean_param(get_config('tool_ally', 'push_timestamp'), PARAM_INT);

        if (empty($time)) {
            // First time running or reset.  Since this pushes file updates and this is first time, then we have no update
            // window. So, set time and wait till next task execution.
            $this->set_push_timestamp(time());
            return;
        }

        $updates    = $this->updates ?: new push_file_updates($config);
        $files      = local_file::iterator()->since($time)->sort_by('timemodified');
        $payload    = [];
        $timetosave = 0;

        try {
            $files->rewind();
            while ($files->valid()) {
                $file = $files->current();
                $files->next();

                $payload[] = local_file::to_crud($file);

                // Check to see if we have our batch size or if we are at the last file.
                if (count($payload) >= $config->get_batch_size() || !$files->valid()) {
                    $updates->send($payload);

                    // Reset payload and track last successful and latest time modified.
                    $payload    = [];
                    $timetosave = $file->get_timemodified();
                }
            }
        } catch (\Exception $e) {
            // Save current progress so we don't resend files that were successfully sent.
            $this->set_push_timestamp($timetosave);
            throw $e;
        }

        // Everything went according to plan, update our timestamp.
        $this->set_push_timestamp($timetosave);
    }

    /**
     * Save push timestamp.  This is our file last modified window.
     *
     * @param $timestamp
     */
    private function set_push_timestamp($timestamp) {
        if (!empty($timestamp)) {
            set_config('push_timestamp', $timestamp, 'tool_ally');
        }
    }
}