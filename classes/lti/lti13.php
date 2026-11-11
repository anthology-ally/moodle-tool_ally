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
 * LTI 1.3 launches for Ally links that have no course module.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2026 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally\lti;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/lti/locallib.php');

/**
 * LTI 1.3 launches for Ally links that have no course module.
 *
 * Each Ally app (config, institution report, course report) is one tool an admin registers through LTI Advantage
 * dynamic registration. A link launches over LTI 1.3 when its app is registered and active, and over LTI 1.1
 * otherwise.
 *
 * Core's mod/lti/auth.php cannot rebuild a launch without a course module, so the login request is marked with
 * "ally": true in lti_message_hint; Ally then sends the authentication request to this plugin's lti/auth.php.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2026 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lti13 {

    /** @var string[] Ally apps; each is one registered tool, named by its target link URI host prefix. */
    const APPS = ['config', 'course', 'institution'];

    /**
     * The URL an admin pastes into Manage tools > Add LTI Advantage to register an Ally app.
     *
     * @param \stdClass $pluginconfig tool_ally config; the Ally host is taken from adminurl.
     * @param string $app One of self::APPS.
     * @return string|null Null when adminurl is not set.
     */
    public static function registration_url(\stdClass $pluginconfig, string $app): ?string {
        $host = self::ally_host($pluginconfig);
        return $host ? "https://{$host}/api/v2/auth/lti/1.3/register/{$app}" : null;
    }

    /**
     * Find the registered, active LTI 1.3 tool type for an Ally app.
     *
     * Only tools for the Ally host in adminurl match, so a tool registered against another Ally environment is
     * never used.
     *
     * @param \stdClass $pluginconfig tool_ally config.
     * @param string $app One of self::APPS.
     * @return int|null The lti_types id, or null when the app is not registered.
     */
    public static function find_type(\stdClass $pluginconfig, string $app): ?int {
        global $DB;

        $host = self::ally_host($pluginconfig);
        if (!$host) {
            return null;
        }
        $sql = 'SELECT id FROM {lti_types}
                 WHERE ' . $DB->sql_compare_text('baseurl') . ' = ' . $DB->sql_compare_text(':baseurl') . '
                   AND ltiversion = :ltiversion AND state = :state
              ORDER BY id';
        $ids = $DB->get_fieldset_sql($sql, [
            'baseurl' => "https://{$app}.{$host}/api/v2/auth/lti/1.3/target",
            'ltiversion' => LTI_VERSION_1P3,
            'state' => LTI_TOOL_STATE_CONFIGURED,
        ]);
        return $ids ? (int) reset($ids) : null;
    }

    /**
     * The Ally app a registered tool serves.
     *
     * @param \stdClass $typeconfig From lti_get_type_type_config().
     * @return string|null One of self::APPS, or null for a tool that is not an Ally app.
     */
    public static function app_for_type(\stdClass $typeconfig): ?string {
        $host = parse_url($typeconfig->lti_toolurl ?? '', PHP_URL_HOST);
        $app = $host ? explode('.', $host)[0] : null;
        return in_array($app, self::APPS, true) ? $app : null;
    }

    /**
     * Require the access the matching LTI 1.1 link requires, before Moodle signs a launch for the user.
     *
     * @param string $app One of self::APPS.
     * @param int $courseid The course the launch is for; SITEID for the config and institution apps.
     */
    public static function require_access(string $app, int $courseid): void {
        switch ($app) {
            case 'config':
                require_login(null, false);
                require_capability('tool/ally:clientconfig', \context_system::instance());
                return;
            case 'institution':
                require_login(null, false);
                require_capability('report/allylti:viewadminreport', \context_system::instance());
                return;
            case 'course':
                require_login($courseid, false);
                require_capability('report/allylti:viewcoursereport', \context_course::instance($courseid));
                return;
        }
        throw new \moodle_exception('invalidrequest', 'error');
    }

    /**
     * The unsaved LTI instance for a launch without a course module.
     *
     * id is a string, as a DB-loaded id is: it becomes the resource_link id claim, which Ally decodes as a string.
     * resourcekey and password stay empty: a non-empty resourcekey would win over the tool's LTI 1.3 client id in
     * lti_get_launch_data().
     *
     * @param int $typeid
     * @param \stdClass $typeconfig From lti_get_type_type_config().
     * @param int $courseid
     * @return \stdClass
     */
    public static function instance(int $typeid, \stdClass $typeconfig, int $courseid): \stdClass {
        return (object) [
            'id' => '0',
            'course' => $courseid,
            'name' => 'Ally',
            'typeid' => $typeid,
            'instructorchoicesendname' => 1,
            'instructorchoicesendemailaddr' => 1,
            'instructorchoiceallowroster' => null,
            'instructorcustomparameters' => null,
            'instructorchoiceacceptgrades' => 0,
            'resourcekey' => '',
            'password' => '',
            'launchcontainer' => LTI_LAUNCH_CONTAINER_EMBED,
            'toolurl' => $typeconfig->lti_toolurl,
            'securetoolurl' => '',
            'servicesalt' => uniqid('', true),
            'debuglaunch' => 0,
        ];
    }

    /**
     * Auto-submitting OIDC login initiation form: the first step of an LTI 1.3 launch.
     *
     * Core builds the request and stores the launch in the session; only the "ally" marker is added.
     *
     * @param int $typeid
     * @param int $courseid
     * @return string HTML
     */
    public static function login_form(int $typeid, int $courseid): string {
        $typeconfig = lti_get_type_type_config($typeid);
        $instance = self::instance($typeid, $typeconfig, $courseid);
        $params = lti_build_login_request($courseid, '', $instance, $typeconfig, 'basic-lti-launch-request');
        $hint = json_decode($params['lti_message_hint'], true);
        $hint['ally'] = true;
        $params['lti_message_hint'] = json_encode($hint);
        return lti_post_launch_html($params, $typeconfig->lti_initiatelogin, false);
    }

    /**
     * The Ally host the 1.1 launch already points at.
     *
     * @param \stdClass $pluginconfig tool_ally config.
     * @return string|null
     */
    private static function ally_host(\stdClass $pluginconfig): ?string {
        $host = parse_url($pluginconfig->adminurl ?? '', PHP_URL_HOST);
        return $host ?: null;
    }
}
