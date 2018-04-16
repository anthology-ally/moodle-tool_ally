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
 * File processor for Ally.
 * @package   tool_ally
 * @copyright Copyright (c) 2018 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_ally;

use core\event\base;

use core\event\course_created;
use core\event\course_updated;
use core\event\course_deleted;

use core\event\course_module_created;
use core\event\course_module_updated;
use core\event\course_module_deleted;

use core\event\course_section_created;
use core\event\course_section_updated;
use core\event\course_section_deleted;

use mod_forum\event\discussion_created;
use mod_forum\event\discussion_updated;
use mod_forum\event\discussion_deleted;
use mod_forum\event\post_updated;

use mod_hsuforum\event\discussion_created as hsu_discussion_created;
use mod_hsuforum\event\discussion_updated as hsu_discussion_updated;
use mod_hsuforum\event\discussion_deleted as hsu_discussion_deleted;
use mod_hsuforum\event\post_updated as hsu_post_updated;
use mod_hsuforum\event\post_updated as hsu_post_created;

use tool_ally\models\component_content;

defined('MOODLE_INTERNAL') || die();

/**
 * File processor for Ally.
 * Can be used to process individual or groups of files.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2018 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class event_handlers {

    const API_CREATED = 'rich_content_created';
    const API_UPDATED = 'rich_content_updated';
    const API_DELETED = 'rich_content_deleted';

    /**
     * @param course_created $event
     */
    public static function course_created(course_created $event) {
        $courseid = $event->courseid;
        $contents = local_content::get_all_html_content($courseid, 'course');
        content_processor::push_content_update($contents, self::API_CREATED);
    }

    /**
     * @param course_updated $event
     */
    public static function course_updated(course_updated $event) {
        $courseid = $event->courseid;
        $contents = local_content::get_all_html_content($courseid, 'course');
        content_processor::push_content_update($contents, self::API_UPDATED);
    }

    /**
     * @param int $courseid
     * @param string $component
     * @param base $event
     * @throws \dml_exception
     */
    private static function queue_delete($courseid, $id, $component, $table, $field, base $event) {
        global $DB;

        $DB->insert_record_raw('tool_ally_deleted_content', [
            'instanceid'        => $id,
            'courseid'     => $courseid,
            'component'    => $component,
            'comptable'        => $table,
            'field'        => $field,
            'timedeleted'  => $event->timecreated,
        ], false);
    }

    /**
     * @param course_deleted $event
     */
    public static function course_deleted(course_deleted $event) {
        $courseid = $event->courseid;
        self::queue_delete($courseid, $courseid, 'course', 'course', 'summary', $event);
    }

    /**
     * @param base $event
     * @param string $apieventname
     * @throws \dml_exception
     */
    private static function course_section_crud(base $event, $apieventname) {
        global $DB;

        $sectionid = $event->objectid;
        $courseid = $event->courseid;
        $content = '';

        if (!$event instanceof course_section_deleted) {
            $section = $DB->get_record('course_sections', ['id' => $sectionid]);
            $content = $section->summary;
        } else {
            self::queue_delete($courseid, $sectionid, 'course', 'course_sections', 'summary', $event);
            return;
        }

        $content = new component_content(
                $sectionid, 'course', 'course_sections', 'summary', $courseid, $event->timecreated,
                $section->summaryformat, $content);
        content_processor::push_content_update([$content], $apieventname);
    }

    /**
     * @param course_section_created $event
     */
    public static function course_section_created(course_section_created $event) {
        self::course_section_crud($event, self::API_CREATED);
    }

    /**
     * @param course_section_updated $event
     * @throws \dml_exception
     */
    public static function course_section_updated(course_section_updated $event) {
        self::course_section_crud($event, self::API_UPDATED);
    }

    /**
     * @param course_section_deleted $event
     * @throws \dml_exception
     */
    public static function course_section_deleted(course_section_deleted $event) {
        self::course_section_crud($event, self::API_DELETED);
    }

    /**
     * @param base $event
     * @param $apieventname
     */
    private static function course_module_crud(base $event, $apieventname) {
        $module = $event->other['modulename'];
        $id = $event->other['instanceid'];
        $contents = local_content::get_all_html_content($id, $module);
        if (empty($contents)) {
            return;
        }
        content_processor::push_content_update($contents, $apieventname);
    }

    /**
     * @param course_module_created $event
     */
    public static function course_module_created(course_module_created $event) {
        self::course_module_crud($event, self::API_CREATED);
    }

    /**
     * @param course_module_updated $event
     */
    public static function course_module_updated(course_module_updated $event) {
        self::course_module_crud($event, self::API_UPDATED);
    }

    /**
     * @param course_module_deleted $event
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function course_module_deleted(course_module_deleted $event) {
        $module = $event->other['modulename'];
        $id = $event->other['instanceid'];

        if (!local_content::component_supports_html_content($module)) {
            return;
        }

        self::queue_delete($event->courseid, $id, $module, $module, 'intro', $event);
    }

    /**
     * @param base $event
     * @param string $eventname
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    private static function forum_discussion_crud(base $event, $eventname, $forumtype = 'forum') {
        $module = $forumtype;
        $component = local_content::component_instance($module);
        $userid = $event->userid;
        // Don't go any further if user is not a teacher / manager / admin, etc..
        if (!$component->user_is_approved_author_type($userid, $event->get_context())) {
            return;
        }

        // Get the forum post id from the discussion without hitting the DB!
        $recordsnapshot = $event->get_record_snapshot($forumtype.'_discussions', $event->objectid);
        $postid = $recordsnapshot->firstpost;

        $table = $forumtype.'_posts';
        if ($event instanceof discussion_deleted || $event instanceof hsu_discussion_deleted) {
            $content = local_content::get_html_content_deleted($postid, $module, $table, 'message', $event->courseid);
        } else {
            $content = local_content::get_html_content($postid, $module, $table, 'message', $event->courseid);
        }
        if (!$content) {
            $a = (object) [
                'component' => $module,
                'id' => $postid
            ];
            throw new \moodle_exception('error:componentcontentnotfound', 'tool_ally', '', $a);
        }
        content_processor::push_content_update([$content], $eventname);
    }

    /**
     * @param discussion_created $event
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public static function forum_discussion_created(discussion_created $event, $forumtype = 'forum') {
        self::forum_discussion_crud($event, self::API_CREATED, $forumtype);
    }

    /**
     * @param discussion_updated $event
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public static function forum_discussion_updated(discussion_updated $event, $forumtype = 'forum') {
        self::forum_discussion_crud($event, self::API_UPDATED, $forumtype);
    }

    /**
     * @param discussion_deleted $event
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public static function forum_discussion_deleted(discussion_deleted $event, $forumtype = 'forum') {
        self::forum_discussion_crud($event, self::API_DELETED, $forumtype);
    }

    /**
     * Note - although we are only interested in discussions, if we alter a discussions message we are in fact altering
     * the corersponding post.
     * @param post_updated $event
     */
    public static function forum_post_updated(base $event, $forumtype = 'forum') {
        $module = $forumtype;
        $component = local_content::component_instance($module);
        $userid = $event->userid;
        // Don't go any further if user is not a teacher / manager / admin, etc..
        if (!$component->user_is_approved_author_type($userid, $event->get_context())) {
            return;
        }
        $discussionid = $event->other['discussionid'];
        $postid = $event->objectid;
        $table = $forumtype.'_posts';

        $recordsnapshot = $event->get_record_snapshot($forumtype.'_discussions', $discussionid);
        if (intval($recordsnapshot->firstpost) === intval($postid)) {
            // This is a discussion post, let's go!
            $content = local_content::get_html_content($postid, $module, $table, 'message', $event->courseid);
            if (!$content) {
                $a = (object) [
                    'component' => $module,
                    'id' => $postid
                ];
                throw new \moodle_exception('error:componentcontentnotfound', 'tool_ally', '', $a);
            }
            content_processor::push_content_update([$content], self::API_UPDATED);
        }
    }

    /**
     * @param discussion_created $event
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public static function hsuforum_discussion_created(hsu_discussion_created $event) {
        self::forum_discussion_crud($event, self::API_CREATED, 'hsuforum');
    }

    /**
     * @param discussion_updated $event
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public static function hsuforum_discussion_updated(hsu_discussion_updated $event) {
        self::forum_discussion_crud($event, self::API_UPDATED, 'hsuforum');
    }

    /**
     * @param discussion_deleted $event
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public static function hsuforum_discussion_deleted(hsu_discussion_deleted $event) {
        self::forum_discussion_crud($event, self::API_DELETED, 'hsuforum');
    }

    /**
     * Note - although we are only interested in discussions, if we alter a discussions message we are in fact altering
     * the corersponding post.
     * @param post_updated $event
     */
    public static function hsuforum_post_updated(hsu_post_updated $event) {
        self::forum_post_updated($event, 'hsuforum');
    }
}