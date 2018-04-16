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
 * Base class for processing module html.
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally\componentsupport;

use tool_ally\local;
use tool_ally\role_assignments;
use \context;

defined ('MOODLE_INTERNAL') || die();

/**
 * Base class for processing module html.
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

abstract class component_base {

    const TYPE_CORE = 'core';

    const TYPE_MOD = 'mod';

    protected $tablefields = [];

    /**
     * Return component type for this component - a class constant beginning with TYPE_
     *
     * @return int
     */
    abstract public static function component_type();

    protected function installed_modules() {
        global $DB;

        static $installed = []; // Static caching.
        if (!empty($installed)) {
            return $installed;
        }

        $sqllike = $DB->sql_like('plugin', '?');
        $params = [$DB->sql_like_escape('mod').'%'];
        $sql = <<<SQL
        SELECT plugin FROM {config_plugins} WHERE name = 'version' AND $sqllike
SQL;
        $pluginswithversions = $DB->get_records_sql($sql, $params);
        $pluginswithversions = array_map(function($item) {
            return $item->plugin;
        }, $pluginswithversions);

        $pluginlist = \core_component::get_plugin_list('mod');
        foreach ($pluginlist as $plugin => $dir) {
            if (in_array('mod_'.$plugin, $pluginswithversions)) {
                $installed[] = $plugin;
            }
        }

        return $installed;
    }

    /**
     * @return bool
     */
    public function module_installed() {
        if ($this->component_type() !== self::TYPE_MOD) {
            return true;
        }

        $installed = $this->installed_modules();
        $component = $this->get_component_name();
        if (!in_array($component, $installed)) {
            return false;
        }

        return true;
    }

    /**
     * @param string $table
     * @param string $field
     * @throws \coding_exception
     */
    protected function validate_component_table_field($table, $field) {
        if (empty($this->tablefields[$table]) || !is_array($this->tablefields)) {
            throw new \coding_exception('Table '.$table.' is not allowed for the requested component content');
        }
        if (!in_array($field, $this->tablefields[$table])) {
            throw new \coding_exception('Field '.$field.' is not allowed for the table '.$table);
        }
    }

    /**
     * Extract component name from class.
     * @return mixed
     */
    protected function get_component_name() {
        $reflect = new \ReflectionClass($this);
        $class = $reflect->getShortName();
        return explode('_', $class)[0];
    }

    /**
     * Get ids of approved content authors - teachers, managers, admin, etc.
     * @param context $context
     * @return array
     */
    public function get_approved_author_ids_for_context(context $context) {
        $admins = local::get_adminids();
        $ra = new role_assignments(local::get_roleids());
        $userids = $ra->user_ids_for_context($context);
        $userids = array_filter($userids, function($item) {
            return !!$item;
        });
        $userids = array_keys($userids);
        $result = array_unique(array_merge($admins, $userids));
        return $result;
    }

    /**
     * Is the user an approved content author? teachers, managers, admin, etc.
     * @param int $userid
     * @param context $context
     * @return bool
     */
    public function user_is_approved_author_type($userid, context $context) {
        return in_array($userid, $this->get_approved_author_ids_for_context($context));
    }

}
