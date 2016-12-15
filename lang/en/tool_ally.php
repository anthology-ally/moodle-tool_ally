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
 * Language definitions.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2016 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['adminurl'] = 'Launch URL';
$string['adminurldesc'] = 'The LTI launch URL used to access the Accessibility report.';
$string['contentauthors'] = 'Content authors';
$string['contentauthorsdesc'] = 'Administrators and users assigned to these selected roles will have their uploaded course files evaluated for accessibility. The files are given an accessibility rating. Low ratings mean that the file needs changes to be more accessible.';
$string['curlerror'] = 'cURL error: {$a}';
$string['curlinvalidhttpcode'] = 'Invalid HTTP status code: {$a}';
$string['curlnohttpcode'] = 'Unable to verify HTTP status code';
$string['filecoursenotfound'] = 'The passed in file does not belong to any course';
$string['fileupdatestask'] = 'Push file updates to Ally';
$string['key'] = 'Key';
$string['keydesc'] = 'The LTI consumer key.';
$string['pluginname'] = 'Ally';
$string['pushurl'] = 'File updates URL';
$string['pushurldesc'] = 'Push notifications about file updates to this URL.';
$string['queuesendmessagesfailure'] = 'An error occurred while sending messages to the AWS SQS. Error data: $a';
$string['secret'] = 'Secret';
$string['secretdesc'] = 'The LTI secret.';
