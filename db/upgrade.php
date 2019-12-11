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
 * Version details
 *
 * Configurable Reports - A Moodle block for creating customizable reports
 *
 * @package     block_configurable_reports
 * @author:     Juan leyva <http://www.twitter.com/jleyvadelgado>
 * @date:       2013-09-07
 *
 * @copyright  Juan leyva <http://www.twitter.com/jleyvadelgado>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_block_configurable_reports_upgrade($oldversion) {
    global $DB, $CFG;

    $dbman = $DB->get_manager();

    if ($oldversion < 2011040103) {

        $table = new xmldb_table('block_configurable_reports_report');
        $dbman->rename_table($table, 'block_configurable_reports');
        upgrade_plugin_savepoint(true, 2011040103, 'block', 'configurable_reports');
    }

    if ($oldversion < 2011040106) {

        $table = new xmldb_table('block_configurable_reports');

        $field = new xmldb_field('global', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, null, null, '0', null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('lastexecutiontime', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, '0', null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('cron', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, '0', null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2011040106, 'block', 'configurable_reports');
    }

    if ($oldversion < 2011040115) {

        $table = new xmldb_table('block_configurable_reports');

        $field = new xmldb_field('remote', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, '0', null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2011040115, 'block', 'configurable_reports');
    }

    if ($oldversion < 2019020600) {
        $table = new xmldb_table('block_configurable_reports');
        $field = new xmldb_field('summaryformat');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'summary');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Conditionally migrate to html format in summary.
        if ($CFG->texteditors !== 'textarea') {
            $rs = $DB->get_recordset('block_configurable_reports', array('summaryformat'=>FORMAT_MOODLE), '', 'id, summary, summaryformat');
            foreach ($rs as $f) {
                $f->summary = text_to_html($f->summary, false, false, true);
                $f->summaryformat = FORMAT_HTML;
                $DB->update_record('block_configurable_reports', $f);
                upgrade_set_timeout();
            }
            $rs->close();
        }

        upgrade_plugin_savepoint(true, 2019020600, 'block', 'configurable_reports');
    }

    if ($oldversion < 2019021503) {

        // Define field id to be added to block_configurable_reports.
        $table = new xmldb_table('block_configurable_reports');
        $field = new xmldb_field('enableschedule', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'remote');

        // Conditionally launch add field enableschedule.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('emailto', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'enableschedule');

        // Conditionally launch add field emailto.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('customdir', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'emailto');

        // Conditionally launch add field customdir.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('frequency', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'daily', 'customdir');

        // Conditionally launch add field frequency.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('runat', XMLDB_TYPE_CHAR, '16', null, null, null, null, 'frequency');

        // Conditionally launch add field runat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('lastrun', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'runat');

        // Conditionally launch add field lastrun.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('enableschedule_index', XMLDB_INDEX_NOTUNIQUE, array('enableschedule'));

        // Conditionally launch add index enableschedule_index.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index = new xmldb_index('frequency_index', XMLDB_INDEX_NOTUNIQUE, array('frequency'));

        // Conditionally launch add index frequency_index.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index = new xmldb_index('lastrun_index', XMLDB_INDEX_NOTUNIQUE, array('lastrun'));

        // Conditionally launch add index lastrun_index.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Configurable_reports savepoint reached.
        upgrade_block_savepoint(true, 2019021503, 'configurable_reports');
    }

    if ($oldversion < 2019040602) {

        // Define field lastexport to be added to block_configurable_reports.
        $table = new xmldb_table('block_configurable_reports');
        $field = new xmldb_field('lastexport', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'lastrun');

        // Conditionally launch add field lastexport.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Configurable_reports savepoint reached.
        upgrade_block_savepoint(true, 2019040602, 'configurable_reports');
    }

    if ($oldversion < 2019060301) {

        // Define field noresultdisplay to be added to block_configurable_reports.
        $table = new xmldb_table('block_configurable_reports');
        $field = new xmldb_field('noresultdisplay', XMLDB_TYPE_TEXT, null, null, null, null, null, 'lastexport');

        // Conditionally launch add field noresultdisplay.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Configurable_reports savepoint reached.
        upgrade_block_savepoint(true, 2019060301, 'configurable_reports');
    }

    if ($oldversion < 2019082202) {
        $table = new xmldb_table('block_configurable_reports');
        $field = new xmldb_field('datatableperpage', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'noresultdisplay');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $DB->execute("UPDATE {block_configurable_reports} SET datatableperpage = 100");

        upgrade_block_savepoint(true, 2019082202, 'configurable_reports');
    }

    if ($oldversion < 2019091701) {

        // Define table block_configurable_reports_p to be created.
        $table = new xmldb_table('block_configurable_reports_p');

        // Adding fields to table block_configurable_reports_p.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('reportid', XMLDB_TYPE_INTEGER, '11', null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '11', null, null, null, null);
        $table->add_field('filter', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table block_configurable_reports_p.
        $table->add_key('id', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for block_configurable_reports_p.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Configurable_reports savepoint reached.
        upgrade_block_savepoint(true, 2019091701, 'configurable_reports');
    }

    if ($oldversion < 2019092301) {

        // Define field default to be added to block_configurable_reports_p.
        $table = new xmldb_table('block_configurable_reports_p');
        $field = new xmldb_field('defaultfilter', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'filter');

        // Conditionally launch add field default.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Configurable_reports savepoint reached.
        upgrade_block_savepoint(true, 2019092301, 'configurable_reports');
    }

    if ($oldversion < 2019103102) {

        // Define field converttime to be added to block_configurable_reports.
        $table = new xmldb_table('block_configurable_reports');
        $field = new xmldb_field('converttime', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'datatableperpage');

        // Conditionally launch add field converttime.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field timeformat to be added to block_configurable_reports.
        $table = new xmldb_table('block_configurable_reports');
        $field = new xmldb_field('timeformat', XMLDB_TYPE_CHAR, '25', null, XMLDB_NOTNULL, null, null, 'converttime');

        // Conditionally launch add field timeformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Configurable_reports savepoint reached.
        upgrade_block_savepoint(true, 2019103102, 'configurable_reports');
    }

    if ($oldversion < 2019120800) {

        // Define field fixedwidthpattern to be added to block_configurable_reports.
        $table = new xmldb_table('block_configurable_reports');
        $field = new xmldb_field('fixedwidthpattern', XMLDB_TYPE_TEXT, null, null, null, null, null, 'timeformat');

        // Conditionally launch add field fixedwidthpattern.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Configurable_reports savepoint reached.
        upgrade_block_savepoint(true, 2019120800, 'configurable_reports');
    }
    return true;
}