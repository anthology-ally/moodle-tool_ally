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
 * Test for loggable_external_api.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2019 Open LMS (https://www.openlms.net) / 2023 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_ally;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/abstract_testcase.php');

use tool_ally\abstract_testcase;
use tool_ally\logging\constants;
use tool_ally\logging\logger;
use tool_ally\webservice\log;
use tool_ally\webservice\version_info;
use Psr\Log\LogLevel;

defined('MOODLE_INTERNAL') || die();

/**
 * Test for loggable_external_api.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2019 Open LMS (https://www.openlms.net) / 2023 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group     tool_ally
 * @group     ally
 * @runTestsInSeparateProcesses
 */
final class loggable_external_api_test extends abstract_testcase {
    protected function setUp(): void {
        parent::setUp();
        global $CFG;
        require_once($CFG->dirroot . '/lib/externallib.php');
        // Log all log levels.
        set_config('logrange', constants::RANGE_ALL, 'tool_ally');
        logger::get()->setlevelrange(constants::RANGE_ALL);
    }

    /**
     * Test that service version failure is logged.
     *
     * @covers \tool_ally\webservice\version_info::service
     * @covers \tool_ally\webservice\log::service
     */
    public function test_service_version_failure_logged(): void {
        $this->resetAfterTest();

        set_config('sitepolicy', 'sitepolicyURL.com');
        set_config('sitepolicyguest', 'sitepolicyURLguest.com');

        try {
            version_info::service();
        } catch (\Exception $e) {
            $this->setAdminUser();
            $logentries = log::service(null);
            $this->assertCount(1, $logentries['data']);
            $this->assertEquals('logger:servicefailure', $logentries['data'][0]->code);
            $this->assertEquals(LogLevel::ERROR, $logentries['data'][0]->level);
            $this->assertEquals(get_string('logger:servicefailure_exp', 'tool_ally', (object)[
                'class' => version_info::class,
                'params' => var_export([], true),
            ]), $logentries['data'][0]->explanation);
        }
    }

    public function test_log_service_normalises_query_sorting(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var \tool_ally_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_ally');
        $generator->create_log_entry(['time' => time() - 1]);
        $generator->create_log_entry(['time' => time()]);

        $results = log::service(json_encode([
            'limit' => 20,
            'offset' => 0,
            'sort' => 'id DESC, (SELECT 1)',
            'order' => null,
        ]));

        $this->assertNull($results['query']->sort);
        $this->assertNull($results['query']->order);

        $results = log::service(json_encode([
            'limit' => 20,
            'offset' => 0,
            'sort' => 'id',
            'order' => null,
        ]));

        $this->assertSame('id', $results['query']->sort);
        $this->assertSame('DESC', $results['query']->order);

        $results = log::service(json_encode([
            'limit' => 20,
            'offset' => 0,
            'sort' => 'level',
            'order' => 'ASC',
        ]));

        $this->assertSame('level', $results['query']->sort);
        $this->assertSame('ASC', $results['query']->order);
    }

    public function test_content_queue_log_excludes_rich_content(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('push_cli_only', 1, 'tool_ally');
        content_processor::get_config(true);

        $content = new \tool_ally\models\component_content(
            42,
            'mod_page',
            'page',
            'content',
            7,
            time(),
            FORMAT_HTML,
            '<img src=x onerror=alert(1)>'
        );
        content_processor::push_content_update($content, 'updated');

        $logentry = $DB->get_record('tool_ally_log', ['code' => 'logger:addingconenttoqueue'], '*', MUST_EXIST);
        $logcontext = unserialize($logentry->data);

        $this->assertSame([
            [
                'id' => 42,
                'component' => 'mod_page',
                'table' => 'page',
                'field' => 'content',
                'courseid' => 7,
            ],
        ], $logcontext['content']);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $logentry->data);
    }
}
