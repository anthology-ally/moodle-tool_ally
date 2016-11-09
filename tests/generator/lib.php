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
 * Testing generator.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Testing generator.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_ally_generator extends component_generator_base {
    /**
     * Create a draft file for the current user.
     *
     * Note: The file's item ID is the draft ID.
     *
     * @param array $record Draft file record
     * @param string $content File contents
     * @return stored_file
     */
    public function create_draft_file(array $record = [], $content = 'Test file') {
        global $USER;

        if (empty($USER->username) || $USER->username === 'guest') {
            throw new coding_exception('Requires a current user');
        }

        $defaults = [
            'component' => 'user',
            'filearea'  => 'draft',
            'contextid' => context_user::instance($USER->id)->id,
            'itemid'    => file_get_unused_draft_itemid(),
            'filename'  => 'attachment.html',
            'filepath'  => '/'
        ];

        return get_file_storage()->create_file_from_string($record + $defaults, $content);
    }
}