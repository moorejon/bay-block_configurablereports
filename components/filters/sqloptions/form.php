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

if (!defined('MOODLE_INTERNAL')) {
    //  It must be included from a Moodle page.
    die('Direct access to this script is forbidden.');
}

require_once($CFG->libdir.'/formslib.php');

class sqloptions_form extends moodleform {

    public function definition() {
        global $DB, $CFG, $COURSE;

        $mform =& $this->_form;

        $mform->addElement('header',  'crformheader', get_string('filter_sql', 'block_configurable_reports'), '');

        $mform->addElement('text', 'idnumber', get_string('idnumber'));
        $mform->setType('idnumber', PARAM_RAW);
        $mform->addRule('idnumber', null, 'required', null, 'client');

        $mform->addElement('text', 'label', get_string('label', 'block_configurable_reports'));
        $mform->setType('label', PARAM_RAW);
        $mform->addRule('label', null, 'required', null, 'client');

        $mform->addElement('textarea', 'querysql', get_string('querysql', 'block_configurable_reports'), 'rows="35" cols="80"');
        $mform->addRule('querysql', get_string('required'), 'required', null, 'client');
        $mform->setType('querysql', PARAM_RAW);

        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        if (get_config('block_configurable_reports', 'sqlsecurity')) {
            return $this->validation_high_security($data, $files);
        } else {
            return $this->validation_low_security($data, $files);
        }
    }

    public function validation_high_security($data, $files) {
        global $DB, $CFG, $db, $USER;

        $errors = parent::validation($data, $files);

        $sql = $data['querysql'];
        $sql = trim($sql);

        // Simple test to avoid evil stuff in the SQL.
        $regex = '/\b(ALTER|CREATE|DELETE|DROP|GRANT|INSERT|INTO|TRUNCATE|UPDATE|SET|VACUUM|REINDEX|DISCARD|LOCK)\b/i';
        if (preg_match($regex, $sql)) {
            $errors['querysql'] = get_string('notallowedwords', 'block_configurable_reports');

        } else if (strpos($sql, ';') !== false) {
            // Do not allow any semicolons.
            $errors['querysql'] = get_string('nosemicolon', 'report_customsql');

        } else if ($CFG->prefix != '' && preg_match('/\b' . $CFG->prefix . '\w+/i', $sql)) {
            // Make sure prefix is prefix_, not explicit.
            $errors['querysql'] = get_string('noexplicitprefix', 'block_configurable_reports');

        } else {
            // Now try running the SQL, and ensure it runs without errors.

            $sql = $this->_customdata['reportclass']->prepare_sql($sql);
            $rs = null;
            try {
                $rs = $this->_customdata['reportclass']->execute_query($sql, 2);
            } catch (dml_read_exception $e) {
                $errors['querysql'] = get_string('queryfailed', 'block_configurable_reports', $e->error );
            }
            if ($rs && !empty($data['singlerow'])) {
                if (rs_EOF($rs)) {
                    $errors['querysql'] = get_string('norowsreturned', 'block_configurable_reports');
                }
            }

            if (!array_key_exists('configid', $rs->current()) || !array_key_exists('configdisplay', $rs->current())) {
                $errors['querysql'] = get_string('noconfigidordisplay', 'block_configurable_reports');
            }

            if ($rs) {
                $rs->close();
            }
        }

        return $errors;
    }

    public function validation_low_security($data, $files) {
        global $DB, $CFG, $db, $USER;

        $errors = parent::validation($data, $files);

        $sql = $data['querysql'];
        $sql = trim($sql);

        if (empty($this->_customdata['report']->runstatistics) OR $this->_customdata['report']->runstatistics == 0) {
            // Simple test to avoid evil stuff in the SQL.
            // Allow cron SQL queries to run CREATE|INSERT|INTO queries.
            if (preg_match('/\b(ALTER|DELETE|DROP|GRANT|TRUNCATE|UPDATE|SET|VACUUM|REINDEX|DISCARD|LOCK)\b/i', $sql)) {
                $errors['querysql'] = get_string('notallowedwords', 'block_configurable_reports');
            }
        } else {
            // Now try running the SQL, and ensure it runs without errors.
            $sql = $this->_customdata['reportclass']->prepare_sql($sql);
            $rs = $this->_customdata['reportclass']->execute_query($sql, 2);
            if (!$rs) {
                $errors['querysql'] = get_string('queryfailed', 'block_configurable_reports', $db->ErrorMsg());
            } else if (!empty($data['singlerow'])) {
                if (rs_EOF($rs)) {
                    $errors['querysql'] = get_string('norowsreturned', 'block_configurable_reports');
                }
            }

            if (!array_key_exists('configid', $rs->current()) || !array_key_exists('configdisplay', $rs->current())) {
                $errors['querysql'] = get_string('noconfigidordisplay', 'block_configurable_reports');
            }

            if ($rs) {
                $rs->close();
            }
        }

        return $errors;
    }
}
