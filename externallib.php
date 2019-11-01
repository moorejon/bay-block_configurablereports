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
                'defaultfilter' => new external_value(PARAM_RAW, '', VALUE_OPTIONAL, '0'),
                'action' => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL, 'update')
            )
        );
    }

    public static function update_filter_preferences($id, $reportid, $name, $parameters, $defaultfilter = 0, $action = 'update') {
        global $DB, $USER;

        $result = array();

        $params = self::validate_parameters(self::update_filter_preferences_parameters(), array(
            'id' => $id,
            'reportid' => $reportid,
            'name' => $name,
            'parameters' => $parameters,
            'defaultfilter' => $defaultfilter,
            'action' => $action,
        ));

        $report = $DB->get_record('block_configurable_reports', ['id' => $params['reportid']], '*', MUST_EXIST);

        $context = context_course::instance($report->courseid);
        self::validate_context($context);

        if (!has_capability('block/configurable_reports:viewreports', $context)) {
            $result['success'] = false;
            $result['msg'] = get_string('nopermissions', 'error', '');
            return $result;
        }

        if ($params['id']) {
            $preference = $DB->get_record('block_configurable_reports_p', ['id' => $params['id']], '*', MUST_EXIST);
        }

        if ($params['action'] == 'setdefault') {
            $data = new stdClass();
            $data->id = $preference->id;

            if ($preference->defaultfilter) {
                $data->defaultfilter = 0;
                $message = 'removed';
            } else {
                $DB->execute("UPDATE {block_configurable_reports_p} SET defaultfilter = 0 WHERE userid = ? AND reportid = ?",
                    [$USER->id, $params['reportid']]
                );
                $data->defaultfilter = 1;
                $message = '';
            }

            if ($result['success'] = $DB->update_record('block_configurable_reports_p', $data)) {
                $result['msg'] = $message;
            } else {
                $result['msg'] = get_string('filternotupdate', 'block_configurable_reports');
            }

            return $result;
        } else if ($params['action'] == 'delete') {
            if ($result['success'] = $DB->delete_records('block_configurable_reports_p', ['id' => $params['id']])) {
                $result['msg'] = '';
            } else {
                $result['msg'] = get_string('filternotdelete', 'block_configurable_reports');
            }
            return $result;
        } else {
            // Check duplicate name.
            if (empty($preference)) {
                if ($duplicate = $DB->get_record('block_configurable_reports_p', ['name' => $params['name'], 'reportid' => $params['reportid'], 'userid' => $USER->id])) {
                    $result['success'] = false;
                    $result['msg'] = get_string('filterwithsamename', 'block_configurable_reports');
                    return $result;
                }

                $preference = new \stdClass();
                $preference->userid = $USER->id;
                $preference->name = $params['name'];
                $preference->reportid = $params['reportid'];
                $preference->filter = $params['parameters'];
                $preference->defaultfilter = $params['defaultfilter'];

                $params['id'] = $DB->insert_record('block_configurable_reports_p', $preference);
            } else {
                $preference->filter = $params['parameters'];
                $DB->update_record('block_configurable_reports_p', $preference);
            }
        }

        $result = array();
        $result['success'] = true;
        $result['msg'] = '';
        $result['id'] = $params['id'];
        return $result;
    }

    /**
     * Returns description of update_filter_preferences() result value.
     *
     * @return \external_value
     */
    public static function update_filter_preferences_returns() {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, '', VALUE_OPTIONAL),
                'msg' => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL),
                'id' => new external_value(PARAM_INT, '', VALUE_OPTIONAL)
            )
        );
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