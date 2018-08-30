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
 * Html file replacement support for core lessons.
 * @package tool_ally
 * @author    David Castro <david.castro@blackboard.com>
 * @copyright Copyright (c) 2017 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally\componentsupport;

defined ('MOODLE_INTERNAL') || die();

use tool_ally\componentsupport\interfaces\annotation_map;
use tool_ally\componentsupport\traits\html_content;
use tool_ally\componentsupport\traits\embedded_file_map;
use tool_ally\componentsupport\interfaces\html_content as iface_html_content;
use tool_ally\local_file;

/**
 * Class lesson_component.
 * Html file replacement support for core lessons.
 * @package tool_ally
 * @author    David Castro <david.castro@blackboard.com>
 * @copyright Copyright (c) 2017 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lesson_component extends file_component_base implements iface_html_content, annotation_map {

    use html_content;
    use embedded_file_map;

    protected $tablefields = [
        'lesson'       => ['intro'],
        'lesson_pages' => ['contents']
    ];

    public static function component_type() {
        return self::TYPE_MOD;
    }

    public function get_annotation_maps($courseid) {
        global $PAGE;

        if (!$this->module_installed()) {
            return [];
        }

        $intros = [];
        $content = [];
        $introcis = $this->get_intro_html_content_items($courseid);

        foreach ($introcis as $introci) {
            list($course, $cm) = get_course_and_cm_from_instance($introci->id, 'lesson');
            $intros[$cm->id] = $introci->entity_id();

            if ($PAGE->pagetype !== 'mod-lesson-view') {
                continue; // No point building annotations for pages that don't use them!
            }

            $lessonid = $cm->instance;
            $contentcis = $this->get_selected_html_content_items($courseid, 'contents',
                'lesson_pages', 'lessonid', $lessonid, 'title');

            foreach ($contentcis as $contentci) {
                $content[$contentci->id] = $contentci->entity_id();
            }
        }

        return ['intros' => $intros, 'pages' => $content];
    }

    public function get_course_html_content_items($courseid) {
        return $this->std_get_course_html_content_items($courseid);
        // TODO - lesson pages
    }

    public function replace_file_links() {
        $file = $this->file;

        $area = $file->get_filearea();
        $itemid = $file->get_itemid();

        if ($area === 'page_contents') {
            local_file::update_filenames_in_html('contents', 'lesson_pages', ' id = ? ',
                ['id' => $itemid], $this->oldfilename, $file->get_filename());
        }
    }

    public function get_html_content($id, $table, $field, $courseid = null) {
        $titlefld = 'name';
        if ($table === 'lesson_pages') {
            $titlefld = 'title';
        }
        return $this->std_get_html_content($id, $table, $field, $courseid, $titlefld);
    }

    private function get_lesson_pages($lessonid) {
        global $DB;
        return $DB->get_records('lesson_pages', ['lessonid' => $lessonid]);
    }

    public function get_all_html_content($id) {
        $lesson = $this->get_html_content($id, 'lesson', 'intro');
        $pagerows = $this->get_lesson_pages($id);
        $pages = [];
        foreach ($pagerows as $row) {
            $pages[] = $this->std_get_html_content(
                $id, 'lesson_pages', 'contents', $lesson->courseid, 'title', 'timemodified', null, $row);
        }
        return array_merge([$lesson], $pages);
    }

    public function replace_html_content($id, $table, $field, $content) {
        return $this->std_replace_html_content($id, $table, $field, $content);
    }

    public function resolve_course_id($id, $table, $field) {
        global $DB;

        if ($table === 'lesson') {
            return $DB->get_field('lesson', 'course', ['id' => $id]);
        } else if ($table === 'lesson_pages') {
            $params = [$id];
            $sql = <<<SQL
            SELECT course FROM {lesson} l
              JOIN {lesson_pages} lp ON lp.lessonid = l.id
             WHERE lp.id = ?
SQL;

            return $DB->get_field_sql($sql, $params);
        }
    }

}
