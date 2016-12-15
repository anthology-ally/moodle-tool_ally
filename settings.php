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
 * Admin settings.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('tool_ally', get_string('pluginname', 'tool_ally'));

    $settings->add(new admin_setting_pickroles('tool_ally/roles', new lang_string('contentauthors', 'tool_ally'),
        new lang_string('contentauthorsdesc', 'tool_ally'), ['manager', 'coursecreator', 'editingteacher']));

    $settings->add(new admin_setting_configtext('tool_ally/key', new lang_string('key', 'tool_ally'),
        new lang_string('keydesc', 'tool_ally'), '', PARAM_ALPHANUMEXT));

    $settings->add(new admin_setting_configpasswordunmask('tool_ally/secret',
        new lang_string('secret', 'tool_ally'), new lang_string('secretdesc', 'tool_ally'), ''));

    $settings->add(new admin_setting_configtext('tool_ally/adminurl', new lang_string('adminurl', 'tool_ally'),
        new lang_string('adminurldesc', 'tool_ally'), '', PARAM_URL, 60));

    $settings->add(new admin_setting_configtext('tool_ally/pushurl', new lang_string('pushurl', 'tool_ally'),
        new lang_string('pushurldesc', 'tool_ally'), '', PARAM_URL, 60));

    $ADMIN->add('tools', $settings);
}
