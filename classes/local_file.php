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
 * @copyright Copyright (c) 2016 Open LMS (https://www.openlms.net) / 2023 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally;

use core_course\cm_info;
use core\exception\moodle_exception;
use coding_exception;
use context;
use context_course;
use moodle_url;
use stored_file;
use tool_ally\componentsupport\component_base;
use tool_ally\componentsupport\file_component_base;
use tool_ally\models\pluginfileurlprops;

/**
 * File library.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Open LMS (https://www.openlms.net) / 2023 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_file {
    /**
     * Factory method for file iterator.
     *
     * @param file_validator|null $validator
     * @return files_iterator
     */
    public static function iterator(file_validator $validator = null) {
        $validator = $validator ?: self::file_validator();

        return new files_iterator($validator);
    }

    /**
     * Factory method for file iterator.
     *
     * @return file_validator
     */
    public static function file_validator() {
        static $validator;

        if ($validator === null || PHPUNIT_TEST) {
            $validator = new file_validator(local::get_adminids(), new role_assignments(local::get_roleids()));
        }

        return $validator;
    }

    /**
     * Get a file's course context if it exists.
     *
     * @param stored_file $file
     * @param int $strictness Throw an exception if set to MUST_EXIST
     * @return context_course|null
     */
    public static function course_context(stored_file $file, $strictness = MUST_EXIST) {
        $context       = context::instance_by_id($file->get_contextid());
        $coursecontext = $context->get_course_context(false);
        if (!$coursecontext instanceof context_course) {
            if ($strictness === MUST_EXIST) {
                throw new moodle_exception('filecoursenotfound', 'tool_ally');
            }
            return null;
        }

        return $coursecontext;
    }

    /**
     * Resolve course module from file
     *
     * @param stored_file $file
     * @return cm_info | false
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function resolve_cm_from_file(stored_file $file): cm_info|false {
        $context = context::instance_by_id($file->get_contextid());
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
     * @param stored_file $file
     * @param int $strictness Throw an exception if set to MUST_EXIST
     * @return int|null
     */
    public static function courseid(stored_file $file, $strictness = MUST_EXIST) {
        $context = self::course_context($file, $strictness);

        return ($context instanceof context_course) ? $context->instanceid : null;
    }

    /**
     * Plugin file URL from stored file.
     *
     * @param stored_file $file
     * @return moodle_url
     */
    public static function url(stored_file $file) {
        global $CFG;

        if ($file->get_component() === 'question') {
            return new moodle_url($CFG->wwwroot . '/admin/tool/ally/pluginfile.php', ['pathnamehash' => $file->get_pathnamehash()]);
        }

        $itemid = self::preprocess_stored_file_itemid($file);
        return moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $itemid,
            $file->get_filepath(),
            $file->get_filename()
        );
    }

    /**
     * Pre process stored file for getting a plugin or webservice url.
     * This fixes an issue with some modules that have a root page, so they use an item id = 0 when there should be no id.
     * @param stored_file $file
     * @return mixed null if fixing, item's id otherwise
     */
    private static function preprocess_stored_file_itemid(stored_file $file) {
        $itemid = $file->get_itemid();

        // Some plugins do not like an itemid of 0 in the web service download path.
        $compareas = [
            'block_html~content',
            'course~legacy',
            'course~summary',
        ];
        if ($file->get_filearea() === 'intro' && $itemid == 0) {
            $itemid = null;
        } else if (in_array($file->get_component() . '~' . $file->get_filearea(), $compareas) && $itemid == 0) {
            $itemid = null;
        }
        return $itemid;
    }

    /**
     * Generate webservice pluginfile signature.
     * @param string $pathnamehash
     * @param null|int $iat - if null will create new iat from current time.
     * @return stdClass
     * @throws \webservice_access_exception
     */
    public static function generate_wspluginfile_signature($pathnamehash, $iat = null) {
        $iat = $iat === null ? time() : $iat;
        $tokenobj = local::get_ws_token();
        if (!$tokenobj) {
            throw new coding_exception('Failed to get Ally web service token object');
        }
        $token = $tokenobj->token;
        return (object) [
            'pathnamehash' => $pathnamehash,
            'iat' => $iat,
            'signature' => hash('sha256', $token . ':' . $iat . ':' . $pathnamehash),
        ];
    }

    /**
     * Webservice plugin file URL from stored file.
     *
     * @param stored_file $file
     * @return moodle_url
     */
    public static function webservice_url(stored_file $file) {
        global $CFG;

        $signature = self::generate_wspluginfile_signature($file->get_pathnamehash());

        return new moodle_url(
            $CFG->wwwroot . '/admin/tool/ally/wspluginfile.php',
            [
                    'pathnamehash' => $signature->pathnamehash,
                    'signature' => $signature->signature,
                    'iat' => $signature->iat,
                ]
        );
    }

    /**
     * A message to send to Ally about a file being updated, created, etc.
     *
     * Warning: be very careful about editing this message.  It's used
     * for webservices and for pushed updates.
     *
     * @param stored_file|\stdClass $file
     * @return array
     */
    public static function to_crud($file) {

        if ($file instanceof stored_file) {
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

        throw new coding_exception('Unexpected parameter type passed, not stored_file or stdClass');
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

        $search = '@@PLUGINFILE@@/' . rawurlencode($oldfname);
        $replace = '@@PLUGINFILE@@/' . rawurlencode($newfname);

        $params = [$search, $replace];

        $fieldsql = "\n $field = REPLACE($field, ?, ?)";
        $fieldwhere = " $field IS NOT NULL";

        $sql = "UPDATE {" . $table . "}
                   SET $fieldsql
                 WHERE $fieldwhere AND $filter";

        $params = array_merge($params, $fparams);

        $DB->execute($sql, $params);
    }

    /**
     * Replace course html link
     *
     * @param string $oldfilename
     * @param stored_file $file
     */
    public static function replace_course_html_link($oldfilename, stored_file $file) {

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
     * Replace block html link.
     *
     * @param string $oldfilename
     * @param stored_file $file
     */
    public static function replace_block_html_link($oldfilename, stored_file $file) {
        global $DB;

        $search = '@@PLUGINFILE@@/' . $oldfilename;
        $replace = '@@PLUGINFILE@@/' . $file->get_filename();

        $contextid = $file->get_contextid();
        $blockcontext = \context::instance_by_id($contextid);
        $blockinst = $DB->get_record('block_instances', ['id' => $blockcontext->instanceid]);
        $configdata = unserialize(base64_decode($blockinst->configdata));
        $configdata->text = str_replace($search, $replace, $configdata->text);
        $blockinst->configdata = base64_encode(serialize($configdata));
        $DB->update_record('block_instances', $blockinst);
    }

    /**
     * Get plugin file url properties for a specific component.
     * @param string $component
     * @param string $pluginfileurl
     * @return pluginfileurlprops
     */
    public static function get_fileurlproperties($pluginfileurl) {

        // First, make sure this pluginfile.php is for the current site.
        // We're not interested in URLs pointing to other sites!
        $baseurl = new moodle_url('/pluginfile.php');
        $drafturl = new moodle_url('/draftfile.php');
        $fileurl = new moodle_url($pluginfileurl);
        if (!$fileurl->compare($baseurl, URL_MATCH_BASE) && !$fileurl->compare($drafturl, URL_MATCH_BASE)) {
            return;
        }

        $regex = '/(?:.*)(?:pluginfile\.php|draftfile\.php)(?:\?file=|)(?:\/|%2F)(\d*?)(?:\/|%2F)(.*)$/';
        $matches = [];
        $matched = preg_match($regex, $pluginfileurl, $matches);
        if (!$matched) {
            return;
        }
        $contextid = $matches[1];
        if (strpos($matches[2], '%2F') !== false) {
            $del = '%2F';
        } else {
            $del = '/';
        }
        $arr = explode($del, $matches[2]);
        $component = urldecode(array_shift($arr));

        // Do we have a specific function in the component for handling file url props?
        // If so then lets return that.
        $componentclassname = local::get_component_class($component);
        if (class_exists($componentclassname)) {
            if (method_exists($componentclassname, 'fileurlproperties')) {
                return $componentclassname::fileurlproperties($pluginfileurl);
            }
        }

        if ((strpos($component, 'mod_') === 0) && ($arr[0] === 'intro')) {
            // Mod intro files file a specific file format.
            $filearea = array_shift($arr);
            $itemid = 0;
            $filename = implode('/', $arr);
        } else if (count($arr) === 2) {
            $filearea = array_shift($arr);
            $itemid = 0;
            $filename = array_shift($arr);
        } else if (count($arr) === 3) {
            $filearea = array_shift($arr);
            $itemid = array_shift($arr);
            $filename = array_shift($arr);
        } else {
            $filearea = array_shift($arr);
            $itemid = array_shift($arr);
            $filename = implode('/', $arr);
        }

        return new pluginfileurlprops($contextid, $component, $filearea, $itemid, $filename);
    }

    /**
     * Get file from pluginfileurlprops
     * @param pluginfileurlprops $props
     * @return bool|stored_file
     */
    public static function get_file_fromprops(pluginfileurlprops $props) {
        $fs = new \file_storage();
        return $fs->get_file(
            $props->contextid,
            $props->component,
            $props->filearea,
            $props->itemid,
            $props->filepath,
            basename($props->filename)
        );
    }

    /**
     * Replace any references to file in component html fields.
     * @param string $oldfilename
     * @param stored_file
     */
    public static function replace_html_links($oldfilename, stored_file $file) {
        global $DB;

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
        if ($cm) {
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
                return;
            }
        }

        // Process any other tables related to this component.
        $instance = local::get_component_instance($component);
        if ($instance instanceof file_component_base) {
            $instance->setup_file_and_validate($oldfilename, $file);
            $instance->replace_file_links();
        }
    }

    /**
     * Get the @@PLUGINFILE@@ relative paths which may be used to reference a file in html.
     *
     * Moodle raw url encodes embedded file references, but content authored elsewhere (or imported) can
     * hold the unencoded path, so both variants have to be considered.
     *
     * @param stored_file $file
     * @return string[]
     */
    public static function pluginfile_path_variants(stored_file $file) {
        $path = $file->get_filepath() . $file->get_filename();

        $encoded = implode('/', array_map('rawurlencode', explode('/', $path)));

        return array_values(array_unique([$encoded, $path]));
    }

    /**
     * Strip img elements referencing a file out of html.
     *
     * Deleting the stored file on its own leaves the img element behind, which renders as a broken
     * image, so the element itself has to go too.
     *
     * @param string $html
     * @param string[] $paths @@PLUGINFILE@@ relative paths, see pluginfile_path_variants.
     * @return string
     */
    public static function strip_pluginfile_elements($html, array $paths) {
        if (empty($html) || empty($paths)) {
            return $html;
        }

        $quoted = array_map(function($path) {
            return preg_quote($path, '~');
        }, $paths);

        $target = '@@PLUGINFILE@@(?:' . implode('|', $quoted) . ')';

        // An image linking to its own file would be left behind as an empty, but still clickable,
        // anchor, so that anchor has to be removed as a whole.
        $anchor = '~<a\b[^>]*\bhref\s*=\s*([\'"])\s*' . $target . '\s*\1[^>]*>\s*' .
            '<img\b[^>]*\bsrc\s*=\s*([\'"])\s*' . $target . '\s*\2[^>]*>\s*</a>~is';

        $img = '~<img\b[^>]*\bsrc\s*=\s*([\'"])\s*' . $target . '\s*\1[^>]*>~is';

        return preg_replace($img, '', preg_replace($anchor, '', $html));
    }

    /**
     * Remove references to a file from a html field.
     *
     * Only for content which has no component support class - where there is one,
     * remove_filepaths_from_content is preferred as it also notifies Ally of the change.
     *
     * @param string $field
     * @param string $table
     * @param string $filter
     * @param array $fparams filter parameters.
     * @param string[] $paths @@PLUGINFILE@@ relative paths, see pluginfile_path_variants.
     */
    public static function remove_filepaths_from_html($field, $table, $filter, array $fparams, array $paths) {
        global $DB;

        $sql = "SELECT id, $field AS allyhtml
                  FROM {" . $table . "}
                 WHERE $field IS NOT NULL AND $filter";

        $rows = $DB->get_records_sql($sql, $fparams);
        foreach ($rows as $row) {
            $stripped = self::strip_pluginfile_elements($row->allyhtml, $paths);
            if ($stripped === $row->allyhtml) {
                continue;
            }
            $DB->set_field($table, $field, $stripped, ['id' => $row->id]);
        }
    }

    /**
     * Remove references to a file from a component html content item.
     *
     * Goes through the component support so that the content update is pushed to Ally and any
     * course cache holding the old content is cleared.
     *
     * @param int $id
     * @param string $component
     * @param string $table
     * @param string $field
     * @param string[] $paths @@PLUGINFILE@@ relative paths, see pluginfile_path_variants.
     * @return bool true when content was updated.
     */
    public static function remove_filepaths_from_content($id, $component, $table, $field, array $paths) {
        $content = local_content::get_html_content($id, $component, $table, $field);
        if (empty($content) || empty($content->content)) {
            return false;
        }

        $stripped = self::strip_pluginfile_elements($content->content, $paths);
        if ($stripped === $content->content) {
            return false;
        }

        return (bool) local_content::replace_html_content($id, $component, $table, $field, $stripped);
    }

    /**
     * Can the content for this component / table / field be read and written through component support?
     *
     * @param string $component
     * @param string $table
     * @param string $field
     * @return bool
     */
    private static function content_support_covers($component, $table, $field) {
        if (!local_content::component_supports_html_content($component)) {
            return false;
        }

        $instance = local::get_component_instance($component);

        return in_array($field, $instance->get_table_fields($table));
    }

    /**
     * Remove any references to a file from component html fields.
     *
     * Intended to be called when a file has been deleted, so that content stops pointing at a file
     * which no longer exists.
     *
     * @param stored_file $file
     */
    public static function remove_html_links(stored_file $file) {
        global $DB;

        $paths = self::pluginfile_path_variants($file);

        $component = $file->get_component();
        $filearea = $file->get_filearea();

        if ($component === 'course') {
            if ($filearea === 'section') {
                // Section files are itemised by section id, so only that section can reference them.
                self::remove_filepaths_from_content($file->get_itemid(), 'course', 'course_sections', 'summary', $paths);
            } else if ($filearea === 'summary') {
                $coursecontext = self::course_context($file);
                self::remove_filepaths_from_content($coursecontext->instanceid, 'course', 'course', 'summary', $paths);
            }
            return;
        }

        if ($component === 'block_html') {
            $blockcontext = context::instance_by_id($file->get_contextid());
            self::remove_filepaths_from_content(
                $blockcontext->instanceid,
                'block_html',
                'block_instances',
                'configdata',
                $paths
            );
            return;
        }

        $cm = self::resolve_cm_from_file($file);
        if ($cm) {
            $component = $cm->modname;

            $tables = $DB->get_tables();
            if (!in_array($component, $tables)) {
                return;
            }

            // Process the main table for the plugin if the file filearea is intro or content.
            $stdfields = ['intro', 'content'];
            if (in_array($filearea, $stdfields)) {
                $instancerow = $DB->get_record($component, ['id' => $cm->instance]);

                $fieldtoupdate = null;

                foreach ($stdfields as $fld) {
                    if (isset($instancerow->$fld) && $filearea === $fld) {
                        $fieldtoupdate = $fld;
                    }
                }
                if (!empty($fieldtoupdate)) {
                    if (self::content_support_covers($component, $component, $fieldtoupdate)) {
                        self::remove_filepaths_from_content($cm->instance, $component, $component, $fieldtoupdate, $paths);
                    } else {
                        // Modules without component support still hold embedded images in their intro.
                        self::remove_filepaths_from_html(
                            $fieldtoupdate,
                            $component,
                            'id = ?',
                            [$cm->instance],
                            $paths
                        );
                    }
                }
                return;
            }
        }

        // Process any other tables related to this component.
        $instance = local::get_component_instance($component);
        if ($instance instanceof file_component_base) {
            $instance->setup_file_and_validate($file->get_filename(), $file);
            $instance->remove_file_links($paths);
        }
    }

    /**
     * List copmonents which support html file replacements.
     * @return string[]
     */
    public static function list_html_file_supported_components() {
        global $CFG;
        $componentsupportpath = $CFG->dirroot . '/admin/tool/ally/classes/componentsupport';
        $dir = new \DirectoryIterator($componentsupportpath);

        $components = [
            'course',
            'block_html',
            'mod_page',
            'mod_folder',
        ];

        foreach ($dir as $fileinfo) {
            if (!$fileinfo->isDot()) {
                $regex = '/(.*)(?:_component.php)$/';

                $matches = [];

                $iscomponentsupportfile = preg_match($regex, $fileinfo->getBasename(), $matches);

                if (empty($matches[1])) {
                    continue;
                }

                $component = $matches[1];

                if ($iscomponentsupportfile) {
                    $type = local::get_component_support_type($component);
                    if ($type != component_base::TYPE_CORE) {
                        $fullcomponent = $type . '_' . $component;
                    } else {
                        $fullcomponent = $component;
                    }
                    $components[] = $fullcomponent;
                }
            }
        }

        return $components;
    }

    /**
     * Queue a file for deletion.
     *
     * @param stored_file $file
     */
    public static function queue_file_for_deletion($file) {
        global $DB;

        $courseid = self::courseid($file);

        $DB->insert_record_raw('tool_ally_deleted_files', [
            'courseid'     => $courseid,
            'pathnamehash' => $file->get_pathnamehash(),
            'contenthash'  => $file->get_contenthash(),
            'mimetype'     => $file->get_mimetype(),
            'timedeleted'  => time(),
        ], false);

        cache::instance()->invalidate_file_keys($file);
    }

    /**
     * Remote a file from the deletion queue. This is needed because a file can be readded while still in
     * the deletion queue, which would cause the file to be 'missing'.
     *
     * @param stored_file $file
     */
    public static function remove_file_from_deletion_queue(stored_file $file) {
        global $DB;

        $courseid = self::courseid($file);

        $DB->delete_records('tool_ally_deleted_files', [
            'courseid'     => $courseid,
            'pathnamehash' => $file->get_pathnamehash(),
            'contenthash'  => $file->get_contenthash(),
        ]);
    }
}
