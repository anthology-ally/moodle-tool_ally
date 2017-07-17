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
 * Base class for processing module html.
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally\componentsupport;

defined ('MOODLE_INTERNAL') || die();

/**
 * Base class for processing module html.
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

abstract class html_base {

    const TYPE_CORE = 'core';

    const TYPE_MOD = 'mod';

    /**
     * @var string
     */
    protected $oldfilename;

    /**
     * @var \stored_file
     */
    protected $file;

    /**
     * @param \stored_file $file
     * @throws \coding_exception
     */
    private function validate_file_component(\stored_file $file) {
        $class = get_class($this);
        $namespacedel = strrpos($class, '\\');
        if ($namespacedel !== false ) {
            $class = substr($class, $namespacedel + 1);
        }
        if ($this->component_type() === self::TYPE_MOD) {
            $modcheck = 'mod_';
        } else {
            $modcheck = '';
        }
        $modcheck .= substr($class, 0, strrpos($class, '_'));
        if ($modcheck !== $file->get_component()) {
            throw new \coding_exception('Using incorrect module support class ('.$class.') for file with component '.
                    $file->get_component());
        }
    }

    /**
     * Replace file links.
     */
    abstract public function replace_file_links();

    /**
     * Return component type for this component - a class constant beginning with TYPE_
     *
     * @return int
     */
    abstract public static function component_type();

    /**
     * Method for replacing file links within html fields.
     *
     * @param string $oldfilename
     * @param \stored_file $file
     * @return void
     */
    public function __construct($oldfilename, \stored_file $file) {
        $this->oldfilename = $oldfilename;
        $this->file = $file;
        $this->validate_file_component($file);
    }
}
