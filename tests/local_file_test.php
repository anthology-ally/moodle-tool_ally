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
 * @copyright Copyright (c) 2018 Open LMS (https://www.openlms.net) / 2023 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_ally;

use tool_ally\local_file;
use tool_ally\auto_config;
use advanced_testcase;

/**
 * Tests for local_file library.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2018 Open LMS (https://www.openlms.net) / 2023 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group     tool_ally
 * @group     ally
 * @covers    \tool_ally\local_file
 * @runTestsInSeparateProcesses
 */
final class local_file_test extends advanced_testcase {
    public function test_generate_wspluginfile_signature_invalid_config(): void {
        // Test failure without ally_webuser / valid configuration.
        $expectedmsg = 'Access control exception (Ally web user (ally_webuser) does not exist.';
        $expectedmsg .= ' Has auto configure been run?)';
        $this->expectExceptionMessage($expectedmsg);
        local_file::generate_wspluginfile_signature('fakehash');
    }

    public function test_generate_wspluginfile_signature(): void {
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

    public function test_get_fileurlproperties(): void {
        global $CFG;
        $this->resetAfterTest();

        $samplefilearea = 'assets';
        $samplecomponent = 'tool_themeassets';
        $samplefilename = 'icon.png';
        $samplefilepath = '/Folder 1/';
        $sampleurl = "{$CFG->wwwroot}/pluginfile.php/1/{$samplecomponent}/{$samplefilearea}/0{$samplefilepath}{$samplefilename}";
        $props = local_file::get_fileurlproperties($sampleurl);
        $this->assertInstanceOf('tool_ally\models\pluginfileurlprops', $props);
        $this->assertEquals($samplefilearea, $props->filearea);
        $this->assertEquals($samplecomponent, $props->component);
        $this->assertEquals($samplefilename, basename($props->filename));
        $this->assertEquals($samplefilepath, $props->filepath);
    }

    /**
     * Only elements pointing at the given file should be stripped, everything else has to survive
     * untouched.
     *
     * @dataProvider strip_pluginfile_elements_provider
     * @param string $html
     * @param string $expected
     */
    public function test_strip_pluginfile_elements($html, $expected): void {
        $paths = ['/gd%20logo.png', '/gd logo.png'];

        $this->assertSame($expected, local_file::strip_pluginfile_elements($html, $paths));
    }

    /**
     * Data provider for test_strip_pluginfile_elements.
     *
     * @return array
     */
    public static function strip_pluginfile_elements_provider(): array {
        return [
            'encoded filename' => [
                '<p>a<img src="@@PLUGINFILE@@/gd%20logo.png" alt="" width="100">b</p>',
                '<p>ab</p>',
            ],
            'unencoded filename' => [
                '<p>a<img src="@@PLUGINFILE@@/gd logo.png" />b</p>',
                '<p>ab</p>',
            ],
            'single quoted src' => [
                "<p><img src='@@PLUGINFILE@@/gd%20logo.png'></p>",
                '<p></p>',
            ],
            'image linking to itself' => [
                '<p><a href="@@PLUGINFILE@@/gd%20logo.png"><img src="@@PLUGINFILE@@/gd%20logo.png"></a></p>',
                '<p></p>',
            ],
            'other files untouched' => [
                '<p><img src="@@PLUGINFILE@@/other.png"><img src="@@PLUGINFILE@@/gd%20logo.png"></p>',
                '<p><img src="@@PLUGINFILE@@/other.png"></p>',
            ],
            'same name in another folder untouched' => [
                '<p><img src="@@PLUGINFILE@@/sub/gd%20logo.png"></p>',
                '<p><img src="@@PLUGINFILE@@/sub/gd%20logo.png"></p>',
            ],
            'text link left alone' => [
                '<p><a href="@@PLUGINFILE@@/gd%20logo.png">My logo</a></p>',
                '<p><a href="@@PLUGINFILE@@/gd%20logo.png">My logo</a></p>',
            ],
            'empty content' => [
                '',
                '',
            ],
        ];
    }

    public function test_pluginfile_path_variants(): void {
        $this->resetAfterTest();

        $file = get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'tool_ally',
            'filearea'  => 'unittest',
            'itemid'    => 0,
            'filepath'  => '/sub dir/',
            'filename'  => 'gd logo.png',
        ], 'test');

        $this->assertSame(
            ['/sub%20dir/gd%20logo.png', '/sub dir/gd logo.png'],
            local_file::pluginfile_path_variants($file)
        );
    }
}
