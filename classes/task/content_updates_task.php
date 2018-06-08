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
use tool_ally\content_processor;
use tool_ally\local_content;
use tool_ally\models\component_content;
use tool_ally\push_config;
use tool_ally\event_handlers;
use tool_ally\push_content_updates;

defined('MOODLE_INTERNAL') || die();

/**
 * Content updates task.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_updates_task extends scheduled_task {
    /**
     * @var push_config
     */
    public $config;

    /**
     * @var bool
     */
    private $clionly;

    /**
     * @var push_content_updates
     */
    public $updates;

    public function get_name() {
        return get_string('contentupdatestask', 'tool_ally');
    }

    public function execute() {
        $config = $this->config ?: new push_config();
        if (!$config->is_valid()) {
            return;
        }
        $time = clean_param(get_config('tool_ally', 'push_content_timestamp'), PARAM_INT);

        if (empty($time)) {
            // First time running or reset.  Since this pushes content updates and this is first time, then we have no update
            // window. So, set time and wait till next task execution.
            $this->set_push_content_timestamp(time());
            return;
        }

        $this->clionly = $config->is_cli_only();

        $this->updates = $this->updates ?: new push_content_updates($config);

        // Push deleted files.
        $this->push_deletes($config);

        // Push content updates.
        $this->push_content_updates($config);

        $this->set_push_content_timestamp(time());
    }

    /**
     * Push content deletions to Ally.
     *
     * @param push_config $config
     * @param push_content_updates $updates
     */
    private function push_deletes(push_config $config) {
        global $DB;

        $ids     = [];
        $payload = [];
        $deletes = $DB->get_recordset('tool_ally_deleted_content', null, 'id');

        while ($deletes->valid()) {
            $todelete = $deletes->current();
            $deletes->next();

            $ids[]     = $todelete->id;

            // Note - always use FORMAT_HMTL for deletes. Once something is deleted we have no idea what it's format
            // is, so just go with FORMAT_HTML.
            $content = new component_content(
                    $todelete->instanceid, $todelete->component, $todelete->comptable, $todelete->field,
                    $todelete->courseid, $todelete->timedeleted, FORMAT_HTML, '');
            $payload[] = $content;

            // Check to see if we have our batch size or if we are at the last file.
            if (count($payload) >= $config->get_batch_size() || !$deletes->valid()) {
                content_processor::push_update($this->updates, $payload, event_handlers::API_DELETED);

                if ($this->clionly) {
                    // Successful send, enable live push updates.
                    set_config('push_cli_only', 0, 'tool_ally');
                    $this->clionly = false;
                }

                // Successfully sent, remove.
                $DB->delete_records_list('tool_ally_deleted_content', 'id', $ids);

                // Reset arrays for next payload.
                $ids     = [];
                $payload = [];
            }
        }
        $deletes->close();
    }

    /**
     * Push content updates.
     *
     * @param push_config $config
     */
    private function push_content_updates(push_config $config) {
        global $DB;

        $ids     = [];
        $payload = [];
        $queue = $DB->get_recordset('tool_ally_content_queue', null, 'id');

        while ($queue->valid()) {
            $queuerow = $queue->current();
            $queue->next();

            $ids[]     = $queuerow->id;

            $content = local_content::get_html_content(
                $queuerow->componentid, $queuerow->component, $queuerow->comptable, $queuerow->compfield,
                $queuerow->courseid);

            $payload[] = $content;

            // Check to see if we have our batch size or if we are at the last file.
            if (count($payload) >= $config->get_batch_size() || !$queue->valid()) {
                content_processor::push_update($this->updates, $payload, $queuerow->eventname);

                if ($this->clionly) {
                    // Successful send, enable live push updates.
                    set_config('push_cli_only', 0, 'tool_ally');
                    $this->clionly = false;
                }

                // Successfully sent, remove.
                $DB->delete_records_list('tool_ally_content_queue', 'id', $ids);

                // Reset arrays for next payload.
                $ids     = [];
                $payload = [];
            }
        }
        $queue->close();
    }

    /**
     * Save push timestamp.  This is our file last modified window.
     *
     * @param int $timestamp
     */
    private function set_push_content_timestamp($timestamp) {
        if (!empty($timestamp)) {
            set_config('push_content_timestamp', $timestamp, 'tool_ally');
        }
    }
}
