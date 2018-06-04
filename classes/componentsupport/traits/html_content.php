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
 * Interface for supporting html content.
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @copyright Copyright (c) 2018 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally\componentsupport\traits;

use tool_ally\models\component;
use tool_ally\models\component_content;

defined ('MOODLE_INTERNAL') || die();

trait html_content {


    /**
     * Standard method for getting course html content items.
     *
     * @param $courseid
     * @return array
     * @throws \dml_exception
     */
    protected function std_get_course_html_content_items($courseid) {
        global $DB;

        if (!$this->module_installed()) {
            return;
        }
        $component = $this->get_component_name();

        $array = [];

        $select = "course = ? AND introformat = ? AND intro !=''";
        $rs = $DB->get_recordset_select($component, $select, [$courseid, FORMAT_HTML]);
        foreach ($rs as $row) {
            $array[] = new component(
                $row->id, $component, $component, 'intro', $courseid, $row->timemodified,
                $row->introformat, $row->name);
        }
        $rs->close();

        return $array;
    }

    /**
     * Standard method for getting html content.
     *
     * @param int $id
     * @param string $table
     * @param string $field
     * @param array $tablefields
     * @param null|int $courseid
     * @param string $titlefield
     * @param string $modifiedfield
     * @return component_content | null;
     * @throws \coding_exception
     */
    protected function std_get_html_content($id, $table, $field, $courseid = null, $titlefield = 'name',
                                            $modifiedfield = 'timemodified') {
        global $DB;

        if (!$this->module_installed()) {
            return;
        }

        $component = $this->get_component_name();

        $this->validate_component_table_field($table, $field);

        $record = $DB->get_record($table, ['id' => $id]);
        if (!$record) {
            $ident = 'component='.$component.'&table='.$table.'&field='.$field.'&id='.$id;
            throw new \moodle_exception('error:invalidcomponentident', 'tool_ally', null, $ident);
        }
        $timemodified = $record->$modifiedfield;
        $content = $record->$field;
        $formatfield = $field.'format';
        $contentformat = $record->$formatfield;
        $title = !empty($record->$titlefield) ? $record->$titlefield : null;
        $url = null;
        if (method_exists($this, 'make_url')) {
            $url = $this->make_url($id, $table, $field, $courseid);
        }

        $contentmodel = new component_content($id, $component, $table, $field, $courseid, $timemodified, $contentformat,
            $content, $title, $url);
        return $contentmodel;
    }

    /**
     * Return a content model for a deleted content item.
     * @param int $id
     * @param string $table
     * @param string $field
     * @param int $courseid // This is mandatory because you should be able to get it from the event.
     * @param null|int $timemodified
     * @return component_content
     */
    public function get_html_content_deleted($id, $table, $field, $courseid, $timemodified = null) {
        if (!$this->module_installed()) {
            return;
        }

        $timemodified = $timemodified ? $timemodified : time();
        $component = $this->get_component_name();
        $contentmodel = new component_content($id, $component, $table, $field, $courseid, $timemodified,
            FORMAT_HTML, '', '');
        return $contentmodel;
    }

    /**
     * Standard method for replacing html content.
     * @param int $id
     * @param string $table
     * @param string $field
     * @param string $content
     * @return mixed
     * @throws \coding_exception
     */
    protected function std_replace_html_content($id, $table, $field, $content) {
        global $DB;

        if (!$this->module_installed()) {
            return;
        }

        $this->validate_component_table_field($table, $field);

        $dobj = (object) [
            'id' => $id,
            $field => $content
        ];
        if (!$DB->update_record($table, $dobj)) {
            return false;
        }

        if ($this->component_type() === self::TYPE_MOD && $table === $this->get_component_name()) {
            list ($course, $cm) = get_course_and_cm_from_instance($id, $table);
            \core\event\course_module_updated::create_from_cm($cm, $cm->context)->trigger();
            // Course cache needs updating to show new module text.
            rebuild_course_cache($course->id, true);
        }

        return true;
    }

    /**
     * @param string $module
     * @param int $id
     * @return string
     * @throws \moodle_exception
     */
    protected function make_module_instance_url($module, $id) {
        list($course, $cm) = get_course_and_cm_from_instance($id, $module);
        return new \moodle_url('/course/view.php?id=' . $course->id . '#module-' . $cm->id) . '';
    }

    public function get_annotation($id) {
        return '';
    }
}