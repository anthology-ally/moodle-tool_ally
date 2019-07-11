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
 * Abstract class for logging erroneous service consumption.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2019 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally\webservice;

defined('MOODLE_INTERNAL') || die();

use external_api;
use Exception;
use tool_ally\logging\logger;

require_once(__DIR__.'/../../../../../lib/externallib.php');

/**
 * Abstract class for logging erroneous service consumption.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2019 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class loggable_external_api extends external_api {
    /**
     * @throws Exception
     */
    public static function service() {
        $params = func_get_args();
        $classname = static::class;
        try {
            return call_user_func_array([$classname, 'execute_service'], $params);
        } catch (Exception $ex) {
            // Catching and releasing exception.
            $logstr = 'logger:servicefailure';
            $msg = get_string($logstr . '_exp', 'tool_ally', (object)[
                'class' => $classname,
                'params' => var_export($params, true)
            ]);
            logger::get()->error($logstr, [
                '_explanation' => $msg,
                '_exception' => $ex
            ]);
            throw $ex;
        }
    }
}
