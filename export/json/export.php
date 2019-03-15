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
 * @author  : Juan leyva <http://www.twitter.com/jleyvadelgado>
 * @date    : 2009
 * @param $report
 */

class export_json {
    function export_report($report, $download = true) {
        global $CFG;

        $table = $report->table;
        $filename = 'report.json';
        $json = [];
        $headers = $table->head;
        foreach ($table->data as $data) {
            $jsonObject = [];
            foreach ($data as $index => $value) {
                $jsonObject[$headers[$index]] = $value;
            }
            $json[] = $jsonObject;
        }

        $downloadfilename = clean_filename($filename);
        if ($download) {
            header('Content-disposition: attachment; filename=' . $downloadfilename);
            header('Content-type: application/json');
            echo json_encode($json);
            exit;
        } else {
            $tmppath = '/temp';
            // Create a unique temporary filename to use for this schedule
            $filename = tempnam($CFG->dataroot.$tmppath, 'export_json_');
            file_put_contents($filename, json_encode($json));
            return $filename;
        }
    }
}