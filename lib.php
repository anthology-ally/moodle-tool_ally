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
 * Library for core hooks.
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use tool_ally\file_processor,
    tool_ally\local_file,
    tool_ally\cache,
    tool_ally\local_content,
    tool_ally\componentsupport\interfaces\content_sub_tables;

/**
 * Callback for after file deleted.
 * @param stdClass $filerecord
 */
function tool_ally_after_file_deleted($filerecord) {
    global $DB;

    $fs = get_file_storage();
    $file = $fs->get_file_instance($filerecord);

    $courseid = local_file::courseid($file, IGNORE_MISSING);
    if (!local_file::file_validator()->validate_stored_file($file)) {
        return; // Ally does not support files outside of a course.
    }

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
 * Callback for after file created.
 * @param stdClass $filerecord
 */
function tool_ally_after_file_created($filerecord) {
    $fs = get_file_storage();
    $file = $fs->get_file_instance($filerecord);
    file_processor::push_file_update($file);

    cache::instance()->invalidate_file_keys($file);
}

/**
 * Callback for after file updated.
 * @param stdClass $filerecord
 */
function tool_ally_after_file_updated($filerecord) {
    $fs = get_file_storage();
    $file = $fs->get_file_instance($filerecord);
    file_processor::push_file_update($file);

    cache::instance()->invalidate_file_keys($file);
}

/**
 * Callback for pre-module deletion.
 * @param stdClass $cm (cm record from course_modules table)
 */
function tool_ally_pre_course_module_delete(stdClass $cm) {
    /** @var cm_info $cm */
    list ($course, $cm) = get_course_and_cm_from_cmid($cm->id);
    $component = local_content::component_instance($cm->modname);
    if (!$component || !$component instanceof content_sub_tables) {
        return;
    }
    // Queue for deletion, all records related to the main record for this course module.
    $component->queue_delete_sub_tables($cm);
}