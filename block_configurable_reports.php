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

class block_configurable_reports extends block_base {

    /**
     * Sets the block name and version number
     *
     * @return void
     **/
    public function init() {
        $this->title = get_string('pluginname', 'block_configurable_reports');
    }

    /**
     * Act on instance data.
     */
    public function specialization() {

        if ($this->config) {
            $this->title = $this->config->title ? $this->config->title : get_string('pluginname', 'block_configurable_reports');
        } else {
            $this->title = get_string('pluginname', 'block_configurable_reports');
            $this->config = new stdClass();
            $this->config->displayglobalreports = true;
        }
    }

    public function instance_allow_config() {
        return true;
    }

    /**
     * Where to add the block
     *
     * @return boolean
     **/
    public function applicable_formats() {
        return array('site' => true, 'course' => true, 'my' => true);
    }

    /**
     * Global Config?
     *
     * @return boolean
     **/
    public function has_config() {
        return true;
    }

    /**
     * More than one instance per page?
     *
     * @return boolean
     **/
    public function instance_allow_multiple() {
        return true;
    }

    /**
     * Gets the contents of the block (course view)
     *
     * @return object An object with the contents
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function get_content() {
        global $CFG, $DB, $COURSE, $USER, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        if (!isloggedin()) {
            return (object) [
                'text' => '',
                'footer' => ''
            ];
        }

        $course = $DB->get_record('course', array('id' => $COURSE->id));

        if (!$course) {
            print_error('coursedoesnotexists');
        }

        $renderable = new \block_configurable_reports\output\main($course, $this->config);
        $renderer = $this->page->get_renderer('block_configurable_reports');

        if (!empty($this->config->displaysinglereport)) {
            $download = optional_param('download', false, PARAM_BOOL);
            $format = optional_param('format', '', PARAM_ALPHA);
            $courseid = optional_param('courseid', null, PARAM_INT);

            // Single report view.
            if (!$report = $DB->get_record('block_configurable_reports', ['id' => $this->config->displaysinglereport])) {
                $this->content = (object) [
                    'text' => get_string('reportdoesnotexists', 'block_configurable_reports'),
                    'footer' => ''
                ];
                return $this->content;
            }

            if (!$this->config->displayglobalreports && $report->global) {
                return $this->content;
            }

            if ($courseid && $report->global) {
                $report->courseid = $courseid;
            } else {
                $courseid = $report->courseid;
            }

            // Force user login in course (SITE or Course).
            if ($course->id == SITEID) {
                $context = context_system::instance();
            } else {
                $context = context_course::instance($course->id);
            }

            require_once($CFG->dirroot.'/blocks/configurable_reports/locallib.php');
            require_once($CFG->dirroot.'/blocks/configurable_reports/report.class.php');
            require_once($CFG->dirroot.'/blocks/configurable_reports/reports/'.$report->type.'/report.class.php');

            $reportclassname = 'report_'.$report->type;
            $reportclass = new $reportclassname($report);

            if (!$reportclass->check_permissions($USER->id, $context)) {
                return $this->content;
            }
            $reportclass->check_filters_request();
            $reportclass->create_report();

            if (empty($this->config->displayfilter)) {
                $reportclass->filterform = null;
            }

            $blockinstancecfg = array(
                'displaychartonly' => !empty($this->config->displaychartonly),
                'legendbelowchart' => !empty($this->config->legendbelowchart),
            );
            ob_start();
            // Print the report HTML.
            $reportclass->print_report_page($this->page, $blockinstancecfg);
            $output = ob_get_contents();
            ob_end_clean();

            $main = $renderable->export_for_template($OUTPUT);
            $lastitem = end($main['items']);
            $managereporturl = $lastitem->url;

            if ($managereporturl && $managereporturl->get_path() == "/blocks/configurable_reports/managereport.php") {
                $output .= html_writer::div(
                    html_writer::link($managereporturl, $lastitem->name),
                    'centerpara'
                );
            }
            $this->content = (object) [
                'text' => $output,
                'footer' => ''
            ];
        } else {
            $this->content = (object) [
                'text' => $renderer->render($renderable),
                'footer' => ''
            ];
        }

        return $this->content;
    }

    public function cron() {
        global $CFG, $DB;

        $hour = get_config('block_configurable_reports', 'cron_hour');
        $min = get_config('block_configurable_reports', 'cron_minute');

        $date = usergetdate(time());
        $usertime = mktime($date['hours'], $date['minutes'], $date['seconds'], $date['mon'], $date['mday'], $date['year']);

        $crontime = mktime($hour, $min, $date['seconds'], $date['mon'], $date['mday'], $date['year']);

        if ( ($crontime - $usertime) < 0 ) {
            return false;
        }

        $lastcron = $DB->get_field('block', 'lastcron', array('name' => 'configurable_reports'));
        if (!$lastcron and ($lastcron + $this->cron < time()) ) {
            return false;
        }

        // Starting to run...
        require_once($CFG->dirroot."/blocks/configurable_reports/locallib.php");
        require_once($CFG->dirroot.'/blocks/configurable_reports/report.class.php');
        require_once($CFG->dirroot.'/blocks/configurable_reports/reports/sql/report.class.php');

        mtrace("\nConfigurable report (block)");

        $reports = $DB->get_records('block_configurable_reports');
        if ($reports) {
            foreach ($reports as $report) {
                // Running only SQL reports. $report->type == 'sql'.
                if ($report->type == 'sql' AND (!empty($report->cron) AND $report->cron == '1')) {
                    $reportclass = new report_sql($report);

                    // Execute it using $remotedb.
                    $starttime = microtime(true);
                    mtrace("\nExecuting query '$report->name'");

                    $components = cr_unserialize($reportclass->config->components);
                    $config = (isset($components['customsql']['config'])) ? $components['customsql']['config'] : new \stdclass;
                    $sql = $reportclass->prepare_sql($config->querysql);

                    $sqlqueries = explode(';', $sql);

                    foreach ($sqlqueries as $sql) {
                        mtrace(substr($sql, 0, 60)); // Show some SQL.
                        $results = $reportclass->execute_query($sql);
                        if ($results == 1) {
                            mtrace('...OK time='.round((microtime(true) - $starttime) * 1000).'mSec');
                        } else {
                            mtrace('Some SQL Error'.'\n');
                        }
                    }
                    unset($reportclass);
                }
            }
        }
        return true; // Finished OK.
    }

}
