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
 * Configurable reports.
 *
 * @package   block_configurable_reports
 * @copyright 2019 MLC
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->libdir . '/coursecatlib.php');

$categoryid = required_param('category', PARAM_INT);

$category = coursecat::get($categoryid);
$courses = $category->get_courses(['recursive' => true, 'sort' => ['fullname' => 1]]);
foreach ($courses as $course) {
    $c = new stdClass();
    $c->id = $course->id;
    $c->fullname = $course->fullname;
    $courselist[] = $c;
}
echo json_encode(['courses' => $courselist]);
