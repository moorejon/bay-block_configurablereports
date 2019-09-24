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
 * Web service declarations
 *
 * @package    block_configurable_reports
 * @copyright  2019 Michael Gardener <mgardener@cissq.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

class block_configurable_reports_external extends external_api {

    /**
     * Returns description of update_filter_preferences() parameters.
     *
     * @return \external_function_parameters
     */
    public static function update_filter_preferences_parameters() {
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, '', VALUE_REQUIRED),
                'reportid' => new external_value(PARAM_INT, '', VALUE_REQUIRED),
                'name' => new external_value(PARAM_TEXT, '', VALUE_REQUIRED),
                'parameters' => new external_value(PARAM_RAW, '', VALUE_REQUIRED),
                'defaultfilter' => new external_value(PARAM_RAW, '', VALUE_OPTIONAL, '0')
            )
        );
    }

    public static function update_filter_preferences($id, $reportid, $name, $parameters, $defaultfilter = 0) {
        global $DB, $USER;
        $params = self::validate_parameters(self::update_filter_preferences_parameters(), array(
            'id' => $id,
            'reportid' => $reportid,
            'name' => $name,
            'parameters' => $parameters,
            'defaultfilter' => $defaultfilter
        ));

        $report = $DB->get_record('block_configurable_reports', ['id' => $params['reportid']], '*', MUST_EXIST);
        if ($id) {
            $preference = $DB->get_record('block_configurable_reports_p', ['id' => $params['id']], '*', MUST_EXIST);
        }

        $context = context_course::instance($report->courseid);
        self::validate_context($context);

        if (!has_capability('block/configurable_reports:viewreports', $context)) {
            return false;
        }

        if (!$preference) {
            $preference = new \stdClass();
            $preference->userid = $USER->id;
            $preference->name = $params['name'];
            $preference->reportid = $params['reportid'];
            $preference->filter = $params['parameters'];
            $preference->defaultfilter = $params['defaultfilter'];

            if ($preference->defaultfilter) {
                $DB->execute("UPDATE {block_configurable_reports_p} SET defaultfilter = 0 WHERE userid = ? AND reportid = ?",
                    [$USER->id, $params['reportid']]
                );
            }

            $DB->insert_record('block_configurable_reports_p', $preference);
        }

        return true;
    }

    /**
     * Returns description of update_filter_preferences() result value.
     *
     * @return \external_value
     */
    public static function update_filter_preferences_returns() {
        return new external_value(PARAM_BOOL, 'True if the update was successful.');
    }

    /**
     * Returns description of get_filter_preferences() parameters.
     *
     * @return \external_function_parameters
     */
    public static function get_filter_preferences_parameters() {
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, '', VALUE_REQUIRED)
            )
        );
    }

    public static function get_filter_preferences($id) {
        global $DB, $USER;
        $params = self::validate_parameters(self::get_filter_preferences_parameters(), array('id' => $id));

        $preference = $DB->get_record('block_configurable_reports_p', ['id' => $params['id']], '*', MUST_EXIST);
        $report = $DB->get_record('block_configurable_reports', ['id' => $preference->reportid], '*', MUST_EXIST);

        $context = context_course::instance($report->courseid);
        self::validate_context($context);

        if (!has_capability('block/configurable_reports:viewreports', $context)) {
            return false;
        }

        return $preference;
    }

    /**
     * Returns description of get_filter_preferences() result value.
     *
     * @return \external_value
     */
    public static function get_filter_preferences_returns() {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'name' => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                'reportid' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'userid' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'filter' => new external_value(PARAM_RAW, '', VALUE_OPTIONAL)
            )
        );
    }
}