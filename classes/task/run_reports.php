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
 * A scheduled task for Block Configurable Reports, to run the scheduled reports.
 *
 * @package block_configurable_reports
 * @copyright 2019 My Learning Consultants
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_configurable_reports\task;

defined('MOODLE_INTERNAL') || die();


class run_reports extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('crontask', 'block_configurable_reports');
    }

    /**
     * Function to be run periodically according to the moodle cron
     * This function searches for things that need to be done, such
     * as sending out mail, toggling flags etc ...
     *
     * Runs any automatically scheduled reports daily, weekly, monthly or quarterly.
     *
     * @return boolean
     */
    public function execute() {
        global $CFG, $DB;

        require_once(dirname(__FILE__) . '/../../locallib.php');

        $timenow = time();

        list($startofthisweek) = block_configurable_reports_get_week_starts($timenow);
        list($startofthismonth) = block_configurable_reports_get_month_starts($timenow);
        list($startofthisquarter) = block_configurable_reports_get_quarter_starts($timenow);

        // Get daily scheduled reports.
        $dailyreportstorun = block_configurable_reports_get_ready_to_run_daily_reports($timenow);

        // Get weekly and monthly scheduled reports.
        $scheduledreportstorun = $DB->get_records_select('block_configurable_reports',
                        "enableschedule = 1 AND emailto <> '' AND ((frequency = 'weekly' AND lastrun < :startofthisweek) OR
                                 (frequency = 'monthly' AND lastrun < :startofthismonth) OR
                                 (frequency = 'quarterly' AND lastrun < :startofthisquarter))",
                array('startofthisweek' => $startofthisweek,
                        'startofthismonth' => $startofthismonth,
                        'startofthisquarter' => $startofthisquarter), 'lastrun');

        // All reports ready to run.
        $reportstorun = array_merge($dailyreportstorun, $scheduledreportstorun);

        foreach ($reportstorun as $report) {
            try {
                mtrace("... Running report " . $this->block_configurable_reports_run_report($report));
            } catch (\Exception $e) {
                mtrace("... REPORT FAILED " . $e->getMessage());
            }
        }
    }

    public function block_configurable_reports_run_report($report) {
        global $CFG, $DB;

        // Large exports are likely to take their time and memory.
        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_EXTRA);

        require_once($CFG->dirroot.'/blocks/configurable_reports/report.class.php');
        require_once($CFG->dirroot.'/blocks/configurable_reports/reports/'.$report->type.'/report.class.php');

        $reportclassname = 'report_'.$report->type;
        $emails = preg_split("/[\s,;]+/", $report->emailto);
        list ($sql, $params) = $DB->get_in_or_equal($emails);
        $users = $DB->get_records_select('user', 'email ' . $sql, $params);

        foreach ($users as $user) {
            $reportclass = new $reportclassname($report, $user);
            $reportclass->create_report();

            $exports = explode(',', $report->export);
            $files = array();
            if ($reportclass->totalrecords) {
                foreach ($exports as $format) {
                    if ($format) {
                        $exportplugin = $CFG->dirroot . '/blocks/configurable_reports/export/' . $format . '/export.php';
                        if (file_exists($exportplugin)) {
                            require_once($exportplugin);
                            $classname = 'export_' . $format;
                            $export = new $classname();
                            if ($format == 'fixedwidth') {
                                $files['txt'] = $export->export_report($reportclass->finalreport, false,
                                        $reportclass->config->fixedwidthpattern);
                            } else {
                                if ($file = $export->export_report($reportclass->finalreport, false)) {
                                    $files[$format] = $file;
                                }
                            }
                        }
                    }
                }
            }
            $this->block_configurable_reports_email_report($reportclass, $files);
        }
        $report->lastrun = time();
        $DB->update_record('block_configurable_reports', $report);
    }

    public function block_configurable_reports_email_report($reportclass, $files = null) {
        $from = get_string('fromname', 'block_configurable_reports');

        // Get the message.
        $attachment = null;
        $attachname = null;
        if ($files) {
            $message = $this->block_configurable_reports_get_message($reportclass->config);
            $zip = new \ZipArchive;
            $attachname = 'report_download.zip';
            $filename = $this->get_temp_path($attachname);
            $zip->open($filename, \ZipArchive::CREATE);
            foreach ($files as $extension => $file) {
                if ($extension == 'xls') {
                    $extension = 'xlsx';
                }
                $content = file_get_contents($file);
                $zip->addFromString('report.'.$extension, $content);
            }
            $zip->close();
            $attachment = $filename;
        } else {
            $message = $this->block_configurable_reports_get_message_no_data($reportclass->config);
        }

        $messageresult = email_to_user($reportclass->currentuser, $from, $message->subject, $message->messagetext, $message->messagehtml, $attachment, $attachname);
        if (!$messageresult) {
            mtrace(get_string('emailsentfailed', 'block_configurable_reports', $reportclass->user->email));
        }
    }

    public function block_configurable_reports_get_message_no_data($report) {
        // Construct subject.
        $subject = get_string('emailsubject', 'block_configurable_reports',
                $report->name);
        $url = new \moodle_url('/blocks/configurable_reports/viewreport.php', array('id' => $report->id));
        $link = \html_writer::tag('a', $url, array('href' => $url));
        $fullmessage = \html_writer::tag('p', get_string('nodatareturned', 'block_configurable_reports') . ' ' . $link);
        $fullmessagehtml = $fullmessage;

        // Create the message object.
        $message = new \stdClass();
        $message->subject           = $subject;
        $message->messagetext       = $fullmessage;
        $message->messagehtml       = $fullmessagehtml;
        return $message;
    }

    public function block_configurable_reports_get_message($report) {
        // Construct subject.
        $subject = get_string('emailsubject', 'block_configurable_reports',
                $report->name);
        $url = new \moodle_url('/blocks/configurable_reports/viewreport.php', array('id' => $report->id));
        $link = \html_writer::tag('a', $url, array('href' => $url));
        $fullmessage = \html_writer::tag('p', get_string('datareturned', 'block_configurable_reports') . ' ' . $link);
        $fullmessagehtml = $fullmessage;

        // Create the message object.
        $message = new \stdClass();
        $message->subject = $subject;
        $message->messagetext = $fullmessage;
        $message->messagehtml = $fullmessagehtml;
        return $message;
    }

    function get_temp_path($filename) {
        global $CFG;

        // Create a unique temporary filename to use for this schedule
        $path = tempnam($CFG->tempdir, $filename);
        return $path;
    }
}