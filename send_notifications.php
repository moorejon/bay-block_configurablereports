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
 *
 * @package    block_configurable_reports
 * @copyright  2020 Michael Gardener <mgardener@cissq.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_OUTPUT_BUFFERING', true); // progress bar is used here

require_once("../../config.php");
require_once($CFG->dirroot."/blocks/configurable_reports/locallib.php");
require_once($CFG->dirroot.'/blocks/configurable_reports/report.class.php');

$id = required_param('id', PARAM_INT);
$process = optional_param('process', 0, PARAM_INT);

if (!$report = $DB->get_record('block_configurable_reports', array('id' => $id))) {
    print_error('reportdoesnotexists', 'block_configurable_reports');
}

if (!$course = $DB->get_record('course', array('id' => $report->courseid))) {
    print_error('nosuchcourseid', 'block_configurable_reports');
}

if (!$report->enablepersonalizednotification) {
    print_error('notenabledpersonalizednotification', 'block_configurable_reports');
}

if (!$report->notificationemailfield) {
    print_error('nonotificationemailfield', 'block_configurable_reports');
}

require_once($CFG->dirroot.'/blocks/configurable_reports/reports/'.$report->type.'/report.class.php');

// Force user login in course (SITE or Course).
if ($course->id == SITEID) {
    require_login();
    $context = context_system::instance();
} else {
    require_login($course->id);
    $context = context_course::instance($course->id);
}

$reportclassname = 'report_'.$report->type;
$reportclass = new $reportclassname($report);

if (!$reportclass->check_permissions($USER->id, $context)) {
    print_error('badpermissions', 'block_configurable_reports');
}

$thispageurl = new moodle_url('/blocks/configurable_reports/send_notifications.php', ['id'=> $report->id, 'courseid' => $course->id]);
$redirecturl = new moodle_url('/blocks/configurable_reports/viewreport.php', ['id'=> $report->id, 'courseid' => $course->id]);

$hasmanageallcap = has_capability('block/configurable_reports:managereports', $context);
$hasmanageowncap = has_capability('block/configurable_reports:manageownreports', $context);

if ($hasmanageallcap || ($hasmanageowncap && $report->ownerid == $USER->id)) {
    $managereporturl = new \moodle_url('/blocks/configurable_reports/managereport.php', ['courseid' => $report->courseid]);
    $PAGE->navbar->add(get_string('managereports', 'block_configurable_reports'), $managereporturl);
    $PAGE->navbar->add($report->name);
} else {
    print_error('badpermissions', 'block_configurable_reports');
}

$PAGE->set_cacheable(false);    // progress bar is used here
$PAGE->set_context($context);
$PAGE->set_url($thispageurl);
$PAGE->set_title(get_string('sendnotifications', 'block_configurable_reports'));
$PAGE->set_pagelayout('incourse');

if ($process) {
    $reportclass->create_report();

    $notificationemailfieldindex = null;
    foreach ($reportclass->finalreport->table->head as $key => $c) {
        if ($report->notificationemailfield == $c) {
            $notificationemailfieldindex = $key;
            break;
        }
    }

    if (is_null($notificationemailfieldindex)) {
        print_error('noemailfield', 'block_configurable_reports');
    }

    // Get receoients frim report.
    $recepients = [];
    foreach ($reportclass->finalreport->table->data as $data) {
        $recepients[$data[$notificationemailfieldindex]] = 0;
    }

    if (!$recepients) {
        print_error('norecepients', 'block_configurable_reports');
    }

    // Email Template.
    $templatename = 'notification_' . $report->id . '_' . uniqid();
    $fulltemplatepath = $CFG->dirroot . "/blocks/configurable_reports/templates/" . $templatename . ".mustache";

    if ($handle = fopen($fulltemplatepath, 'w')) {
        fwrite($handle, $report->notificationtemplate);
        fclose($handle);
    }

    $noreply = core_user::get_noreply_user();

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('sendnotifications', 'block_configurable_reports') . ' - ' . $report->name);

    $progressbar = new progress_bar();
    $progressbar->create();// prints the HTML code of the progress bar

    $done = 0;
    $strinprogress = get_string('notificationssending', 'block_configurable_reports');

    foreach ($reportclass->finalreport->table->data as $data) {
        $templatecontext = new stdClass();

        $recepient = $data[$notificationemailfieldindex];

        if (!empty($recepients[$recepient])) {
            continue;
        }

        foreach ($data as $index => $datum) {
            $templatecontext->{$reportclass->finalreport->table->head[$index]} = $datum;
        }

        $html = $OUTPUT->render_from_template('block_configurable_reports/' . $templatename, $templatecontext);
        $user = core_user::get_user_by_email($recepient);
        $user->mailformat = FORMAT_HTML;

        email_to_user($user, $noreply, $report->notificationsubject, '', $html);

        $recepients[$recepient] = 1;

        $done++;
        if (!is_null($progressbar)) {
            $donepercent = floor($done / count($recepients) * 100);
            $progressbar->update_full($donepercent, $strinprogress);
        }
    }

    if (!is_null($progressbar)) {
        $progressbar->update_full(100, get_string('notificationssent', 'block_configurable_reports'));
    }
    unlink($fulltemplatepath);
    echo $OUTPUT->continue_button($redirecturl, 'get');
    echo $OUTPUT->footer();
} else {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('sendnotifications', 'block_configurable_reports') . ' - ' . $report->name);
    $thispageurl->param('process', 1);
    echo $OUTPUT->confirm(get_string('sendnotificationconfirm', 'block_configurable_reports') , $thispageurl,  $redirecturl);
    echo $OUTPUT->footer();
}