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
 * @copyright Copyright (c) 2018 Open LMS (https://www.openlms.net) / 2023 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally\componentsupport;

use cm_info;
use tool_ally\componentsupport\interfaces\annotation_map;
use tool_ally\componentsupport\interfaces\content_sub_tables;
use tool_ally\componentsupport\interfaces\html_content as iface_html_content;
use tool_ally\componentsupport\traits\html_content;
use tool_ally\componentsupport\traits\embedded_file_map;
use tool_ally\models\component;
use tool_ally\models\component_content;
use moodle_url;

/**
 * Html content support for book module.
 * @copyright Copyright (c) 2018 Open LMS (https://www.openlms.net) / 2023 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class book_component extends component_base implements annotation_map, content_sub_tables, iface_html_content {
    use html_content;
    use embedded_file_map;

    /**
     * {@inheritdoc}
     * @var array
     */
    protected array $tablefields = [
        'book' => ['intro'],
        'book_chapters' => ['content'],
    ];

    /**
     * {@inheritdoc}
     */
    public static function component_type(): string {
        return self::TYPE_MOD;
    }

    /**
     * {@inheritdoc}
     */
    public function get_course_html_content_items(int $courseid): array {
        global $DB;

        $array = [];
        if (!$this->module_installed()) {
            return $array;
        }

        $sql = <<<SQL
               SELECT b.id AS bookid,
                      b.timemodified AS booktimemodified,
                      b.introformat AS bookintroformat,
                      b.name AS bookname,
                      bc.id as chapterid,
                      bc.timemodified AS chaptertimemodified,
                      bc.contentformat AS chaptercontentformat,
                      bc.title AS chaptertitle
                 FROM {book} b
            LEFT JOIN {book_chapters} bc on bc.bookid = b.id AND bc.contentformat = ?
                WHERE b.introformat = ? AND b.course = ?
             ORDER BY b.id ASC
SQL;

        $rs = $DB->get_recordset_sql($sql, [FORMAT_HTML, FORMAT_HTML, $courseid]);

        $prevbookid = null;
        foreach ($rs as $row) {
            // Add an entry for the book if it's not already been added.
            if ($row->bookid !== $prevbookid) {
                $prevbookid = $row->bookid;
                $array[] = new component(
                    $row->bookid,
                    'book',
                    'book',
                    'intro',
                    $courseid,
                    $row->booktimemodified,
                    $row->bookintroformat,
                    $row->bookname
                );
            }
            // Add an entry for the book chapter if it's populated.
            if (!empty($row->chaptertimemodified)) {
                $array[] = new component(
                    $row->chapterid,
                    'book',
                    'book_chapters',
                    'content',
                    $courseid,
                    $row->chaptertimemodified,
                    $row->chaptercontentformat,
                    $row->chaptertitle
                );
            }
        }
        $rs->close();

        return $array;
    }

    /**
     * {@inheritdoc}
     */
    public function get_html_content(int $id, string $table, string $field, ?int $courseid = null): ?component_content {
        global $DB;
        $content = $this->std_get_html_content($id, $table, $field, $courseid);
        if (empty($content)) {
            return $content;
        }

        if ($table === 'book') {
            $content->title = $DB->get_field('book', 'name', ['id' => $id]);
        }
        if ($table === 'book_chapters') {
            $content->title = $DB->get_field('book_chapters', 'title', ['id' => $id]);
        }
        return ($content);
    }

    /**
     * Get chapter html content.
     *
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

        $chapters = $DB->get_records('book_chapters', ['bookid' => $bookid]);

        if (empty($chapters)) {
            return $content;
        }

         [$course, $cm] = get_course_and_cm_from_instance($bookid, 'book');

        foreach ($chapters as $chapter) {
            $url = new \moodle_url('/mod/book/view.php', ['id' => $cm->id, 'chapterid' => $chapter->id]);
            $contentmodel = new component_content(
                $chapter->id,
                'book',
                'book_chapters',
                'content',
                $course->id,
                $chapter->timemodified,
                'contentformat',
                $chapter->content,
                $chapter->title,
                $url
            );
            $content[] = $contentmodel;
        }

        return $content;
    }

    /**
     * {@inheritdoc}
     */
    public function get_all_html_content(int $id): array {
        return array_merge(
            [$this->get_html_content($id, 'book', 'intro')],
            $this->get_chapter_html_content($id)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function replace_html_content(int $id, string $table, string $field, string $content): ?bool {
        return $this->std_replace_html_content($id, $table, $field, $content);
    }

    /**
     * {@inheritdoc}
     */
    public function resolve_course_id(int $id, string $table, string $field): int {
        global $DB;

        if ($table === 'book') {
            $course = $DB->get_field('book', 'course', ['id' => $id]);
            return $course;
        }

        throw new \coding_exception('Invalid table used to recover course id ' . $table);
    }

    /**
     * {@inheritdoc}
     */
    public function get_annotation_maps(int $courseid): array {
        global $PAGE;

        if (!$this->module_installed()) {
            return [];
        }

        $intros = [];
        $content = [];
        $introcis = $this->get_intro_html_content_items($courseid, false);
        $bookids = [];
        foreach ($introcis as $introci) {
            [$course, $cm] = get_course_and_cm_from_instance($introci->id, 'book', $courseid);
            $intros[$cm->id] = $introci->entity_id();

            if ($PAGE->pagetype !== 'mod-book-view') {
                continue; // No point building annotations for pages that don't use them!
            }

            $bookids[] = $cm->instance;
        }

        if (!empty($bookids)) {
            $contentcis = $this->get_selected_html_content_items(
                $courseid,
                'content',
                'book_chapters',
                'bookid',
                $bookids,
                'title'
            );
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
             [$course, $cm] = get_course_and_cm_from_instance($bookid, 'book', $courseid);
            unset($course);
            return new moodle_url('/mod/book/view.php', ['id' => $cm->id, 'chapterid' => $id]) . '';
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function get_file_area(string $table, string $field): string {
        if ($table === 'book_chapters' && $field === 'content') {
            return 'chapter';
        }
        return parent::get_file_area($table, $field);
    }

    /**
     * {@inheritdoc}
     */
    public function get_file_item(string $table, string $field, int $id): int {
        if ($table === 'book_chapters' && $field === 'content') {
            return $id;
        }
        return parent::get_file_item($table, $field, $id);
    }

    /**
     * {@inheritdoc}
     */
    public function queue_delete_sub_tables(cm_info $cm): void {
        $chapters = $this->get_chapter_html_content($cm->instance);
        $this->bulk_queue_delete_content($chapters);
    }
}
