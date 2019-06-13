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

require_once($CFG->dirroot . '/lib/excellib.class.php');

class export_xls {
    function export_report($report, $download = true) {
        global $DB, $CFG;

        $table = $report->table;
        $matrix = array();
        $filename = 'report_excel.xls';

        if (!empty($table->head)) {
            $countcols = count($table->head);
            $keys = array_keys($table->head);
            $lastkey = end($keys);
            foreach ($table->head as $key => $heading) {
                $matrix[0][$key] = str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br($heading))));
            }
        }

        if (!empty($table->data)) {
            foreach ($table->data as $rkey => $row) {
                foreach ($row as $key => $item) {
                    $matrix[$rkey + 1][$key] = str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br($item))));
                }
            }
        }

        $downloadfilename = clean_filename($filename);
        // Creating a workbook.
        if ($download) {
            $workbook = new \MoodleExcelWorkbook("-");
        } else {
            $workbook = new ScheduledWorkbook("-");
        }
        // Sending HTTP headers.
        $workbook->send($downloadfilename);
        // Adding the worksheet.
        $myxls = $workbook->add_worksheet($filename);

        foreach ($matrix as $ri => $col) {
            foreach ($col as $ci => $cv) {
                if (!is_numeric($cv)) {
                    $myxls->write_string($ri, $ci, $cv);
                } else {
                    $myxls->write_number($ri, $ci, $cv);
                }
            }
        }

        $workbook->close();
        if ($download) {
            exit;
        } else {
            return $workbook->filelocation;
        }
    }
}

class ScheduledWorkbook extends MoodleExcelWorkbook {

    public $filelocation = '';

    public function close() {
        global $CFG;

        foreach ($this->objPHPExcel->getAllSheets() as $sheet){
            $sheet->setSelectedCells('A1');
        }
        $this->objPHPExcel->setActiveSheetIndex(0);

        $tmppath = '/temp';
        // Create a unique temporary filename to use for this schedule
        $filename = tempnam($CFG->dataroot.$tmppath, 'export_report_');

        $objWriter = PHPExcel_IOFactory::createWriter($this->objPHPExcel, $this->type);
        $objWriter->save($filename);
        $this->filelocation = $filename;
    }
}

