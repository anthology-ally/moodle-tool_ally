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
 * Files that are processed for accessibility.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally;

defined('MOODLE_INTERNAL') || die();

/**
 * Files that are processed for accessibility.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class files_iterator implements \IteratorAggregate {
    /**
     * @var array
     */
    private $userids;

    /**
     * @var \file_storage
     */
    private $storage;

    /**
     * @var role_assignments
     */
    private $assignments;

    /**
     * @param array $userids
     * @param role_assignments|null $assignments
     * @param \file_storage|null $storage
     */
    public function __construct(array $userids = [], role_assignments $assignments = null, \file_storage $storage = null) {
        $this->userids     = $userids;
        $this->assignments = $assignments ?: new role_assignments();
        $this->storage     = $storage ?: get_file_storage();
    }

    /**
     * Get files that should be processed for accessibility.
     *
     * @return \Generator|\stored_file[]
     */
    public function getIterator() { // @codingStandardsIgnoreLine
        global $DB;

        $contextsql = \context_helper::get_preload_record_columns_sql('c');

        $rs = $DB->get_recordset_sql("
            SELECT f.*, $contextsql
              FROM {files} f
              JOIN {context} c ON c.id = f.contextid
             WHERE f.filename != '.'
               AND c.contextlevel NOT IN(?, ?, ?)
        ", [CONTEXT_USER, CONTEXT_COURSECAT, CONTEXT_SYSTEM]);

        foreach ($rs as $row) {
            $context = $this->extract_context($row);

            $validuser = empty($row->userid) || array_key_exists($row->userid, $this->userids) ||
                $this->assignments->has($row->userid, $context);

            if (!$validuser) {
                continue;
            }

            yield $this->storage->get_file_instance($row);
        }
        $rs->close();
    }

    /**
     * @param \stdClass $row
     * @return \context
     */
    private function extract_context($row) {
        // This loads the context into cache and strips the context fields from the row.
        \context_helper::preload_from_record($row);

        return \context::instance_by_id($row->contextid);
    }
}