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
 * Determine if a user has a role assignment in a context or parent contexts.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally;

defined('MOODLE_INTERNAL') || die();

/**
 * Determine if a user has a role assignment in a context or parent contexts.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class role_assignments {
    /**
     * @var array
     */
    private $roleids;

    /**
     * @var array
     */
    private $data;
    /**
     * @var \moodle_database
     */
    private $db;

    public function __construct(array $roleids = [], \moodle_database $db = null) {
        global $DB;

        $this->roleids = $roleids;
        $this->db      = $db ?: $DB;
    }

    /**
     * Determine if a user has a role assignment in a context or parent contexts.
     *
     * @param int $userid
     * @param \context $context
     * @return bool
     */
    public function has($userid, \context $context) {
        if (empty($this->roleids)) {
            return false; // Nothing to do.
        }

        $this->load_role_assignments();
        foreach ($context->get_parent_context_ids(true) as $contextid) {
            if (array_key_exists($contextid, $this->data) && array_key_exists($userid, $this->data[$contextid])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return users with acceptable role assignments for a specific context.
     * @param \context $context
     * @return array
     */
    public function user_ids_for_context(\context $context) {
        $this->load_role_assignments();
        if (empty($this->data[$context->id])) {
            return [];
        }
        return $this->data[$context->id];
    }

    /**
     * Load all role assignments that we care about and store them into the class.
     */
    private function load_role_assignments() {
        if (empty($this->roleids)) {
            return; // Nothing to do.
        }
        if (is_array($this->data)) {
            return; // Already loaded.
        }

        $this->data = [];

        $rs = $this->db->get_recordset_list('role_assignments', 'roleid', $this->roleids, '', 'id, contextid, userid');
        foreach ($rs as $row) {
            if (!array_key_exists($row->contextid, $this->data)) {
                $this->data[$row->contextid] = [];
            }
            $this->data[$row->contextid][$row->userid] = true;
        }
        $rs->close();
    }
}
