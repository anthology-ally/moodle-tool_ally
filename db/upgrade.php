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

    return true;
}