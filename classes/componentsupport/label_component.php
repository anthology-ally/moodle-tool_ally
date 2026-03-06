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
 * Support for course content
 * @copyright Copyright (c) 2018 Open LMS (https://www.openlms.net) / 2023 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally\componentsupport;

use tool_ally\componentsupport\traits\html_content;
use tool_ally\componentsupport\traits\embedded_file_map;
use tool_ally\componentsupport\interfaces\html_content as iface_html_content;
use tool_ally\models\component_content;

/**
 * Html content support for labels.
 * @copyright Copyright (c) 2018 Open LMS (https://www.openlms.net) / 2023 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class label_component extends component_base implements iface_html_content {
    use html_content;
    use embedded_file_map;

    /**
     * {@inheritdoc}
     * @var array
     */
    protected array $tablefields = [
        'label' => ['intro'],
    ];

    /**
     * {@inheritdoc}
     */
    public static function component_type(): string {
        return self::TYPE_MOD;
    }

    /**
     * Get a title for the label from its content.
     */
    private function get_label_title_from_content($content) {
        $title = \core_text::substr(html_to_text($content), 0, 50);
        return trim($title);
    }

    /**
     * {@inheritdoc}
     */
    public function get_course_html_content_items(int $courseid): array {
        return $this->std_get_course_html_content_items($courseid);
    }

    /**
     * {@inheritdoc}
     */
    public function get_html_content(int $id, string $table, string $field, ?int $courseid = null): ?component_content {
        $content = $this->std_get_html_content($id, $table, $field, $courseid);
        if (empty($content)) {
            return $content;
        }
        $content->title = $this->get_label_title_from_content($content->content);
        return ($content);
    }

    /**
     * {@inheritdoc}
     */
    public function get_all_html_content(int $id): array {
        return [$this->get_html_content($id, 'label', 'intro')];
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
    public function get_annotation(int $id): string {
        return $this->get_component_name() . ':' . $this->get_component_name() . ':intro:' . $id;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve_course_id(int $id, string $table, string $field): int {
        global $DB;

        if ($table === 'label') {
            $label = $DB->get_record('label', ['id' => $id]);
            return $label->course;
        }

        throw new \coding_exception('Invalid table used to recover course id ' . $table);
    }

    /**
     * Attempt to make url for content.
     * @param int $id
     * @param string $table
     * @param string $field
     * @param int $courseid
     */
    public function make_url($id, $table, $field = null, $courseid = null) {
        if (!isset($this->tablefields[$table])) {
            return null;
        }
        return $this->make_module_instance_url($table, $id);
    }
}
