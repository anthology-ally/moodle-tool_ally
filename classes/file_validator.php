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
 * Validates if the file should be pushed to Ally.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally;

defined('MOODLE_INTERNAL') || die();

/**
 * Validates if the file should be pushed to Ally.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_validator {

    /**
     * Ally whitelisted components.
     */
    const WHITELIST = [
        // Resources.
        'mod_book~chapter',
        'mod_book~intro',
        'mod_folder~content',
        'mod_folder~intro',
        'mod_label~intro',
        'mod_page~content',
        'mod_page~intro',
        'mod_resource~content',
        'mod_resource~intro',
        // Activities.
        'mod_assign~intro',
        'mod_assign~introattachment',
        'mod_forum~intro', // Note students can create files in discussion topics / replies. This is the best we can do.
        'mod_hsuforum~intro',
        'mod_glossary~intro', // We can't do glossary entries as students can add these.
        'mod_lesson~intro',
        'mod_lesson~page_contents',
        'mod_lesson~page_responses',
        'mod_lesson~page_answers',
        'mod_quiz~intro',
        // Whitelist other.
        'block_html~content',
        'course~overviewfiles',
        'course~section',
        'course~summary',
        'question~questiontext',
        'question~generalfeedback',
        'question~answer',
        'question~answerfeedback',
        'question~correctfeedback',
        'question~incorrectfeedback',
        'question~partiallycorrectfeedback',
        'qtype_ddmatch~subanswer',
        'qtype_ddmatch~subquestion'
    ];

    /**
     * @var array
     */
    private $userids;

    /**
     * @var role_assignments
     */
    private $assignments;

    /**
     * Creates a new file_validator.
     * @param array $userids
     * @param role_assignments|null $assignments
     */
    public function __construct(array $userids = [], role_assignments $assignments = null) {
        $this->userids        = $userids;
        $this->assignments    = $assignments ?: new role_assignments();;
    }

    /**
     * Validates if the file should be pushed to Ally.
     * @param \stored_file $file
     * @param \context|null $context
     * @return bool
     * @throws \coding_exception
     */
    public function validate_stored_file(\stored_file $file, \context $context = null) {
        $context = $context ?: \context::instance_by_id($file->get_contextid());

        // Is it whitelisted?
        $whitelisted = $this->check_component_area_teacher_whitelist($file->get_component(), $file->get_filearea());

        if (!$whitelisted) {
            // Component + area are not whitelisted so check if user is an editing teacher / manager / admin / etc.
            $userid = $file->get_userid();
            $validuser = empty($userid) || array_key_exists($userid, $this->userids) ||
                $this->assignments->has($userid, $context);
            if (!$validuser) {
                return false;
            }
        }

        // Only files that belong to a course are supported by Ally.
        return $context->get_course_context(false) instanceof \context_course;
    }

    /**
     * Check component and area to see if it's whitelisted as a teacher authored file - return true if it is.
     * @param string $component
     * @param string $filearea
     * @return bool
     */
    private function check_component_area_teacher_whitelist($component, $filearea) {
        $key = $component.'~'.$filearea;
        return in_array($key, self::WHITELIST);
    }
}