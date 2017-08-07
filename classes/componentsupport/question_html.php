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
 * Html file replacement support for core questions
 * @package tool_ally
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally\componentsupport;

defined ('MOODLE_INTERNAL') || die();

use tool_ally\local_file;

require_once($CFG->dirroot.'/question/engine/bank.php');

/**
 * Html file replacement support for core questions
 * @package tool_ally
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_html extends html_base {

    public static function component_type() {
        return self::TYPE_CORE;
    }

    /**
     * Get question record.
     * @param $id
     * @return mixed
     */
    private function get_question($id) {
        global $DB;
        return $DB->get_record('question', ['id' => $id]);
    }

    public function replace_file_links() {

        $file = $this->file;

        $area = $file->get_filearea();
        $itemid = $file->get_itemid();

        // Correct, incorrect, partially correct feedback areas.
        $inorcorrectfbareas = [
            'correctfeedback',
            'partiallycorrectfeedback',
            'incorrectfeedback'
        ];

        $idfield = null;
        $table = null;
        $field = $area;

        if ($area === 'questiontext' || $area === 'generalfeedback') {
            $table = 'question';
            $idfield = 'id';
        } else if ($area === 'answer' || $area === 'answerfeedback') {
            $table = 'question_answers';
            $idfield = 'id';
            $field = $area === 'answer' ? 'answer' : 'feedback';
        } else if (in_array($area, $inorcorrectfbareas)) {
            $question = $this->get_question($itemid);
            $qtype = $question->qtype;
            $idfield = 'questionid';

            switch ($qtype) {
                case 'ddimageortext' :
                    $table = 'qtype_ddimageortext';
                    break;
                case 'ddmarker' :
                    $table = 'qtype_ddmarker';
                    break;
                case 'ddwtos' :
                    $table = 'question_ddwtos';
                    break;
                case 'gapfill' :
                    $table = 'question_gapfill';
                    $idfield = 'question';
                    break;
                case 'gapselect' :
                    $table = 'question_gapselect';
                    break;
                case 'match' :
                    $table = 'qtype_match_options';
                    break;
                case 'multichoice' :
                    $table = 'qtype_multichoice_options';
                    break;
                case 'randomsamatch' :
                    $table = 'qtype_randomsamatch_options';
                    break;
                default :
                    debugging('Question area of '.$area.' and question type '.$qtype.' is not yet supported');
                    return;
            }
        }

        if ($idfield === null || $table === null) {
            // We need this because questions are essentially plugins and new ones will be introduced to our code base
            // as and when customer demand necessitates them.
            debugging('Question area of '.$area.' is not yet supported');
            return;
        }

        local_file::update_filenames_in_html($field, $table, ' '.$idfield.' = ? ',
            [$itemid], $this->oldfilename, $file->get_filename());

        \question_finder::get_instance()->uncache_question($itemid);
    }
}
