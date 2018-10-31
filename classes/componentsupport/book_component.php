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
 * Html content support for book module.
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally\componentsupport;

defined ('MOODLE_INTERNAL') || die();

use tool_ally\componentsupport\interfaces\annotation_map;
use tool_ally\componentsupport\interfaces\html_content as iface_html_content;
use tool_ally\componentsupport\traits\html_content;
use tool_ally\componentsupport\traits\embedded_file_map;
use tool_ally\models\component_content;

use moodle_url;

/**
 * Html content support for book module.
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class book_component extends component_base implements iface_html_content, annotation_map {

    use html_content;
    use embedded_file_map;

    protected $tablefields = [
        'book' => ['intro'],
        'book_chapters' => ['content']
    ];

    public static function component_type() {
        return self::TYPE_MOD;
    }

    public function get_course_html_content_items($courseid) {
        return $this->std_get_course_html_content_items($courseid);
    }

    public function get_html_content($id, $table, $field, $courseid = null) {
        global $DB;
        $content = $this->std_get_html_content($id, $table, $field, $courseid);
        if ($table === 'book') {
            $content->title = $DB->get_field('book', 'name', ['id' => $id]);
        }
        if ($table === 'book_chapters') {
            $content->title = $DB->get_field('book_chapters', 'title', ['id' => $id]);
        }
        return ($content);
    }

    /**
     * @param int $bookid
     * @return component_content[]
     * @throws \dml_exception
     */
    private function get_chapter_html_content($bookid) {
        global $DB;

        $content = [];

        if (!$this->module_installed()) {
            return null;
        }

        list ($course, $cm) = get_course_and_cm_from_instance($bookid, 'book');

        $chapters = $DB->get_records('book_chapters', ['bookid' => $bookid]);
        foreach ($chapters as $chapter) {
            $url = new \moodle_url('/mod/book/view.php', ['id' => $cm->id, 'chapterid' => $chapter->id]);
            $contentmodel = new component_content($chapter->id, 'book', 'book_chapters',
                'content', $course->id,
                $chapter->timemodified, 'contentformat',
                $chapter->content, $chapter->title, $url);
            $content[] = $contentmodel;
        }

        return $content;
    }

    public function get_all_html_content($id) {
        return array_merge([$this->get_html_content($id, 'book', 'intro')],
            $this->get_chapter_html_content($id));
    }

    public function replace_html_content($id, $table, $field, $content) {
        return $this->std_replace_html_content($id, $table, $field, $content);
    }

    public function resolve_course_id($id, $table, $field) {
        global $DB;

        if ($table === 'book') {
            $course = $DB->get_field('book', 'course', ['id' => $id]);
            return $course;
        }

        throw new \coding_exception('Invalid table used to recover course id '.$table);
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
            list($course, $cm) = get_course_and_cm_from_instance($introci->id, 'book');
            $intros[$cm->id] = $introci->entity_id();

            if ($PAGE->pagetype !== 'mod-book-view') {
                continue; // No point building annotations for pages that don't use them!
            }

            $bookid = $cm->instance;
            $contentcis = $this->get_selected_html_content_items($courseid, 'content',
                    'book_chapters', 'bookid', $bookid, 'title');
            foreach ($contentcis as $contentci) {
                $content[$contentci->id] = $contentci->entity_id();
            }
        }

        return ['intros' => $intros, 'chapters' => $content];
    }


    /**
     * Attempt to make url for content.
     * @param int $id
     * @param string $table
     * @param string $field
     * @param int $courseid
     * @return null|string;
     */
    public function make_url($id, $table, $field = null, $courseid = null) {
        global $DB;

        if (!isset($this->tablefields[$table])) {
            return null;
        }
        if ($table === 'book') {
            return $this->make_module_instance_url($table, $id);
        } else if ($table === 'book_chapters') {
            $bookid = $DB->get_field('book_chapters', 'bookid', ['id' => $id]);
            return new moodle_url('/mod/book/view.php', ['id' => $bookid, 'chapterid' => $id]).'';
        }
        return null;
    }
}