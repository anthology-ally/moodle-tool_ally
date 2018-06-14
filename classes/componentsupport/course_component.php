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
 * Html content support for courses.
 * @copyright Copyright (c) 2018 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally\componentsupport;

defined ('MOODLE_INTERNAL') || die();

use tool_ally\componentsupport\traits\html_content;
use tool_ally\models\component;
use tool_ally\componentsupport\interfaces\html_content as iface_html_content;
use moodle_url;

/**
 * Html content support for courses.
 * @copyright Copyright (c) 2018 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_component extends component_base implements iface_html_content {

    use html_content;

    protected $tablefields = [
        'course' => ['summary'],
        'course_sections' => ['summary']
    ];

    public static function component_type() {
        return self::TYPE_CORE;
    }

    /**
     * @param int $courseid
     * @return \moodle_recordset
     * @throws \dml_exception
     */
    public function get_course_section_summary_rows($courseid) {
        global $DB;

        $select = "course = ? AND summaryformat = ? AND summary !=''";
        return $DB->get_recordset_select('course_sections', $select, [$courseid, FORMAT_HTML]);
    }

    public function get_course_html_content_items($courseid) {
        global $DB;

        $array = [];

        // Add course summary.
        $select = "id = ? AND summaryformat = ? AND summary !=''";
        $row = $DB->get_record_select('course', $select, [$courseid, FORMAT_HTML]);
        if ($row) {
            $array[] = new component(
                    $row->id, 'course', 'course', 'summary', $courseid, $row->timemodified,
                    $row->summaryformat, $row->fullname);
        }

        // Add course sections.
        $rs = $this->get_course_section_summary_rows($courseid);
        foreach ($rs as $row) {
            $array[] = new component(
                    $row->id, 'course', 'course_sections', 'summary', $courseid, $row->timemodified,
                    $row->summaryformat, $row->name);
        }
        $rs->close();

        return $array;
    }

    public function get_html_content($id, $table, $field, $courseid = null) {
        $titlefield = $table === 'course' ? 'fullname' : 'name';
        return $this->std_get_html_content($id, $table, $field, $courseid, $titlefield);
    }

    /**
     * Get section number corresponding to $sectionid.
     * @param $sectionid
     * @return int
     * @throws \dml_exception
     */
    private function get_section_number($sectionid) {
        global $DB;

        static $sections = null; // Static caching for performance.

        if (is_null($sections)) {
            // With a 1000 courses this would take approx 516k to cache.
            // With 10000 courses 4M to cache.
            // With 100000 courses 32M to cache.
            // So we are good to use static caching.
            // http://sandbox.onlinephpfunctions.com/code/aaa8f0ed270c7e787caa6428c816fb82b11784d0.
            $sections = $DB->get_records_menu('course_sections', null, '', 'id, section');
        }

        if (!isset($sections[$sectionid])) {
            // Better not to throw an error because the web service might be requesting information for a section
            // that has been deleted or something.
            return null;
        }

        return $sections[$sectionid];
    }

    /**
     * Attempt to make url for content.
     * @param int $id
     * @param string $table
     * @param string $field
     * @param null|int $courseid
     */
    public function make_url($id, $table, $field, $courseid) {
        global $DB;

        if ($table === 'course') {
            return new moodle_url('/course/edit.php?id='.$id).'';
        } else if ($table === 'course_sections') {
            $sectionnumber = $this->get_section_number($id);
            if (empty($courseid)) {
                $courseid = $DB->get_field('course_sections', 'course', ['id' => $id]);
            }
            return new moodle_url('/course/view.php?id='.$courseid.'#section-'.$sectionnumber).'';
        }
        return null;
    }

    public function get_all_html_content($id) {
        global $DB;
        $content = [];
        $content[] = $this->get_html_content($id, 'course', 'summary');
        $sections = $DB->get_records('course_sections', ['course' => $id]);
        foreach ($sections as $section) {
            $content[] = $this->get_html_content($section->id, 'course_sections', 'summary');
        }
        return $content;
    }

    public function replace_html_content($id, $table, $field, $content) {
        global $DB;

        if ($table === 'course_sections') {
            $section = $DB->get_record('course_sections', ['id' => $id]);
            if ($section) {
                $data = ['id' => $section->id, 'summary' => $content];
                course_update_section($section->course, $section, $data);
                return true;
            }
            return false;
        } else {

            return $this->std_replace_html_content($id, $table, $field, $content);
        }
    }

    public function resolve_course_id($id, $table, $field) {
        global $DB;

        if ($table === 'course') {
            return $id;
        } else if ($table === 'course_sections') {
            $section = $DB->get_record('course_sections', ['id' => $id]);
            return $section->course;
        }

        throw new \coding_exception('Invalid table used to recover course id '.$table);
    }
}
