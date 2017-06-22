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
        if ($file->get_component() === 'question') {
            return self::generate_question_preview_url($file);
        }

        $itemid = self::preprocess_stored_file_itemid($file);
        return \moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(),
            $itemid, $file->get_filepath(), $file->get_filename());
    }

    /**
     * Webservice plugin file URL from stored file.
     *
     * @param \stored_file $file
     * @return \moodle_url
     */
    public static function webservice_url(\stored_file $file) {
        if ($file->get_component() === 'question') {
            return self::generate_question_preview_url($file, true);
        }

        $itemid = self::preprocess_stored_file_itemid($file);
        return \moodle_url::make_webservice_pluginfile_url($file->get_contextid(), $file->get_component(),
            $file->get_filearea(), $itemid, $file->get_filepath(), $file->get_filename());
    }

    /**
     * Pre process stored file for getting a plugin or webservice url.
     * This fixes an issue with some modules that have a root page, so they use an item id = 0 when there should be no id.
     * @param \stored_file $file
     * @return mixed null if fixing, item's id otherwise
     */
    private static function preprocess_stored_file_itemid(\stored_file $file) {
        $itemid = $file->get_itemid();

        // Some plugins do not like an itemid of 0 in the web service download path.
        $compareas = [
            'block_html~content',
            'course~legacy',
            'course~summary'
        ];
        if ($file->get_filearea() === 'intro' && $itemid == 0) {
            $itemid = null;
        } else if (in_array($file->get_component().'~'.$file->get_filearea(), $compareas) && $itemid == 0) {
            $itemid = null;
        }
        return $itemid;
    }

    /**
     * Generates a question preview URL for downloading a question's content file.
     *
     * @param \stored_file $file
     * @param bool $forwebservice Is it for a webservice URL?
     * @return \moodle_url
     */
    private static function generate_question_preview_url(\stored_file $file, $forwebservice = false) {
        global $CFG;
        $urlbase = $CFG->httpswwwroot;
        if ($forwebservice) {
            $urlbase .= '/webservice';
        }
        $urlbase .= '/pluginfile.php';

        require_once($CFG->libdir . '/questionlib.php');

        $quba = \question_engine::make_questions_usage_by_activity('core_question_preview', \context_system::instance());
        $quba->set_preferred_behaviour('deferredfeedback');
        $question = \question_bank::load_question($file->get_itemid());
        $slot = $quba->add_question($question);
        $quba->start_question($slot);
        \question_engine::save_questions_usage_by_activity($quba);

        return \moodle_url::make_file_url($urlbase, '/'.$file->get_contextid().'/question/'.$file->get_filearea().'/'
                .$quba->get_id().'/'.$slot.'/'.$file->get_itemid().$file->get_filepath().$file->get_filename());
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
