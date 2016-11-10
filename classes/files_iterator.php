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
class files_iterator implements \Iterator {
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
     * @var \moodle_recordset
     */
    private $rs;

    /**
     * @var \stored_file
     */
    private $current;

    /**
     * @var int|null
     */
    private $since;

    /**
     * @param array $userids
     * @param role_assignments|null $assignments
     * @param \file_storage|null $storage
     * @param int|null $since
     */
    public function __construct(array $userids = [], role_assignments $assignments = null,
                \file_storage $storage = null, $since = null) {

        $this->userids     = $userids;
        $this->assignments = $assignments ?: new role_assignments();
        $this->storage     = $storage ?: get_file_storage();
        $this->since       = $since;
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

    /**
     * @return \stored_file
     */
    public function current() {
        return $this->current;
    }

    public function next() {
        while ($this->rs instanceof \moodle_recordset && $this->rs->valid()) {
            $row = $this->rs->current();
            $this->rs->next();

            $context = $this->extract_context($row);

            $validuser = empty($row->userid) || array_key_exists($row->userid, $this->userids) ||
                $this->assignments->has($row->userid, $context);

            if (!$validuser) {
                continue;
            }

            $this->current = $this->storage->get_file_instance($row);
            return;
        }
        $this->current = null;
    }

    public function key() {
        if ($this->current instanceof \stored_file) {
            return (int) $this->current->get_id();
        }

        return null;
    }

    public function valid() {
        return $this->current instanceof \stored_file;
    }

    public function rewind() {
        global $DB;

        $contextsql = \context_helper::get_preload_record_columns_sql('c');
        $timemodified = '';
        $params = [CONTEXT_USER, CONTEXT_COURSECAT, CONTEXT_SYSTEM];

        if (!empty($this->since)) {
            $timemodified = 'AND f.timemodified > ?';
            array_unshift($params, $this->since);
        }
        $this->rs = $DB->get_recordset_sql("
            SELECT f.*, $contextsql
              FROM {files} f
              JOIN {context} c ON c.id = f.contextid
             WHERE f.filename != '.' $timemodified
               AND c.contextlevel NOT IN(?, ?, ?)
        ", $params);

        // Must populate current.
        $this->next();
    }
}
