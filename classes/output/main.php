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
 * Class containing data for configurable_reports block.
 *
 * @package    block_configurable_reports
 * @copyright  2019 MLC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_configurable_reports\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use templatable;

require_once($CFG->dirroot."/blocks/configurable_reports/locallib.php");

/**
 * Class containing data for configurable_reports block.
 *
 * @copyright  2018 Ryan Wyllie <ryan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class main implements renderable, templatable {
    /**
     * @var string The current filter preference
     */
    public $course;

    /**
     * @var \stdClass block config
     */
    public $config;

    /**
     * @var \stdClass[] items for display
     */
    public $items;

    /**
     * main constructor.
     *
     * @param \stdClass $course course object
     */
    public function __construct($course, $config) {
        $this->course = $course;
        $this->config = $config;
    }

    /**
     * Get Site (Shared) reports
     * @param \stdClass $course  moodle course object
     * @param \context $context  moodle context object
     *
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    private function get_site_reports($course, $context) {
        global $DB, $USER;

        if (!empty($this->config->displayglobalreports)) {
            $reports = $DB->get_records('block_configurable_reports', array('global' => 1), 'name ASC');

            if ($reports) {
                foreach ($reports as $report) {
                    if ($report->visible && cr_check_report_permissions($report, $USER->id, $context)) {
                        $rname = format_string($report->name);
                        $params = ['id' => $report->id, 'courseid' => $course->id];
                        $url = new \moodle_url('/blocks/configurable_reports/viewreport.php', $params);
                        $this->items[] = (object) ['url' => $url, 'name' => $rname];
                    }
                }
            }
        }
    }

    /**
     * Get Course reports
     * @param \stdClass $course  moodle course object
     * @param \context $context moodle context object
     *
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    private function get_course_reports($course, $context) {
        global $DB, $PAGE, $USER;
        if (!property_exists($this, 'config')
            or !isset($this->config->displayreportslist)
            or $this->config->displayreportslist) {
            $reports = $DB->get_records('block_configurable_reports', array('courseid' => $course->id), 'name ASC');

            if ($reports) {
                foreach ($reports as $report) {
                    if (!$report->global && $report->visible && cr_check_report_permissions($report, $USER->id, $context)) {
                        $rname = format_string($report->name);
                        $params = ['id' => $report->id, 'courseid' => $course->id];
                        $url = new \moodle_url('/blocks/configurable_reports/viewreport.php', $params);
                        $urlid = $PAGE->url->get_param('id');
                        $selected = ($PAGE->url->compare($url, URL_MATCH_BASE)
                            && !empty($urlid) && $urlid == $report->id) ? true : false;
                        $this->items[] = (object) [
                            'url' => $url,
                            'name' => $rname,
                            'selected' => $selected
                        ];
                    }
                }
            }
        }
    }

    /**
     * Get manage link
     * @param \stdClass $course  moodle course object
     * @param \context $context moodle context object
     *
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    private function get_manage_link($course, $context) {
        global $PAGE;
        if (has_capability('block/configurable_reports:managereports', $context)
            || has_capability('block/configurable_reports:manageownreports', $context)) {
            $url = new \moodle_url('/blocks/configurable_reports/managereport.php', ['courseid' => $course->id]);
            $linktext = get_string('managereports', 'block_configurable_reports');
            $selected = $PAGE->pagetype === 'blocks-configurable_reports-managereport' ? true : false;
            $this->items[] = (object) [
                'url' => $url,
                'name' => $linktext,
                'divider' => !empty($this->items) ? true : false,
                'selected' => $selected
            ];
        }
    }


    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return \stdClass
     */
    public function export_for_template(renderer_base $output) {

        if ($this->course->id == SITEID) {
            $context = \context_system::instance();
        } else {
            $context = \context_course::instance($this->course->id);
        }

        $this->get_site_reports($this->course, $context);
        $this->get_course_reports($this->course, $context);
        $this->get_manage_link($this->course, $context);

        return [
            'items' => $this->items
        ];
    }
}