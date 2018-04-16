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
 * Html file replacement support for glossary.
 * @package tool_ally
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally\componentsupport;

defined ('MOODLE_INTERNAL') || die();

use tool_ally\local_file;

/**
 * Html file replacement support for glossary.
 * @package tool_ally
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class glossary_component extends file_component_base {

    public static function component_type() {
        return self::TYPE_MOD;
    }

    public function replace_file_links() {

        $file = $this->file;

        $area = $file->get_filearea();
        if ($area !== 'entry') {
            debugging('Glossary area of '.$area.' is not yet supported');
            return;
        }

        $itemid = $file->get_itemid();
        $table = 'glossary_entries';
        $idfield = 'id';
        $repfield = 'definition';

        local_file::update_filenames_in_html($repfield, $table, ' id = ? ',
            [$idfield => $itemid], $this->oldfilename, $file->get_filename());
    }
}
