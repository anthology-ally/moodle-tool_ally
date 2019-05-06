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
 * Test for file webservice.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_ally\webservice\file;
use tool_ally\local;
use tool_ally\auto_config;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/abstract_testcase.php');

/**
 * Test for file webservice.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_ally_webservice_file_testcase extends tool_ally_abstract_testcase {

    /**
     * Test the web service when used to get a resource file.
     */
    public function test_service() {
        global $CFG;

        $this->resetAfterTest();

        // Test method successful when configured.
        $ac = new auto_config();
        $ac->configure();

        $roleid = $this->assignUserCapability('moodle/course:view', context_system::instance()->id);
        $this->assignUserCapability('moodle/course:viewhiddencourses', context_system::instance()->id, $roleid);

        $course       = $this->getDataGenerator()->create_course();
        $resource     = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
        $expectedfile = $this->get_resource_file($resource);

        $file = file::service($expectedfile->get_pathnamehash());
        $file = external_api::clean_returnvalue(file::service_returns(), $file);

        $timemodified = local::iso_8601_to_timestamp($file['timemodified']);

        $this->assertNotEmpty($file);
        $this->assertEquals($expectedfile->get_pathnamehash(), $file['id']);
        $this->assertEquals($course->id, $file['courseid']);
        $this->assertEquals($expectedfile->get_userid(), $file['userid']);
        $this->assertEquals($expectedfile->get_filename(), $file['name']);
        $this->assertEquals($expectedfile->get_mimetype(), $file['mimetype']);
        $this->assertEquals($expectedfile->get_contenthash(), $file['contenthash']);
        $this->assertEquals($expectedfile->get_timemodified(), $timemodified);
        $this->assertRegExp('/.*pluginfile\.php.*mod_resource.*/', $file['url']);
        $this->assertRegExp('/.*admin\/tool\/ally\/wspluginfile\.php\?pathnamehash=/', $file['downloadurl']);
        $this->assertEquals($CFG->wwwroot.'/mod/resource/view.php?id='.$resource->cmid, $file['location']);
    }

    /**
     * Test the web service when used to get a forum post attachment.
     */
    public function test_forum_post() {
        global $CFG;

        $this->resetAfterTest();

        // Test method successful when configured.
        $ac = new auto_config();
        $ac->configure();

        $roleid = $this->assignUserCapability('moodle/course:view', context_system::instance()->id);
        $this->assignUserCapability('moodle/course:viewhiddencourses', context_system::instance()->id, $roleid);

        $course       = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id);
        $forum = $this->getDataGenerator()->create_module('forum', $options);
        $forumcontext = context_module::instance($forum->cmid);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion($record);

        // Add a post with an attachment.
        $record = new stdClass();
        $record->discussion = $discussion->id;
        $record->userid = $user->id;
        $post = self::getDataGenerator()->get_plugin_generator('mod_forum')->create_post($record);
        $filename = 'shouldbeanimage.jpg';
        $filecontents = 'image contents (not really)';
        // Add a fake inline image to the post.
        $filerecordinline = array(
            'contextid' => $forumcontext->id,
            'component' => 'mod_forum',
            'filearea'  => 'post',
            'itemid'    => $post->id,
            'filepath'  => '/',
            'filename'  => $filename,
        );
        $fs = get_file_storage();
        $expectedfile = $fs->create_file_from_string($filerecordinline, $filecontents);

        $file = file::service($expectedfile->get_pathnamehash());
        $file = external_api::clean_returnvalue(file::service_returns(), $file);

        $timemodified = local::iso_8601_to_timestamp($file['timemodified']);

        $this->assertNotEmpty($file);
        $this->assertEquals($expectedfile->get_pathnamehash(), $file['id']);
        $this->assertEquals($course->id, $file['courseid']);
        $this->assertEquals($expectedfile->get_userid(), $file['userid']);
        $this->assertEquals($expectedfile->get_filename(), $file['name']);
        $this->assertEquals($expectedfile->get_mimetype(), $file['mimetype']);
        $this->assertEquals($expectedfile->get_contenthash(), $file['contenthash']);
        $this->assertEquals($expectedfile->get_timemodified(), $timemodified);
        $this->assertRegExp('/.*pluginfile\.php.*mod_forum.*/', $file['url']);
        $this->assertRegExp('/.*admin\/tool\/ally\/wspluginfile\.php\?pathnamehash=/', $file['downloadurl']);
        $this->assertEquals($CFG->wwwroot.'/mod/forum/discuss.php?d='.$discussion->id.'#p'.$post->id, $file['location']);
    }

    /**
     * Test the web service when used to get a forum main page attachment.
     */
    public function test_forum_main_page() {
        global $CFG;

        $this->resetAfterTest();

        // Test method successful when configured.
        $ac = new auto_config();
        $ac->configure();

        $roleid = $this->assignUserCapability('moodle/course:view', context_system::instance()->id);
        $this->assignUserCapability('moodle/course:viewhiddencourses', context_system::instance()->id, $roleid);

        $course       = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id);
        $forum = $this->getDataGenerator()->create_module('forum', $options);
        $forumcontext = context_module::instance($forum->cmid);

        // Add a post with an attachment.
        $filename = 'shouldbeanimage.jpg';
        $filecontents = 'image contents (not really)';
        // Add a fake inline image to the forum.
        $filerecordinline = array(
            'contextid' => $forumcontext->id,
            'component' => 'mod_forum',
            'filearea'  => 'intro',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => $filename,
        );
        $fs = get_file_storage();
        $expectedfile = $fs->create_file_from_string($filerecordinline, $filecontents);

        $file = file::service($expectedfile->get_pathnamehash());
        $file = external_api::clean_returnvalue(file::service_returns(), $file);

        $timemodified = local::iso_8601_to_timestamp($file['timemodified']);

        $this->assertNotEmpty($file);
        $this->assertEquals($expectedfile->get_pathnamehash(), $file['id']);
        $this->assertEquals($course->id, $file['courseid']);
        $this->assertEquals($expectedfile->get_userid(), $file['userid']);
        $this->assertEquals($expectedfile->get_filename(), $file['name']);
        $this->assertEquals($expectedfile->get_mimetype(), $file['mimetype']);
        $this->assertEquals($expectedfile->get_contenthash(), $file['contenthash']);
        $this->assertEquals($expectedfile->get_timemodified(), $timemodified);
        $this->assertRegExp('/.*pluginfile\.php.*mod_forum.*/', $file['url']);
        $this->assertRegExp('/.*admin\/tool\/ally\/wspluginfile\.php\?pathnamehash=/', $file['downloadurl']);
        $this->assertEquals($CFG->wwwroot.'/mod/forum/view.php?id='.$forum->cmid, $file['location']);
    }

    public function test_unwhitelisted_file_component() {

        $this->resetAfterTest();

        // Test method successful when configured.
        $ac = new auto_config();
        $ac->configure();

        $roleid = $this->assignUserCapability('moodle/course:view', context_system::instance()->id);
        $this->assignUserCapability('moodle/course:viewhiddencourses', context_system::instance()->id, $roleid);

        $filename = 'somefile.txt';
        $filecontents = 'contents of file';

        $filerecord = array(
            'contextid' => context_system::instance()->id,
            'component' => 'mod_somefakemodule',
            'filearea'  => 'intro',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => $filename,
        );
        $fs = get_file_storage();
        $file = $fs->create_file_from_string($filerecord, $filecontents);

        $this->expectExceptionMessage(get_string('filenotfound', 'error'));
        file::service($file->get_pathnamehash());
    }
}
