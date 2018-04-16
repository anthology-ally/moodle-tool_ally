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
 * Local library.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally;

defined('MOODLE_INTERNAL') || die();

/**
 * Local library.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local {
    /**
     * Get list of role IDs from admin settings.
     *
     * @return array
     */
    public static function get_roleids() {
        $roles = get_config('tool_ally', 'roles');
        if (empty($roles)) {
            return [];
        }
        $roleids = explode(',', $roles);

        return array_combine($roleids, $roleids);
    }

    /**
     * Get list of admin user IDs.
     *
     * @return array
     */
    public static function get_adminids() {
        $userids = array_keys(get_admins());

        return array_combine($userids, $userids);
    }

    /**
     * Load all course contexts into context cache.
     *
     * @param array $courseids
     */
    public static function preload_course_contexts(array $courseids = []) {
        global $DB;

        $fields = \context_helper::get_preload_record_columns_sql('c');
        $params = ['contextlevel' => CONTEXT_COURSE];
        $insql  = '';

        if (!empty($courseids)) {
            $result = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
            $insql  = ' AND c.instanceid '.$result[0];
            $params = array_merge($params, $result[1]);
        }
        $rs = $DB->get_recordset_sql("SELECT $fields FROM {context} c WHERE c.contextlevel = :contextlevel$insql", $params);
        foreach ($rs as $context) {
            \context_helper::preload_from_record($context);
        }
        $rs->close();
    }

    /**
     * Format timestamp using ISO-8601 standard.
     *
     * @param int $timestamp
     * @return string
     */
    public static function iso_8601($timestamp) {
        $date = new \DateTime('', new \DateTimeZone('UTC'));
        $date->setTimestamp($timestamp);

        return $date->format('c');
    }

    /**
     * @param string $iso8601
     * @return int
     */
    public static function iso_8601_to_timestamp($iso8601) {
        $dt = \DateTime::createFromFormat(\DateTime::ISO8601, $iso8601, new \DateTimeZone('UTC'));

        return $dt->getTimestamp();
    }

    /**
     * Is this script running during testing?
     *
     * @return bool
     */
    public static function duringtesting() {
        $runningphpunittest = defined('PHPUNIT_TEST') && PHPUNIT_TEST;
        $runningbehattest = defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING;
        return ($runningphpunittest || $runningbehattest);
    }

    /**
     * Get component class with namespace.
     * @param string $component
     * @return string
     */
    public static function get_component_class($component) {
        if (strpos($component, 'mod_') === 0) {
            $component = substr($component, strlen('mod_'));
        }
        $componentclassname = $component . '_component';
        $componentclassname = 'tool_ally\\componentsupport\\'.$componentclassname;
        return $componentclassname;
    }

    /**
     * Get type of component support for specific component.
     *
     * @param string $component
     * @return string | bool
     */
    public static function get_component_support_type($component) {
        $componentclassname = self::get_component_class($component);
        if (class_exists($componentclassname)) {
            return $componentclassname::component_type();
        }
        return false;
    }
}