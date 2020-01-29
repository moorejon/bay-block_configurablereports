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
require_once($CFG->dirroot.'/lib/classes/output/mustache_engine.php');

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

$params = array('process' => 1);
$thispageurl = $reportclass->generate_url('/blocks/configurable_reports/send_notifications.php', $params);
$redirecturl = $reportclass->generate_url('/blocks/configurable_reports/viewreport.php');

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

    $groups = array();
    if ($report->notificationgrouping) {
        $groups = explode('->', $report->notificationgrouping);
    }

    // Get recipients from report.
    $recipients = [];
    foreach ($reportclass->finalreport->table->data as $data) {
        $recipients[$data[$notificationemailfieldindex]] = 0;
    }

    if (!$recipients) {
        print_error('norecepients', 'block_configurable_reports');
    }

    $noreply = core_user::get_noreply_user();

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('sendnotifications', 'block_configurable_reports') . ' - ' . $report->name);

    $progressbar = new progress_bar();
    $progressbar->create();// prints the HTML code of the progress bar

    $done = 0;
    $strinprogress = get_string('notificationssending', 'block_configurable_reports');

    $head = $reportclass->finalreport->table->head;
    $parent = null;
    $grouparray = array();
    foreach ($groups as $group) {
        $groupobj = new stdClass();
        $groupobj->parent = $parent;
        $grouparray[$group] = $groupobj;
        $parent = $group;
    }
    foreach ($recipients as $recipient => $status) {
        $processeddata = new stdClass();
        foreach ($reportclass->finalreport->table->data as $data) {
            if ($data[$notificationemailfieldindex] != $recipient) {
                continue;
            }
            foreach ($data as $index => $datum) {
                $column = $head[$index];
                $datumobj = new stdClass();
                $valueattribute = $column . 'value';
                $datumobj->$valueattribute = $datum;
                // Handle data that has been referenced in group links
                if (array_key_exists($column, $grouparray)) {
                    $parent = $grouparray[$column]->parent;
                    // If the datum has a parent defined place it inside the parent
                    if (!empty($parent)) {
                        // Get index of parent column in this row
                        $parentindex = array_search($parent, $head);
                        $parentfromcurrentrow = $data[$parentindex];
                        $storedparentvalue = $parent . 'value';
                        $parentobj = null;
                        foreach ($processeddata->$parent as $target) {
                            if ($target->$storedparentvalue == $parentfromcurrentrow) {
                                $parentobj = $target;
                                break;
                            }
                        }
                        if (!isset($parentobj->{$column})) {
                            $parentobj->{$column} = array($datumobj);
                        } else {
                            $found = false;
                            foreach ($parentobj->{$column} as $childobj) {
                                if ($childobj->{$valueattribute} == $datumobj->{$valueattribute}) {
                                    $found = true;
                                    break;
                                }
                            }
                            if (!$found) {
                                $parentobj->{$column}[] = $datumobj;
                            }
                        }
                    } else {
                        if (!isset($processeddata->{$column})) {
                            $processeddata->{$column} = array($datumobj);
                        } else {
                            $found = false;
                            foreach ($processeddata->{$column} as $childobj) {
                                if ($childobj->{$valueattribute} == $datumobj->{$valueattribute}) {
                                    $found = true;
                                    break;
                                }
                            }
                            if (!$found) {
                                $processeddata->{$column}[] = $datumobj;
                            }
                        }
                    }
                } else if (!isset($processeddata->{$column})) {
                    $processeddata->{$column} = $datumobj;
                }
            }
        }

        $mustache = new \core\output\mustache_engine();
        $html = $mustache->render($report->notificationtemplate, $processeddata);
        $user = core_user::get_user_by_email($recipient);
        if ($user) {
            $user->mailformat = FORMAT_HTML;
            email_to_user($user, $noreply, $report->notificationsubject, '', $html);
        }

        $recipients[$recipient] = 1;

        $done++;
        if (!is_null($progressbar)) {
            $donepercent = floor($done / count($recipients) * 100);
            $progressbar->update_full($donepercent, $strinprogress);
        }
    }

    if (!is_null($progressbar)) {
        $progressbar->update_full(100, get_string('notificationssent', 'block_configurable_reports'));
    }
    echo $OUTPUT->continue_button($redirecturl, 'get');
    echo $OUTPUT->footer();
} else {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('sendnotifications', 'block_configurable_reports') . ' - ' . $report->name);
    $renderer = $PAGE->get_renderer('block_configurable_reports');
    echo $renderer->confirm(get_string('confirm'), get_string('sendnotificationconfirm', 'block_configurable_reports'), $thispageurl, $redirecturl);
    echo $OUTPUT->footer();
}