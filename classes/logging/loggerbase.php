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
defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../../vendor/autoload.php');
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
/**
 * Define base logging class.
 *
 * @package   tool_logger
 * @copyright Copyright (c) 2018 Open LMS (https://www.openlms.net) / 2023 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class loggerbase implements LoggerInterface {
    /**
     * @var int
     */
    protected $logrange;
    /**
     * Constructor.
     */
    public function __construct() {
        $this->setlevelrange();
    }
    /**
     * Set level range for debugging.
     */
    public function setlevelrange() {
        $confrange = get_config('tool_ally', 'logrange');
        $range = $confrange === false ? constants::RANGE_ALL : $confrange;
        $this->logrange = intval($range);
    }
    /**
     * System is unusable.
     *
     * @param mixed[] $context
     */
    public function emergency(string|\Stringable $message, array $context = []): void {
        // Only log if range is light or greater (Emergency|Alert|Critical).
        if ($this->logrange >= constants::RANGE_LIGHT) {
            $this->log(LogLevel::EMERGENCY, $message, $context);
        }
    }
    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param mixed[] $context
     */
    public function alert(string|\Stringable $message, array $context = []): void {
        // Only log if range is light or greater (Emergency|Alert|Critical).
        if ($this->logrange >= constants::RANGE_LIGHT) {
            $this->log(LogLevel::ALERT, $message, $context);
        }
    }
    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param mixed[] $context
     */
    public function critical(string|\Stringable $message, array $context = []): void {
        // Only log if range is light or greater (Emergency|Alert|Critical).
        if ($this->logrange >= constants::RANGE_LIGHT) {
            $this->log(LogLevel::CRITICAL, $message, $context);
        }
    }
    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param mixed[] $context
     */
    public function error(string|\Stringable $message, array $context = []): void {
        // Only log if range is medium or greater (Emergency|Alert|Critical|Error|Warning).
        if ($this->logrange >= constants::RANGE_MEDIUM) {
            $this->log(LogLevel::ERROR, $message, $context);
        }
    }
    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param mixed[] $context
     */
    public function warning(string|\Stringable $message, array $context = []): void {
        // Only log if range is medium (Emergency|Alert|Critical|Error|Warning).
        if ($this->logrange >= constants::RANGE_MEDIUM) {
            $this->log(LogLevel::WARNING, $message, $context);
        }
    }
    /**
     * Normal but significant events.
     *
     * @param mixed[] $context
     */
    public function notice(string|\Stringable $message, array $context = []): void {
        // Only log if range is all - every possible type of log.
        if ($this->logrange >= constants::RANGE_ALL) {
            $this->log(LogLevel::NOTICE, $message, $context);
        }
    }
    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param mixed[] $context
     */
    public function info(string|\Stringable $message, array $context = []): void {
        // Only log if range is all - every possible type of log.
        if ($this->logrange >= constants::RANGE_ALL) {
            $this->log(LogLevel::INFO, $message, $context);
        }
    }
    /**
     * Detailed debug information.
     *
     * @param mixed[] $context
     */
    public function debug(string|\Stringable $message, array $context = []): void {
        // Only log if range is all - every possible type of log.
        if ($this->logrange >= constants::RANGE_ALL) {
            $this->log(LogLevel::DEBUG, $message, $context);
        }
    }
    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param mixed[] $context
     */
    abstract public function log($level, string|\Stringable $message, array $context = []): void;
}
