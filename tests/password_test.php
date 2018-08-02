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
 * Password generator tests.
 * @author    Guy Thomas <citricity@gmail.com>
 * @copyright Copyright (c) 2017 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_ally\password;

defined('MOODLE_INTERNAL') || die();

/**
 * Password generator tests.
 * @author    Guy Thomas <citricity@gmail.com>
 * @copyright Copyright (c) 2017 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_ally_password_test extends advanced_testcase {

    /**
     * @var string
     */
    protected $password;

    protected function setUp() {
        $this->password = new password();
    }

    public function test_password_consecutives() {
        global $CFG;
        $this->resetAfterTest();
        $CFG->maxconsecutiveidentchars = 1;
        for ($v = 0; $v < 100; $v++) { // 100 iterations should be enough to trap a random consecutive.
            $password = strval(new password());
            $chars = str_split($password);
            $prevchar = '';
            for ($c = 0; $c < count($chars); $c++) {
                $char = $chars[$c];
                $this->assertNotEquals($char, $prevchar, 'Concurrent strings found in '.$password);
                $prevchar = $char;
            }
        }
    }
}
