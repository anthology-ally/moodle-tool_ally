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
 * Trait for supporting embedded file mapping for html content.
 * @author    Guy Thomas <citricity@gmail.com>
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally\componentsupport\traits;

use tool_ally\componentsupport\component_base;
use tool_ally\local;
use tool_ally\local_file;
use tool_ally\local_content;
use tool_ally\models\component_content;

use coding_exception;
use context;
use context_block;
use context_course;
use file_storage;

defined ('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/filestorage/file_storage.php');

trait embedded_file_map {

    /**
     * General purpose function for applying embedded file map to component content.
     *
     * @param component_content|null $content
     * @return component_content
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function apply_embedded_file_map(?component_content $content) {

        $html = $content->content;
        $doc = local_content::build_dom_doc($html);
        if (!$doc) {
            return $content;
        }
        $results = $doc->getElementsByTagName('img');

        $fs = new file_storage();
        $component = local::get_component_instance($content->component);

        foreach ($results as $result) {
            if (!is_object($result->attributes) || !is_object($result->attributes->getNamedItem('src'))) {
                continue;
            }
            $src = $result->attributes->getNamedItem('src')->nodeValue;

            $componenttype = local::get_component_support_type($content->component);
            if ($componenttype === component_base::TYPE_MOD) {
                if ($content->table === $content->component) {
                    /** @var \cm_info $cm */
                    list($course, $cm) = get_course_and_cm_from_instance($content->id, $content->component);
                } else {
                    // Sub table detected - e.g. forum discussion, book chapter, etc...
                    $moduleinstanceid = $component->resolve_module_instance_id($content->table, $content->id);
                    list($course, $cm) = get_course_and_cm_from_instance($moduleinstanceid, $content->component);
                }
                $context = $cm->context;

                $compstr = 'mod_'.$content->component;
            } else if ($componenttype === component_base::TYPE_BLOCK) {
                $context = context_block::instance($content->id);
                $compstr = $content->component;
            } else {
                if (!$content->courseid) {
                    return $content;
                }
                $context = context_course::instance($content->courseid);
                $compstr = $content->component;
            }

            $file = null;

            if (strpos($src, 'pluginfile.php') !== false) {
                $props = local_file::get_fileurlproperties($src);
                $context = context::instance_by_id($props->contextid, IGNORE_MISSING);
                if (!$context) {
                    // The context couldn't be found (perhaps this is a copy/pasted url pointing at old deleted content).
                    // Move on.
                    continue;
                }

                $file = local_file::get_file_fromprops($props);
            } else if (strpos($src, '@@PLUGINFILE@@') !== false) {
                $filename = str_replace('@@PLUGINFILE@@', '', $src);
                $filename = urldecode($filename);
                if (strpos($filename, '/') === 0) {
                    $filename = substr($filename, 1);
                }
                $filearea = $component->get_file_area($content->table, $content->field);
                if (!$filearea) {
                    throw new coding_exception('Failed to get filearea for component_content '.
                        var_export($content, true));
                }
                $fileitem = $component->get_file_item($content->table, $content->field, $content->id);
                $filepath = $component->get_file_path($content->table, $content->field, $content->id);
                $file = $fs->get_file($context->id, $compstr, $filearea, $fileitem, $filepath, $filename);
            }

            if ($file) {
                $content->embeddedfiles[] = [
                    'filename' => rawurlencode($file->get_filename()),
                    'pathnamehash' => $file->get_pathnamehash()
                ];
            }
        }
        return $content;
    }
}