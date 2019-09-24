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

if (!defined('MOODLE_INTERNAL')) {
    // It must be included from a Moodle page.
    die('Direct access to this script is forbidden.');
}

require_once($CFG->libdir.'/formslib.php');

class report_edit_form extends moodleform {
    public function definition() {
        global $DB, $USER, $CFG, $COURSE;

        $mform =& $this->_form;

        $mform->addElement('header', 'general', get_string('filter', 'block_configurable_reports'));

        $this->_customdata->add_filter_elements($mform);

        $mform->addElement('hidden', 'id', $this->_customdata->config->id);
        $mform->addElement('hidden', 'courseid', $COURSE->id);
        $mform->setType('id', PARAM_INT);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'embedded', optional_param('embedded', 0, PARAM_INT));
        $mform->setType('embedded', PARAM_INT);

        // Buttons.
        $this->add_action_buttons(true, get_string('filter_apply', 'block_configurable_reports'));

        // Filter preference save
        $preferences = array();
        $preferences[] =& $mform->createElement('text', 'prefname', get_string('savechanges'),
            ['placeholder' => get_string('savepreferences', 'block_configurable_reports'), 'data-id' => 0]);
        $preferences[] =& $mform->createElement('submit', 'prefsave', get_string('save'));
        $preferences[] =& $mform->createElement('advcheckbox', 'defaultfilter', get_string('default'), '', array('group' => 1), array(0, 1));

        if ($records = $DB->get_records('block_configurable_reports_p',
            ['userid' => $USER->id, 'reportid' => $this->_customdata->config->id])) {

            $preferencesmenu = [];
            foreach ($records as $record) {
                if ($record->defaultfilter) {
                    $preferencesmenu[$record->id] = $record->name . ' ('.get_string('default').')';
                } else {
                    $preferencesmenu[$record->id] = $record->name;
                }
            }

            $preferencesmenu = ['' => get_string('load', 'block_configurable_reports')] + $preferencesmenu;
            $preferences[] =& $mform->createElement('select', 'presaved', '',   $preferencesmenu);
        } else {
            $preferencesmenu = ['' => get_string('load', 'block_configurable_reports')];
            $preferences[] =& $mform->createElement('select', 'presaved', '',   $preferencesmenu , ['style' => 'display: none;', 'hidden' =>'hidden']);
        }

        $mform->setType('prefname', PARAM_TEXT);
        $mform->addGroup($preferences, 'preferencesarr', '', array(' '), false);
    }
}
