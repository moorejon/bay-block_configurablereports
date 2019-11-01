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

class plugin_base {

    public $fullname = '';
    public $type = '';
    public $report = null;
    public $form = false;
    public $cache = array();
    public $unique = false;
    public $reporttypes = array();
    public $defaultfilter = null;
    public $defaultfiltervalue = null;

    public function __construct($report) {
        global $DB, $CFG, $remotedb, $USER;

        if (is_numeric($report)) {
            $this->report = $DB->get_record('block_configurable_reports', array('id' => $report));
        } else {
            $this->report = $report;
        }

        if (!data_submitted()) {
            if ($defaultfilter = $DB->get_field('block_configurable_reports_p', 'filter',
                array('reportid' => $this->report->id, 'userid' => $USER->id, 'defaultfilter' => 1))) {

                $this->defaultfilter = new stdClass();
                foreach (json_decode($defaultfilter) as $item) {
                    $this->defaultfilter->{$item->name} = $item->value;
                }
            }
        }

        $this->init();
    }

    public function summary($data) {
        return '';
    }

    // Should be override.
    public function init() {
        return '';
    }

}
