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
        global $DB;
        $defaultvalue = (isset($this->defaultfilter->{'filter_sql_'.$data->idnumber})) ? $this->defaultfilter->{'filter_sql_'.$data->idnumber} : '';
        if (!empty($data->multiselect)) {
            $filtersqloptions = optional_param_array('filter_sql_'.$data->idnumber, $defaultvalue, PARAM_RAW);
        } else {
            $filtersqloptions = optional_param('filter_sql_'.$data->idnumber, $defaultvalue, PARAM_RAW);
        }

        $filter = '%all%';
        $filters = [];
        if ($filtersqloptions && is_array($filtersqloptions) && !in_array('%all%', $filtersqloptions)) {
            foreach ($filtersqloptions as $filtersqloption) {
                $filters[] = clean_param(base64_decode($filtersqloption), PARAM_RAW);
            }
        }
        if ($filtersqloptions && !is_array($filtersqloptions) && $filtersqloptions != '%all%') {
            $filter = clean_param(base64_decode($filtersqloptions), PARAM_RAW);
        }
        if (empty($filters) && $filter == '%all%') {
            if ($filtersqloptions != '%all%' && !empty($data->defaultsql)) {
                $reportclassname = 'report_'.$this->report->type;
                $reportclass = new $reportclassname($this->report);
                $sql = $reportclass->prepare_sql($data->defaultsql);
                if ($result = $DB->get_record_sql($sql, null, IGNORE_MULTIPLE)) {
                    $filter = $result->configid;
                }
            }
        }

        $operators = array('=', '<', '>', '<=', '>=', '~', 'in', 'rin');

        if ((!empty($filters) || $filter != '%all%')
            && preg_match_all("/%%FILTER_SQL_$data->idnumber:([^%]+)%%/i", $finalelements, $output)) {
            for ($i = 0; $i < count($output[1]); $i++) {
                list($field, $operator) = preg_split('/:/', $output[1][$i]);
                if (!in_array($operator, $operators)) {
                    print_error('nosuchoperator');
                }
                if ($operator == '~') {
                    if ($filters) {
                        $sqlfilter = [];
                        foreach ($filters as $filter) {
                            $sqlfilter[] = "$field LIKE '%$filter%'";
                        }
                        $replace = " AND (" . implode(' OR ', $sqlfilter) . ')';
                    } else {
                        $replace = " AND $field LIKE '%$filter%'";
                    }
                } else if ($operator == 'in') {
                    if ($filters) {
                        $sqlfilter = [];
                        foreach ($filters as $filter) {
                            $sqlfilter = "'$filter' IN $field";
                        }
                    } else {
                        $replace = " AND '$filter' IN $field";
                    }
                } else if ($operator == 'rin') {
                    // Reverse IN
                    // Checks if defined column value is in value(s) selected in the filter
                    if ($filters) {
                        $filter = implode(',', $filters);
                    }
                    $possibles = explode(',', $filter);
                    $length = count($possibles);
                    $filtersql = "(";
                    for ($j = 0; $j < $length; $j++) {
                        $filtersql .= "'$possibles[$j]'";
                        if ($j < ($length - 1)) {
                            $filtersql .= ",";
                        } else {
                            $filtersql .= ")";
                        }
                    }
                    $replace = " AND $field IN $filtersql";
                } else {
                    if ($filters) {
                        $sqlfilter = [];
                        foreach ($filters as $filter) {
                            $sqlfilter[] = "$field $operator '$filter'";
                        }
                        $replace = " AND (" . implode(' OR ', $sqlfilter) . ')';
                    } else {
                        $replace = " AND $field $operator '$filter'";
                    }
                }

                $finalelements = str_replace('%%FILTER_SQL_'.$data->idnumber.':' . $output[1][$i] . '%%', $replace, $finalelements);
            }
        }

        return $finalelements;
    }

    public function print_filter(&$mform, $data) {
        global $DB, $CFG;

        $filteroptions = array();
        $filteroptions['%all%'] = get_string('filter_all', 'block_configurable_reports');
        $defaultvalue = (isset($this->defaultfilter->{'filter_sql_'.$data->idnumber})) ? $this->defaultfilter->{'filter_sql_'.$data->idnumber} : '';

        $reportclassname = 'report_'.$this->report->type;
        $reportclass = new $reportclassname($this->report);
        $sql = $reportclass->prepare_sql($data->querysql);

        $results = $DB->get_records_sql($sql);

        foreach ($results as $result) {
            if (empty($result->configdisplay)) {
                $filteroptions[base64_encode($result->configid)] = $result->configid;
            } else {
                $filteroptions[base64_encode($result->configid)] = $result->configdisplay;
            }
        }

        if (!empty($data->label)) {
            $selectname = $data->label;
        }

        $select = $mform->addElement('select', 'filter_sql_'.$data->idnumber, $selectname, $filteroptions);
        if (!empty($data->multiselect)) {
            $select->setMultiple(true);
            if ($defaultvalue) {
                $select->setSelected($defaultvalue);
            } else {
                $select->setSelected('%all%');
            }
        } else {
            if ($defaultvalue) {
                $select->setSelected($defaultvalue);
            }
        }

        if (!$defaultvalue && !empty($data->defaultsql)) {
            $sql = $reportclass->prepare_sql($data->defaultsql);
            if ($result = $DB->get_record_sql($sql, null, IGNORE_MULTIPLE)) {
                $default = base64_encode($result->configid);
                if (array_key_exists($default, $filteroptions)) {
                    $select->setSelected($default);
                }
            }
        }
    }
}
