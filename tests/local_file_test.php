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
 * Tests for local_file library.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_ally\local_file;
use tool_ally\auto_config;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for local_file library.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_ally_local_file_testcase extends advanced_testcase {


    public function test_generate_wspluginfile_signature_invalid_config() {
        // Test failure without ally_webuser / valid configuration.
        $expectedmsg = 'Access control exception (Ally web user (ally_webuser) does not exist.';
        $expectedmsg .= ' Has auto configure been run?)';
        $this->expectExceptionMessage($expectedmsg);
        local_file::generate_wspluginfile_signature('fakehash');
    }

    public function test_generate_wspluginfile_signature() {
        $this->resetAfterTest();
        // Test method successful when configured.
        $ac = new auto_config();
        $ac->configure();
        $fakehash = 'fakehash'; // Not a hash - just for testing.
        $iat = time();
        $signature = local_file::generate_wspluginfile_signature($fakehash, $iat);
        $this->assertEquals($fakehash, $signature->pathnamehash);
        // Check iat is fresh. 5 second buffer for checking iat.
        $this->assertEquals($iat, $signature->iat);
        $this->assertNotEmpty($signature->signature);
    }
}