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
 * Custom SQL select filter
 *
 * @package    block_configurable_reports
 * @copyright  2019 MLC - David Saylor <david@mylearningconsultants.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/blocks/configurable_reports/plugin.class.php');

class plugin_sqloptions extends plugin_base {

    public function init() {
        $this->form = true;
        $this->unique = false;
        $this->fullname = get_string('sqloptions', 'block_configurable_reports');
        $this->reporttypes = array('sql');
    }

    public function summary($data) {
        return get_string('filtersql_summary', 'block_configurable_reports');
    }

    public function execute($finalelements, $data) {
        $filtersqloptions = optional_param('filter_sql_'.$data->idnumber, 0, PARAM_RAW);
        $filter = clean_param(base64_decode($filtersqloptions), PARAM_RAW);

        $operators = array('=', '<', '>', '<=', '>=', '~');

        if ($filtersqloptions && preg_match_all("/%%FILTER_SQL_$data->idnumber:([^%]+)%%/i", $finalelements, $output)) {
            for ($i = 0; $i < count($output[1]); $i++) {
                list($field, $operator) = preg_split('/:/', $output[1][$i]);
                if (!in_array($operator, $operators)) {
                    print_error('nosuchoperator');
                }
                if ($operator == '~') {
                    $replace = " AND $field LIKE '%$filter%'";
                } else {
                    $replace = " AND $field $operator '$filter'";
                }

                $finalelements = str_replace('%%FILTER_SQL_'.$data->idnumber.':' . $output[1][$i] . '%%', $replace, $finalelements);
            }
        }

        return $finalelements;
    }

    public function print_filter(&$mform, $data) {
        global $DB, $CFG;

        $filteroptions = array();
        $filteroptions[''] = get_string('filter_all', 'block_configurable_reports');

        $reportclassname = 'report_'.$this->report->type;
        $reportclass = new $reportclassname($this->report);
        $sql = $reportclass->prepare_sql($data->querysql);

        $results = $DB->get_records_sql($sql);

        foreach ($results as $result) {
            $filteroptions[base64_encode($result->configid)] = $result->configdisplay;
        }

        if (!empty($data->label)) {
            $selectname = $data->label;
        }

        $mform->addElement('select', 'filter_sql_'.$data->idnumber, $selectname, $filteroptions);
        $mform->setType('filter_sql_'.$data->idnumber, PARAM_BASE64);
    }
}
