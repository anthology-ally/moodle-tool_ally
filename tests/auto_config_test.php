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
 * Test auto configuration class.
 * @author    Guy Thomas <citricity@gmail.com>
 * @copyright Copyright (c) 2017 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_ally\auto_config;

defined('MOODLE_INTERNAL') || die();

class tool_ally_auto_config_test extends advanced_testcase {
    public function test_auto_config() {
        $this->resetAfterTest();

        $ac = new auto_config();
        $ac->configure();

        $this->assertNotEmpty($ac->token);
        $this->assertNotEmpty($ac->user);
        $this->assertNotEmpty($ac->role);
    }

    public function test_auto_config_update_user() {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_user(['username' => 'ally_webuser']);

        $ac = new auto_config();
        $ac->configure();

        $this->assertDebuggingNotCalled();
    }
}
