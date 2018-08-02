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

use tool_ally\adminsetting\ally_trim;

defined('MOODLE_INTERNAL') || die();

/**
 * @package tool_admin
 * @author    Guy Thomas <citricity@gmail.com>
 * @copyright Copyright (c) 2017 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_ally_adminsetting_ally_trim_testcase extends advanced_testcase {
    /**
     * Test settings are trimmed.
     */
    public function test_trim() {
        $this->resetAfterTest();
        $text = '    This should be trimmed    ';
        $setting = new ally_trim('tool_ally/testtrim', new lang_string('key', 'tool_ally'),
            new lang_string('keydesc', 'tool_ally'), '', PARAM_TEXT);
        $setting->write_setting($text);
        $testtrimtext = get_config('tool_ally', 'testtrim');
        $this->assertEquals(trim($text), $testtrimtext);
    }
}
