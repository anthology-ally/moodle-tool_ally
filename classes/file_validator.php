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
        'block_html~content',
        'calendar~event_description',
        'course~overviewfiles',
        'course~section',
        'course~summary',
        'group~description',
        'mod_assign~intro',
        'mod_assign~introattachment',
        'mod_book~chapter',
        'mod_book~intro',
        'mod_chat~intro',
        'mod_choice~intro',
        'mod_data~content',
        'mod_feedback~intro',
        'mod_folder~content',
        'mod_folder~intro',
        'mod_forum~attachment',
        'mod_forum~intro',
        'mod_forum~post',
        'mod_glossary~attachment',
        'mod_glossary~entry',
        'mod_glossary~intro',
        'mod_hsuforum~attachment',
        'mod_hsuforum~comments',
        'mod_hsuforum~intro',
        'mod_hsuforum~post',
        'mod_imscp~content',
        'mod_kalvidres~intro',
        'mod_label~intro',
        'mod_lesson~intro',
        'mod_lesson~mediafile',
        'mod_lesson~page_answers',
        'mod_lesson~page_contents',
        'mod_lesson~page_responses',
        'mod_lightboxgallery~gallery_images',
        'mod_page~content',
        'mod_page~intro',
        'mod_questionnaire~info',
        'mod_questionnaire~intro',
        'mod_questionnaire~question',
        'mod_quiz~intro',
        'mod_resource~intro',
        'mod_resource~content',
        'mod_scorm~content',
        'mod_scorm~intro',
        'mod_turnitintooltwo~intro',
        'mod_url~intro'
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

        // Check if user is an editing teacher / manager / admin / etc.
        $userid = $file->get_userid();
        $validuser = empty($userid) || array_key_exists($userid, $this->userids) ||
            $this->assignments->has($userid, $context);

        return $whitelisted && $validuser && $context->get_course_context(false) instanceof \context_course;
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