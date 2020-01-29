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
 * configurable_reports block rendrer.
 *
 * @package    block_configurable_reports
 * @copyright  2019 MLC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_configurable_reports\output;

defined('MOODLE_INTERNAL') || die;

use plugin_renderer_base;
use renderable;

/**
 * configurable_reports block renderer.
 *
 * @package    block_configurable_reports
 * @copyright  2019 MLC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {
    /**
     * Return the main content for the block timeline.
     *
     * @param main $main The main renderable
     *
     * @return string HTML string
     * @throws \moodle_exception
     */
    public function render_main(main $main) {
        return $this->render_from_template('block_configurable_reports/main', $main->export_for_template($this));
    }

    /**
     * Returns a modal confirmation box using get parameter urls
     *
     * @param $header Header message
     * @param $message Modal message
     * @param $confirmurl URL for confirm button
     * @param $cancelurl URL for cancel button
     * @return bool|string
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function confirm($header, $message, $confirmurl, $cancelurl) {
        $confirm = new \stdClass();
        $confirm->classes = "btn-primary";
        $confirm->url = htmlspecialchars_decode($confirmurl);
        $confirm->text = get_string('confirm');

        $cancel = new \stdClass();
        $cancel->classes = "btn-secondary";
        $cancel->url = htmlspecialchars_decode($cancelurl);
        $cancel->text = get_string('cancel');

        $context = array(
                'header' => $header,
                'message' => $message,
                'buttons' => array(
                        $confirm,
                        $cancel
                )
        );

        return $this->render_from_template('block_configurable_reports/confirm', $context);
    }
}