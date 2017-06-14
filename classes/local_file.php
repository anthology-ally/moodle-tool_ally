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
 * File library.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally;

defined('MOODLE_INTERNAL') || die();

/**
 * File library.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_file {
    /**
     * Factory method for file iterator.
     *
     * @param array|null $userids
     * @param array|null $roleids
     * @return files_iterator
     */
    public static function iterator(array $userids = null, array $roleids = null) {
        if ($userids === null) {
            $userids = local::get_adminids();
        }
        if ($roleids === null) {
            $roleids = local::get_roleids();
        }

        return new files_iterator($userids, new role_assignments($roleids));
    }

    /**
     * Get a file's course context if it exists.
     *
     * @param \stored_file $file
     * @param int $strictness Throw an exception if set to MUST_EXIST
     * @return \context_course|null
     */
    public static function course_context(\stored_file $file, $strictness = MUST_EXIST) {
        $context       = \context::instance_by_id($file->get_contextid());
        $coursecontext = $context->get_course_context(false);
        if (!$coursecontext instanceof \context_course) {
            if ($strictness === MUST_EXIST) {
                throw new \moodle_exception('filecoursenotfound', 'tool_ally');
            }
            return null;
        }

        return $coursecontext;
    }

    /**
     * Get a file's course ID if it exists.
     *
     * @param \stored_file $file
     * @param int $strictness Throw an exception if set to MUST_EXIST
     * @return int|null
     */
    public static function courseid(\stored_file $file, $strictness = MUST_EXIST) {
        $context = self::course_context($file, $strictness);

        return ($context instanceof \context_course) ? $context->instanceid : null;
    }

    /**
     * Plugin file URL from stored file.
     *
     * @param \stored_file $file
     * @return \moodle_url
     */
    public static function url(\stored_file $file) {
        return \moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(),
            $file->get_itemid(), $file->get_filepath(), $file->get_filename());
    }

    /**
     * Webservice plugin file URL from stored file.
     *
     * @param \stored_file $file
     * @return \moodle_url
     */
    public static function webservice_url(\stored_file $file) {
        if ($file->get_component() === 'mod_forum' && $file->get_itemid() == 0) {
            return \moodle_url::make_webservice_pluginfile_url($file->get_contextid(), $file->get_component(),
                $file->get_filearea(), null, $file->get_filepath(), $file->get_filename());

        } else {
            return \moodle_url::make_webservice_pluginfile_url($file->get_contextid(), $file->get_component(),
                $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename());
        }
    }

    /**
     * A message to send to Ally about a file being updated, created, etc.
     *
     * Warning: be very careful about editing this message.  It's used
     * for webservices and for pushed updates.
     *
     * @param \stored_file|\stdClass $file
     * @return array
     */
    public static function to_crud($file) {

        if ($file instanceof \stored_file) {
            $newfile = ($file->get_timecreated() + 2 >= $file->get_timemodified());

            return [
                'entity_id'    => $file->get_pathnamehash(),
                'context_id'   => self::courseid($file),
                'event_name'   => $newfile ? 'file_created' : 'file_updated',
                'event_time'   => local::iso_8601($file->get_timemodified()),
                'mime_type'    => $file->get_mimetype(),
                'content_hash' => $file->get_contenthash(),
            ];
        }

        if ($file instanceof \stdClass) {
            return [
                'entity_id'    => $file->pathnamehash,
                'context_id'   => $file->courseid,
                'event_name'   => 'file_deleted',
                'event_time'   => local::iso_8601($file->timedeleted),
                'mime_type'    => $file->mimetype,
                'content_hash' => $file->contenthash,
            ];
        }

        throw new \coding_exception('Unexpected parameter type passed, not stored_file or stdClass');
    }
}
