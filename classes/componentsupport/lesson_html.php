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
 * Html file replacement support for core lessons.
 * @package tool_ally
 * @author    David Castro <david.castro@blackboard.com>
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally\componentsupport;

defined ('MOODLE_INTERNAL') || die();

use tool_ally\local_file;

/**
 * Class lesson_html.
 * Html file replacement support for core lessons.
 * @package tool_ally
 * @author    David Castro <david.castro@blackboard.com>
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lesson_html extends html_base {

    public static function component_type() {
        return self::TYPE_MOD;
    }

    public function replace_file_links() {
        $file = $this->file;

        $area = $file->get_filearea();
        $itemid = $file->get_itemid();

        if ($area === 'page_contents') {
            local_file::update_filenames_in_html('contents', 'lesson_pages', ' id = ? ',
                ['id' => $itemid], $this->oldfilename, $file->get_filename());
        }
    }
}
