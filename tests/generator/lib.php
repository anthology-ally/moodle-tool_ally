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
 * Testing generator.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Testing generator.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_ally_generator extends component_generator_base {
    /**
     * Create a draft file for the current user.
     *
     * Note: The file's item ID is the draft ID.
     *
     * @param array $record Draft file record
     * @param string $content File contents
     * @return stored_file
     */
    public function create_draft_file(array $record = [], $content = 'Test file') {
        global $USER;

        if (empty($USER->username) || $USER->username === 'guest') {
            throw new coding_exception('Requires a current user');
        }

        $defaults = [
            'component' => 'user',
            'filearea'  => 'draft',
            'contextid' => context_user::instance($USER->id)->id,
            'itemid'    => file_get_unused_draft_itemid(),
            'filename'  => 'attachment.html',
            'filepath'  => '/'
        ];

        return get_file_storage()->create_file_from_string($record + $defaults, $content);
    }

    /**
     * Stolen from /Users/guy/Development/www/moodle_test/blocks/tests/privacy_test.php
     * Get the block manager.
     *
     * @param array $regions The regions.
     * @param context $context The context.
     * @param string $pagetype The page type.
     * @param string $subpage The sub page.
     * @return block_manager
     */
    protected function get_block_manager($regions, $context, $pagetype = 'page-type', $subpage = '') {
        global $CFG;
        require_once($CFG->libdir.'/blocklib.php');
        $page = new moodle_page();
        $page->set_context($context);
        $page->set_pagetype($pagetype);
        $page->set_subpage($subpage);
        $page->set_url(new moodle_url('/'));

        $blockmanager = new block_manager($page);
        $blockmanager->add_regions($regions, false);
        $blockmanager->set_default_region($regions[0]);

        return $blockmanager;
    }

    /**
     * Add block to specific context and return instance row.
     * @param context $context
     * @param $title
     * @param $content
     * @param string $region
     * @param string $pagetypepattern
     * @return stdClass
     * @throws coding_exception
     * @throws dml_exception
     */
    public function add_block(context $context,
                              $title, $content,
                              $region = 'side-pre',
                              $pagetypepattern = 'course-view-*') {
        global $DB;

        $bm = $this->get_block_manager([$region], $context);
        $bm->add_block('html', $region, 1, true, $pagetypepattern); // Wow - doesn't return anything useful like say, the block id!
        $blocks = $DB->get_records('block_instances', [], 'id DESC', 'id', 0, 1);
        if (empty($blocks)) {
            throw new coding_exception('Created a block but block instances empty!');
        }
        $block = reset($blocks);
        $blockconfig = (object) [
            'title' => $title,
            'format' => FORMAT_HTML,
            'text' => $content
        ];
        $block->configdata = base64_encode(serialize($blockconfig));
        $DB->update_record('block_instances', $block);
        $block = $DB->get_record('block_instances', ['id' => $block->id]);
        return $block;
    }
}