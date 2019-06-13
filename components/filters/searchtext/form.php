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

if (!defined('MOODLE_INTERNAL')) {
    //  It must be included from a Moodle page.
    die('Direct access to this script is forbidden.');
}

require_once($CFG->libdir.'/formslib.php');

class searchtext_form extends moodleform {

    public function definition() {
        $mform =& $this->_form;

        $mform->addElement('header',  'crformheader', get_string('filter_searchtext', 'block_configurable_reports'), '');

        $mform->addElement('text', 'idnumber', get_string('idnumber'));
        $mform->setType('idnumber', PARAM_RAW);
        $mform->addRule('idnumber', null, 'required', null, 'client');

        $mform->addElement('text', 'label', get_string('label', 'block_configurable_reports'));
        $mform->setType('label', PARAM_RAW);
        $mform->addRule('label', null, 'required', null, 'client');

        // Buttons.
        $this->add_action_buttons(true, get_string('add'));
    }
}
