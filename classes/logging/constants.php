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

namespace tool_ally\logging;

/**
 * Define logging constants.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2018 Open LMS (https://www.openlms.net) / 2023 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class constants {
    /**@var int No range */
    const RANGE_NONE = 0;
    /**@var int Light range */
    const RANGE_LIGHT = 1;
    /**@var int Medium range */
    const RANGE_MEDIUM = 2;
    /**@var int All range */
    const RANGE_ALL = 3;
    /**@var int Emergency severity */
    const SEV_EMERGENCY = 1000;
    /**@var int Alert severity */
    const SEV_ALERT = 1001;
    /**@var int Critical severity */
    const SEV_CRITICAL = 1002;
    /**@var int Error severity */
    const SEV_ERROR = 1003;
    /**@var int Warning severity */
    const SEV_WARNING = 1004;
    /**@var int Notice severity */
    const SEV_NOTICE = 1005;
    /**@var int Info severity */
    const SEV_INFO = 1006;
    /**@var int Debug severity */
    const SEV_DEBUG = 1007;
}
