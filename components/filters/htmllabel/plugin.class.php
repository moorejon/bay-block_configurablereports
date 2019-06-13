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

/** Configurable Reports
 * A Moodle block for creating customizable reports
 * @package blocks
 * @author: Juan leyva <http://www.twitter.com/jleyvadelgado>
 * @date: 2009
 */

require_once($CFG->dirroot.'/blocks/configurable_reports/plugin.class.php');

class plugin_htmllabel extends plugin_base{

    public function init() {
        $this->form = true;
        $this->unique = true;
        $this->fullname = get_string('filter_htmllabel', 'block_configurable_reports');
        $this->reporttypes = array('sql');
    }

    public function summary($data) {
        return get_string('filter_htmllabel_summary', 'block_configurable_reports');
    }

    public function execute($finalelements, $data) {
        return $finalelements;
    }

    public function print_filter(&$mform, $data) {

        $mform->addElement('static', 'filter_htmllabel_label', $data->label, $data->html);
    }
}
