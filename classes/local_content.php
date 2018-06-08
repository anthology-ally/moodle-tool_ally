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
 * Rich content library.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally;

defined('MOODLE_INTERNAL') || die();

use core\event\base;
use tool_ally\componentsupport\component_base;
use tool_ally\componentsupport\interfaces\annotation_map;
use tool_ally\componentsupport\interfaces\html_content;
use tool_ally\models\component;
use tool_ally\models\component_content;

/**
 * Rich content library.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_content {

    /**
     * @param string $component
     * @return component_base|bool;
     */
    public static function component_instance($component) {
        $componentclassname = local::get_component_class($component);
        if (!class_exists($componentclassname)) {
            return false;
        }
        return new $componentclassname();
    }

    /**
     * Get supports html content.
     *
     * @param string $component
     * @return bool
     */
    public static function component_supports_html_content($component) {
        $component = self::component_instance($component);
        if (!$component) {
            return false;
        }
        if (!$component->module_installed()) {
            return false;
        }
        return method_exists($component, 'get_course_html_content_items');
    }

    /**
     * List copmonents which support html content replacements.
     * @return string[]
     */
    public static function list_html_content_supported_components() {
        global $CFG;
        $componentsupportpath = $CFG->dirroot . '/admin/tool/ally/classes/componentsupport';
        $dir = new \DirectoryIterator($componentsupportpath);

        $components = [];

        foreach ($dir as $fileinfo) {
            if ($fileinfo->isDot()) {
                continue;
            }

            $regex = '/(.*)(?:_component.php)$/';

            $matches = [];

            $iscomponentsupportfile = preg_match($regex, $fileinfo->getBasename(), $matches);

            if (empty($matches[1]) || !$iscomponentsupportfile) {
                continue;
            }

            $component = $matches[1];

            if (self::component_supports_html_content($component)) {
                $type = local::get_component_support_type($component);
                if ($type != component_base::TYPE_CORE) {
                    $fullcomponent = $type . '_' . $component;
                } else {
                    $fullcomponent = $component;
                }
                $components[] = $fullcomponent;
            }

        }

        return $components;
    }

    /**
     * @param $courseid
     * @return array;
     */
    public static function annotation_maps($courseid) {
        $components = self::list_html_content_supported_components();
        $maps = [];
        foreach ($components as $component) {
            $instance = self::component_instance($component);
            // We are now working with a component instance located in admin/tool/ally/classes/componentsupport.
            if ($instance instanceof annotation_map) {
                try {
                    $maps = array_merge($maps, [$component => $instance->get_annotation_maps($courseid)]);
                } catch (\moodle_exception $ex) {
                    // Component not identified correctly.
                    $msg = $ex->getMessage();
                    $msg .= '<br> Component: '.$component;
                    $msg .= '<br> Course ID: '.$courseid;
                    \tool_ally\event\annotation_module_error::create_from_msg($msg)->trigger();
                }
            }
        }
        return $maps;
    }

    /**
     * Get course html content details for specific component and course.
     * @param string $component
     * @param int $courseid
     * @return component[]
     */
    public static function get_course_html_content_items($component, $courseid) {
        $component = self::component_instance($component);
        return $component->get_course_html_content_items($courseid);
    }

    /**
     * @param int $id
     * @param string $component
     * @param string $table
     * @param string $field
     * @param int $courseid
     * @return bool|component_content
     */
    public static function get_html_content($id, $component, $table, $field, $courseid = null) {
        $component = self::component_instance($component);
        if (empty($component)) {
            return false;
        }
        return $component->get_html_content($id, $table, $field, $courseid);
    }

    /**
     * Return a content model for a deleted content item.
     * @param int $id
     * @param string $table
     * @param string $field
     * @param null|int $courseid
     * @param null|int $timemodified
     * @return component_content|bool
     */
    public static function get_html_content_deleted($id, $component, $table, $field,
                                                    $courseid = null, $timemodified = null) {
        $component = self::component_instance($component);
        if (empty($component)) {
            return false;
        }
        return $component->get_html_content_deleted($id, $table, $field, $courseid, $timemodified);
    }

    /**
     * @param int $id
     * @param string $component
     * @return bool|component_content[]
     */
    public static function get_all_html_content($id, $component) {
        $component = self::component_instance($component);
        if (empty($component)) {
            return false;
        }
        if (!$component instanceof html_content) {
            return false;
        }
        return $component->get_all_html_content($id);
    }

    /**
     * @param int $id
     * @param string $component
     * @param string $table
     * @param string $field
     * @param string $content
     * @return bool|string
     */
    public static function replace_html_content($id, $component, $table, $field, $content) {
        $component = self::component_instance($component);
        return $component->replace_html_content($id, $table, $field, $content);
    }

    /**
     * @param \context $context
     * @return string
     */
    public static function get_annotation($context) {
        if ($context->contextlevel === CONTEXT_MODULE) {
            try {
                list($course, $cm) = get_course_and_cm_from_cmid($context->instanceid);
                $component = self::component_instance($cm->modname);
                if ($component && method_exists($component, 'get_annotation')) {
                    return $component->get_annotation($cm->instance);
                }
            } catch (\moodle_exception $ex) {
                // Component not identified correctly.
                $msg = $ex->getMessage();
                $msg .= '<br> Context: '.$context->path;
                $msg .= '<br> Instance ID: '.$context->instanceid;
                \tool_ally\event\annotation_module_error::create_from_msg($msg)->trigger();
                return '';
            }
        }
        return '';
    }

    /**
     * A message to send to Ally about a content being updated, created, etc.
     *
     * Warning: be very careful about editing this message.  It's used
     * for webservices and for pushed updates.
     *
     * @param component_content $componentcontent
     * @return array
     */
    public static function to_crud($componentcontent, $eventname) {
        return [
            'entity_id'    => $componentcontent->entity_id(),
            'context_id'   => (string) $componentcontent->get_courseid(),
            'event_name'   => $eventname,
            'event_time'   => local::iso_8601($componentcontent->timemodified),
            'content_hash' => $componentcontent->contenthash
        ];
    }

    /**
     * @param int $courseid
     * @param int $id
     * @param string $component
     * @throws \dml_exception
     * @return bool|int
     */
    public static function queue_delete($courseid, $id, $component, $table, $field) {
        global $DB;

        return $DB->insert_record_raw('tool_ally_deleted_content', [
            'comprowid'        => $id,
            'courseid'     => $courseid,
            'component'    => $component,
            'comptable'        => $table,
            'compfield'        => $field,
            'timedeleted'  => time(),
        ], false);
    }
}