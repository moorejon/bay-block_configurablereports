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

require_once($CFG->dirroot.'/lib/csvlib.class.php');

class export_fixedwidth {
    /**
     * @param $report
     * @param bool $download
     * @param string $pattern
     * @return bool|false|string
     */
    function export_report($report, $download = true, $pattern = '') {
        global $DB, $CFG;

        $txt = '';

        $patterndata = @json_decode($pattern);
        if (!$isvalid = (json_last_error() === JSON_ERROR_NONE)) {
            return false;
        }
        $numberoflines = 0;
        foreach ($patterndata as $item) {
            $data = [];
            $this->print_rows($data, [$item], $report, $numberoflines);

            foreach ($data as $index => $datum) {
                if (strpos($index, '%%') === 0) {
                    unset($data[$index]);
                }
            }
            array_walk_recursive($data, function($item, $key) use(&$txt) {
                $txt .= "$item\n";
            });

            $numberoflines = substr_count($txt, "\n");
        }

        $table = $report->table;
        $filename = 'report.txt';
        $headers = $table->head;

        $downloadfilename = clean_filename($filename);
        if ($download) {
            header('Content-disposition: attachment; filename=' . $downloadfilename);
            header('Content-type: application/text');
            echo $txt;
            exit;
        } else {
            $tmppath = '/temp';
            // Create a unique temporary filename to use for this schedule
            $filename = tempnam($CFG->dataroot.$tmppath, 'export_json_');
            file_put_contents($filename, json_encode($json));
            return $filename;
        }
    }

    public function print_rows(&$data, $rows, $report, $numberoflines, $hasparent = false) {
        foreach ($rows as $row) {
            $indexkey = null;
            if (isset($row->index)) {
                $indexkey = array_search(str_replace('_', ' ', strtolower($row->index)), $report->table->head);
            }
            if ($indexkey) {
                if ($fields = $this->parse_pattern($row->pattern)) {
                    foreach ($fields as $index => $field) {
                        $key = array_search($field['name'], $report->table->head);
                        $field['key'] = $key;
                        $fields[$index] = $field;
                    }
                }
                if (!empty($report->table->data)) {
                    foreach ($report->table->data as $line)  {
                        $linetxt = '';
                        $indexvalue = $this->clean_data($line[$indexkey]);

                        $counter  = false;

                        foreach ($fields as $field) {
                            if (is_numeric($field['key'])) {
                                $linetxt .= str_pad($this->clean_data($line[$field['key']]), $field['width']);
                            } else {
                                if ($field['name'] == '%%count%%') {
                                    $counter = true;
                                    if (isset($data['&&'.$indexvalue][$fields[0]['name']])) {
                                        $count = count($data['%%'.$indexvalue][$fields[0]['name']]) + 1;
                                    } else {
                                        $count = 1;
                                    }
                                    $linetxt .= str_pad($count, $field['width']);
                                } else {
                                    $linetxt .= str_pad($this->clean_data($field['name']), $field['width']);
                                }
                            }
                        }

                        // Top level output has to be single.
                        if ($hasparent) {
                            if ($counter) {
                                $data[$indexvalue][$fields[0]['name']] = $linetxt;
                                $data['%%'.$indexvalue][$fields[0]['name']][] = $linetxt;
                            } else {
                                $data[$indexvalue][$fields[0]['name']][] = $linetxt;
                            }

                        } else {
                            $data[$indexvalue][$fields[0]['name']] = $linetxt;
                        }
                    }
                }
            } else {
                if ($fields = $this->parse_pattern($row->pattern)) {
                    $linetxt = '';
                    foreach ($fields as $field) {
                        if ($field['name'] == '%%rowcount%%') {
                            $linetxt .= str_pad(($numberoflines+1), $field['width']);
                        } else {
                            $linetxt .= str_pad($field['name'], $field['width']);
                        }
                    }
                    $data[] = $linetxt;

                }
            }

            if ($this->has_child($row)) {
                $this->print_rows($data, $this->get_children($row), $report, $numberoflines, true);
            }
        }
    }

    /**
     * @param $row
     * @return bool
     */
    public function has_child($row) {
        return !empty($row->children);
    }

    /**
     * @param $row
     * @return mixed
     */
    public function get_children($row) {
        return $row->children;
    }

    /**
     * @param $tr
     * @return string|string[]
     */
    public function clean_data($tr) {
        return str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br($tr))));
    }

    /**
     * @param $pattern
     * @return array
     */
    public function parse_pattern($pattern) {
        $var = [];
        $arr = explode('|', $pattern);
        if (empty($arr)) {
            return $var;
        }
        foreach ($arr as $item) {
            $arr2 = explode(',', $item);
            if (count($arr2) != 2) {
                continue;
            }
            $var[] = ['name' => str_replace('_', ' ', strtolower($arr2[0])), 'width' => $arr2[1]];
        }
        return $var;
    }
}