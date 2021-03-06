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

require_once($CFG->dirroot.'/blocks/configurable_reports/plugin.class.php');

class plugin_pie extends plugin_base{

    public function init() {
        $this->fullname = get_string('pie', 'block_configurable_reports');
        $this->form = true;
        $this->ordering = true;
        $this->reporttypes = array('courses', 'sql', 'users', 'timeline', 'categories');
    }

    public function summary($data) {
        return get_string('piesummary', 'block_configurable_reports');
    }

    // Data -> Plugin configuration data.
    public function execute($id, $data, $finalreport) {
        global $DB, $CFG;

        $series = array();
        if ($finalreport) {
            foreach ($finalreport as $r) {
                if ($data->areaname == $data->areavalue) {
                    $hash = md5(strtolower($r[$data->areaname]));
                    if (isset($series[0][$hash])) {
                        $series[1][$hash] += 1;
                    } else {
                        $series[0][$hash] = str_replace(',', '', $r[$data->areaname]);
                        $series[1][$hash] = 1;
                    }

                } else if (!isset($data->group) || ! $data->group) {
                    $series[0][] = str_replace(',', '', $r[$data->areaname]);
                    $series[1][] = (isset($r[$data->areavalue]) && is_numeric($r[$data->areavalue])) ? $r[$data->areavalue] : 0;
                } else {
                    $hash = md5(strtolower($r[$data->areaname]));
                    if (isset($series[0][$hash])) {
                        $series[1][$hash] += (isset($r[$data->areavalue]) && is_numeric($r[$data->areavalue])) ? $r[$data->areavalue] : 0;
                    } else {
                        $series[0][$hash] = str_replace(',', '', $r[$data->areaname]);
                        $series[1][$hash] = (isset($r[$data->areavalue]) && is_numeric($r[$data->areavalue])) ? $r[$data->areavalue] : 0;
                    }
                }
            }
        }

        // Custom sort.
        $sortorder = [];
        $colors = [];

        for ($i = 0; $i < 5; $i++) {
            if (!empty($data->{'label'.$i})) {
                $target = $data->{'label'.$i};
                $colorcode = ltrim($data->{'labelcolor'.$i}, '#');
                $sortorder[$target] = $colorcode;
            }
        }

        $serie0sorted = [];
        $serie1sorted = [];
        $i = 0;

        foreach ($sortorder as $item => $color) {
            foreach ($series[0] as $index => $serie) {
                $serie = strip_tags($serie);
                if ($item == $serie) {
                    $serie0sorted[] = $serie;
                    $serie1sorted[] = $series[1][$index];
                    unset($series[0][$index]);
                    unset($series[1][$index]);

                    $colors[$i] = implode('|', array_map(function($c){return hexdec(str_pad($c, 2, $c));}, str_split($color, strlen($color) > 4 ? 2 : 1)));
                    $i++;
                }
            }
        }

        if (!empty($series[0])) {
            foreach ($series[0] as $index => $serie) {
                $serie0sorted[] = strip_tags($series[0][$index]);
                $serie1sorted[] = $series[1][$index];
            }
        }

        $serie0 = base64_encode(strip_tags(implode(',', $serie0sorted)));
        $serie1 = base64_encode(implode(',', $serie1sorted));
        $colorpalette = base64_encode(implode(',', $colors));
        $legendbelowchart = !empty($this->report->blockinstancecfg['legendbelowchart']);

        return $CFG->wwwroot.'/blocks/configurable_reports/components/plot/pie/graph.php?reportid='. $this->report->id.
            '&id='.$id.'&serie0='.$serie0.'&serie1='.$serie1.'&colorpalette='.$colorpalette.'&legendbelowchart='.$legendbelowchart;
    }

    public function get_series($data) {
        $serie0 = required_param('serie0', PARAM_RAW);
        $serie1 = required_param('serie1', PARAM_RAW);

        return array(explode(',', base64_decode($serie0)), explode(',', base64_decode($serie1)));
    }

    public function get_color_palette($data) {
        if ($colorpalette = optional_param('colorpalette', '', PARAM_RAW)) {
            $colorpalette = explode(',', base64_decode($colorpalette));
            foreach ($colorpalette as $index => $item) {
                $colorpalette[$index] = explode('|', $item);
            }
            return $colorpalette;
        }
        return null;
    }
}
