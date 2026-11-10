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
 * Tests for LTI 1.3 launches without a course module.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2026 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use tool_ally\lti\lti13;

/**
 * Tests for LTI 1.3 launches without a course module.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2026 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[Group('tool_ally')]
#[Group('ally')]
#[CoversClass(lti13::class)]
final class lti13_test extends \advanced_testcase {

    /** @var \stdClass tool_ally config pointing at the Ally host the tools are registered for. */
    private \stdClass $pluginconfig;

    public function setUp(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/lti/locallib.php');
        parent::setUp();
        $this->resetAfterTest();
        $this->pluginconfig = (object) ['adminurl' => 'https://ally.example/api/v1/1/lti/institution'];
    }

    /**
     * Register an Ally app the way dynamic registration does.
     *
     * @param string $app
     * @param int $state
     * @param string $host
     * @return int lti_types id
     */
    private function register(string $app, int $state = LTI_TOOL_STATE_CONFIGURED, string $host = 'ally.example'): int {
        $type = (object) [
            'name' => "Ally {$app}",
            'baseurl' => "https://{$app}.{$host}/api/v2/auth/lti/1.3/target",
            'tooldomain' => "{$app}.{$host}",
            'state' => $state,
            'ltiversion' => LTI_VERSION_1P3,
            'clientid' => random_string(15),
            'coursevisible' => LTI_COURSEVISIBLE_PRECONFIGURED,
            'timemodified' => time(),
        ];
        $config = (object) [
            'lti_initiatelogin' => "https://{$host}/api/v2/auth/lti/1.3/login",
            'lti_redirectionuris' => "https://{$host}/api/v2/auth/lti/1.3/callback",
            'lti_keytype' => LTI_JWK_KEYSET,
            'lti_publickeyset' => "https://{$host}/api/v2/auth/lti/1.3/jwk",
        ];
        return lti_add_type($type, $config);
    }

    public function test_registration_url(): void {
        $this->assertSame('https://ally.example/api/v2/auth/lti/1.3/register/course',
            lti13::registration_url($this->pluginconfig, 'course'));
        $this->assertNull(lti13::registration_url((object) [], 'course'));
    }

    public function test_find_type_matches_only_an_active_tool_for_the_app_and_host(): void {
        $this->assertNull(lti13::find_type($this->pluginconfig, 'config'), 'nothing registered');

        $this->register('config', LTI_TOOL_STATE_PENDING);
        $this->register('config', LTI_TOOL_STATE_CONFIGURED, 'other-ally.example');
        $this->register('course');
        $this->assertNull(lti13::find_type($this->pluginconfig, 'config'), 'pending, other host or other app');

        $active = $this->register('config');
        $this->assertSame($active, lti13::find_type($this->pluginconfig, 'config'));
        $this->assertNull(lti13::find_type((object) [], 'config'), 'no adminurl');
    }

    public function test_app_for_type(): void {
        foreach (lti13::APPS as $app) {
            $config = (object) ['lti_toolurl' => "https://{$app}.ally.example/api/v2/auth/lti/1.3/target"];
            $this->assertSame($app, lti13::app_for_type($config));
        }
        $this->assertNull(lti13::app_for_type((object) ['lti_toolurl' => 'https://tool.example/launch']));
    }

    public function test_instance_uses_the_tool_identity_and_a_string_id(): void {
        $typeid = $this->register('config');
        $instance = lti13::instance($typeid, lti_get_type_type_config($typeid), SITEID);

        $this->assertSame('0', $instance->id);
        $this->assertSame('', $instance->resourcekey);
        $this->assertSame($typeid, $instance->typeid);
        $this->assertSame('https://config.ally.example/api/v2/auth/lti/1.3/target', $instance->toolurl);
    }

    public function test_login_form_marks_the_launch_and_stores_it_without_a_course_module(): void {
        global $SESSION;
        $this->setAdminUser();
        $typeid = $this->register('config');

        $html = lti13::login_form($typeid, SITEID);

        $this->assertStringContainsString('action="https://ally.example/api/v2/auth/lti/1.3/login"', $html);
        $this->assertSame(1, preg_match('/name="lti_message_hint" value="([^"]+)"/', $html, $matches));
        $hint = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);
        $this->assertTrue($hint['ally']);
        $this->assertSame(SITEID . ",{$typeid},,basic-lti-launch-request,0,,", $SESSION->{$hint['launchid']});
    }

    public function test_require_access_checks_each_app_capability(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        $this->setUser($teacher);
        lti13::require_access('course', $course->id);
        foreach (['config', 'institution'] as $app) {
            try {
                lti13::require_access($app, SITEID);
                $this->fail("A teacher must not launch {$app}");
            } catch (\required_capability_exception $e) {
                $this->assertInstanceOf(\required_capability_exception::class, $e);
            }
        }

        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        lti13::require_access('course', $course->id);
    }

    public function test_require_access_rejects_an_unknown_app(): void {
        $this->setAdminUser();
        $this->expectException(\moodle_exception::class);
        lti13::require_access('unknown', SITEID);
    }
}
