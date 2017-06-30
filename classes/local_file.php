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

use tool_ally\modulesupport\html_base;

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
     * Resolve course module from file
     *
     * @param \stored_file $file
     * @return \cm_info | false
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public static function resolve_cm_from_file(\stored_file $file) {
        $context = \context::instance_by_id($file->get_contextid());
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return false;
        }
        $coursecontext = $context->get_course_context();
        $modinfo = get_fast_modinfo($coursecontext->instanceid);
        $cmid = $context->instanceid;
        $cm = $modinfo->get_cm($cmid);
        return $cm;
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

    /**
     * Replace contents of field with new file
     * @param string $field
     * @param string $table
     * @param string $filter
     * @param array $fparams filter parameters.
     * @param string $oldfname
     * @param string $newfname
     */
    public static function update_filenames_in_html($field, $table, $filter, array $fparams, $oldfname, $newfname) {
        global $DB;

        if (!$DB->replace_all_text_supported()) {
            return;
        }

        $search = '@@PLUGINFILE@@/'.rawurlencode($oldfname);
        $replace = '@@PLUGINFILE@@/'.rawurlencode($newfname);

        $params = [$search, $replace];

        $fieldsql = "\n $field = REPLACE($field, ?, ?)";
        $fieldwhere = " $field IS NOT NULL";

        $sql = "UPDATE {".$table."}
                   SET $fieldsql
                 WHERE $fieldwhere AND $filter";

        $params = array_merge($params, $fparams);

        $DB->execute($sql, $params);
    }

    /**
     * @param string $oldfilename
     * @param \stored_file $file
     */
    public static function replace_course_html_link($oldfilename, \stored_file $file) {

        $coursecontext = self::course_context($file);

        if ($file->get_filearea() === 'section') {

            self::update_filenames_in_html(
                'summary',
                'course_sections',
                'course = ?',
                [$coursecontext->instanceid],
                $oldfilename,
                $file->get_filename()
            );

        } else if ($file->get_filearea() === 'summary') {

            self::update_filenames_in_html(
                'summary',
                'course',
                'id = ?',
                [$coursecontext->instanceid],
                $oldfilename,
                $file->get_filename()
            );
        }
    }

    /**
     * @param string $oldfilename
     * @param \stored_file $file
     */
    public static function replace_block_html_link($oldfilename, \stored_file $file) {
        global $DB;

        $search = '@@PLUGINFILE@@/'.$oldfilename;
        $replace = '@@PLUGINFILE@@/'.$file->get_filename();

        $contextid = $file->get_contextid();
        $blockcontext = \context::instance_by_id($contextid);
        $blockinst = $DB->get_record('block_instances', ['id' => $blockcontext->instanceid]);
        $configdata = unserialize(base64_decode($blockinst->configdata));
        $configdata->text = str_replace($search, $replace, $configdata->text);
        $blockinst->configdata = base64_encode(serialize($configdata));
        $DB->update_record('block_instances', $blockinst);
    }

    /**
     * Replace any references to file in module html fields.
     * @param string $oldfilename
     * @param \stored_file
     */
    public static function replace_html_links($oldfilename, \stored_file $file) {
        global $DB, $CFG;

        $component = $file->get_component();

        if ($component === 'course') {
            self::replace_course_html_link($oldfilename, $file);
            return;
        }

        if ($component === 'block_html') {
            self::replace_block_html_link($oldfilename, $file);
            return;
        }

        $cm = self::resolve_cm_from_file($file);
        if (!$cm) {
            // Not a module, not yet supported.
            return;
        }

        $component = $cm->modname;

        $tables = $DB->get_tables();
        if (!in_array($component, $tables)) {
            return;
        }

        // Process the main table for the plugin if the file filearea is intro or content.
        $stdfields = ['intro', 'content'];
        if (in_array($file->get_filearea(), $stdfields)) {
            $instancerow = $DB->get_record($component, ['id' => $cm->instance]);

            $fieldtoupdate = null;

            foreach ($stdfields as $fld) {
                if (isset($instancerow->$fld) && $file->get_filearea() === $fld) {
                    $fieldtoupdate = $fld;
                }
            }
            if (!empty($fieldtoupdate)) {
                // Update.
                $newfilename = $file->get_filename();
                self::update_filenames_in_html(
                    $fieldtoupdate,
                    $component,
                    'id = ?',
                    [$cm->instance],
                    $oldfilename,
                    $newfilename
                );
            }
        } else {
            // Process any other tables related to this module.
            $moduleclassname = $component . '_html';
            $moduleclassname = 'tool_ally\\modulesupport\\'.$moduleclassname;
            if (class_exists($moduleclassname)) {
                /** @var html_base $instance */
                $instance = new $moduleclassname($oldfilename, $file);
                $instance->replace_file_links();
            }
        }
    }

    /**
     * List copmonents which support html file replacements.
     * @return string[]
     */
    public static function list_html_file_supported_components() {
        global $CFG;
        $modulesupportpath = $CFG->dirroot . '/admin/tool/ally/classes/modulesupport';
        $dir = new \DirectoryIterator($modulesupportpath);

        $components = [
            'course',
            'block_html',
            'mod_assign',
            'mod_label',
            'mod_page',
            'mod_folder'
        ];

        foreach ($dir as $fileinfo) {
            if (!$fileinfo->isDot()) {

                $regex = '/(.*)(?:_html.php)$/';

                $matches = [];

                $ismodulesupportfile = preg_match($regex, $fileinfo->getBasename(), $matches);

                if ($ismodulesupportfile) {
                    $components[] = 'mod_'.$matches[1];
                }
            }
        }
        return $components;
    }
}
