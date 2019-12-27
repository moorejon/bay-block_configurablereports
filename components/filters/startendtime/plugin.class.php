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
 * Configurable Reports
 * A Moodle block for creating customizable reports
 * @package blocks
 * @author: Juan leyva <http://www.twitter.com/jleyvadelgado>
 * @date: 2009
 */

require_once($CFG->dirroot.'/blocks/configurable_reports/plugin.class.php');

class plugin_startendtime extends plugin_base {

    public function init() {
        $this->form = true;
        $this->unique = true;
        $this->fullname = get_string('startendtime', 'block_configurable_reports');
        $this->reporttypes = array('sql', 'timeline', 'users', 'courses');
    }

    public function summary($data) {
        return get_string('filterstartendtime_summary', 'block_configurable_reports');
    }

    public function execute($finalelements, $data) {
        global $CFG;

        if ($this->report->type != 'sql') {
            return $finalelements;
        }
        if (!isset($data->selector)) {
            $data->selector = 'datetime';
        }

        if ($CFG->version < 2011120100) {
            $filterstarttime = optional_param('filter_starttime', 0, PARAM_RAW);
            $filterendtime = optional_param('filter_endtime', 0, PARAM_RAW);
        } else {
            $filterstarttime = optional_param_array('filter_starttime', 0, PARAM_RAW);
            $filterendtime = optional_param_array('filter_endtime', 0, PARAM_RAW);
        }

        if (!$filterstarttime || !$filterendtime) {
            if (!empty($data->defaulttimeframe)) {
                list($filterstarttime, $filterendtime) = $this->get_start_end_times($data->defaulttimeframe, $data->selector);
            } else {
                list($filterstarttime, $filterendtime) = $this->get_start_end_times('1 month', $data->selector);
            }
        } else {
            if ($data->selector == 'datetime') {
                $filterstarttime = make_timestamp($filterstarttime['year'], $filterstarttime['month'], $filterstarttime['day'],
                        $filterstarttime['hour'], $filterstarttime['minute'], 0, core_date::get_user_timezone($this->user));
                $filterendtime = make_timestamp($filterendtime['year'], $filterendtime['month'], $filterendtime['day'],
                        $filterendtime['hour'], $filterendtime['minute'], 0, core_date::get_user_timezone($this->user));
            } else {
                $filterstarttime = make_timestamp($filterstarttime['year'], $filterstarttime['month'], $filterstarttime['day'],
                        0, 0, 0, core_date::get_user_timezone($this->user));
                $filterendtime = make_timestamp($filterendtime['year'], $filterendtime['month'], $filterendtime['day'],
                        0, 0, 0, core_date::get_user_timezone($this->user));
            }
        }

        $operators = array('<', '>', '<=', '>=');

        if (preg_match_all("/%%FILTER_STARTTIME:([^%]+)%%/i", $finalelements, $output)) {
            for ($i = 0; $i < count($output[1]); $i++) {
                list($field, $operator) = preg_split('/:/', $output[1][$i]);
                if (!in_array($operator, $operators)) {
                    print_error('nosuchoperator');
                }
                $replace = ' AND ' . $field . ' ' . $operator . ' ' . $filterstarttime;
                $finalelements = str_replace('%%FILTER_STARTTIME:' . $output[1][$i] . '%%', $replace, $finalelements);
            }
        }

        if (preg_match_all("/%%FILTER_ENDTIME:([^%]+)%%/i", $finalelements, $output)) {
            for ($i = 0; $i < count($output[1]); $i++) {
                list($field, $operator) = preg_split('/:/', $output[1][$i]);
                if (!in_array($operator, $operators)) {
                    print_error('nosuchoperator');
                }
                $replace = ' AND ' . $field . ' ' . $operator . ' ' . $filterendtime;
                $finalelements = str_replace('%%FILTER_ENDTIME:' . $output[1][$i] . '%%', $replace, $finalelements);
            }
        }

        $finalelements = str_replace('%STARTTIME%%', $filterstarttime, $finalelements);
        $finalelements = str_replace('%ENDTIME%%', $filterendtime, $finalelements);

        return $finalelements;
    }

    public function print_filter(&$mform, $data) {
        if (!isset($data->selector)) {
            $data->selector = 'datetime';
        }
        if ($data->selector == 'datetime') {
            $selector = 'date_time_selector';
        } else {
            $selector = 'date_selector';
        }

        $mform->addElement($selector, 'filter_starttime', get_string('starttime', 'block_configurable_reports'));
        if (!empty($data->defaulttimeframe)) {
            list($starttime, $endtime) = $this->get_start_end_times($data->defaulttimeframe, $data->selector);
        } else {
            list($starttime, $endtime) = $this->get_start_end_times('1 month', $data->selector);
        }
        $mform->setDefault('filter_starttime', $starttime);
        $mform->addElement($selector, 'filter_endtime', get_string('endtime', 'block_configurable_reports'));
        $mform->setDefault('filter_endtime', $endtime);
    }

    public function get_start_end_times($timeframe = '1 month', $dateselectortype = 'datetime') {
        $timezone = new DateTimeZone(core_date::get_user_timezone($this->user));
        if ($dateselectortype == 'datetime') {
            $endtime = new DateTime('now', $timezone);
            $starttime = clone $endtime;
            if ($timeframe) {
                $dateinterval = date_interval_create_from_date_string($timeframe);
            }
            $starttime->sub($dateinterval);
        } else {
            $endtime = new DateTime('now', $timezone);
            $endtime->setTime(0,0, 0);
            $starttime = clone $endtime;
            if ($timeframe) {
                $dateinterval = date_interval_create_from_date_string($timeframe);
            }
            $starttime->sub($dateinterval);
        }

        return array($starttime->getTimestamp(), $endtime->getTimestamp());
    }
}
