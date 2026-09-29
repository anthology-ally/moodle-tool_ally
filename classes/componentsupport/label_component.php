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
     * Matches an <img> tag, along with any stray closing </img>. Quoted attribute values are
     * consumed whole, so a '>' inside one - as in alt="1 > 0" - does not end the match early and
     * leave the remainder of the tag behind to be read as text.
     */
    private const IMG_TAG_REGEX = '/<img\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>(\s*<\/img>)?/is';

    /**
     * Fallback title derived from a label's content, for use when the label has no "Title in
     * course index" of its own. Images are stripped first so their alt text isn't surfaced as the
     * title, falling back to the module's generic name if nothing else remains.
     *
     * @param string $content The label's HTML content.
     * @return string
     */
    public static function title_from_content($content) {
        $textonly = preg_replace(self::IMG_TAG_REGEX, '', $content);
        $title = trim(\core_text::substr(html_to_text($textonly), 0, 50));
        return $title !== '' ? $title : get_string('modulename', 'label');
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
        if (empty($content->title)) {
            $content->title = self::title_from_content($content->content);
        }
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
