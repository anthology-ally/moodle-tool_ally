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
 * Upgrade path.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade path.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_tool_ally_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2016121501) {
        // Migrate settings from report_allylti to tool_ally.
        $settings = ['key', 'secret', 'adminurl'];
        foreach ($settings as $setting) {
            $value = get_config('report_allylti', $setting);

            if ($value !== false) {
                set_config($setting, $value, 'tool_ally');
            }

            unset_config($setting, 'report_allylti');
        }

        upgrade_plugin_savepoint(true, 2016121501, 'tool', 'ally');
    }

    if ($oldversion < 2016121900) {

        // Define table tool_ally_deleted_files to be created.
        $table = new xmldb_table('tool_ally_deleted_files');

        // Adding fields to table tool_ally_deleted_files.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('pathnamehash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
        $table->add_field('contenthash', XMLDB_TYPE_CHAR, '40', null, null, null, null);
        $table->add_field('mimetype', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('timedeleted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table tool_ally_deleted_files.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for tool_ally_deleted_files.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Ally savepoint reached.
        upgrade_plugin_savepoint(true, 2016121900, 'tool', 'ally');
    }

    if ($oldversion < 2016121910) {
        $user = $DB->get_record('user', ['username' => 'ally_webuser']);
        if ($user) {
            // We only do this if auto config has created a user, we are not doing auto config here.
            $user->policyagreed = 1;
            $DB->update_record('user', $user);
        }

        // Ally savepoint reached.
        upgrade_plugin_savepoint(true, 2016121910, 'tool', 'ally');
    }

    if ($oldversion < 2017120811) {

        // Define table tool_ally_deleted_content to be created.
        $table = new xmldb_table('tool_ally_deleted_content');

        // Adding fields to table tool_ally_deleted_content.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('comptable', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('instanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('field', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timedeleted', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table tool_ally_deleted_content.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for tool_ally_deleted_content.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Ally savepoint reached.
        upgrade_plugin_savepoint(true, 2017120811, 'tool', 'ally');
    }

    return true;
}
