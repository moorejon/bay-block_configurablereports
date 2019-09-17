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
 * Handles ajax interface for view reports
 *
 * @package   block_configurable_reports
 * @copyright 2019 MLC
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/templates', 'core/notification'], function ($, templates, notification) {

    return {
        init: function () {

            var self = this;

            $(document).ready(function() {
                if ($('#id_filter_subcategories').val() > 0) {
                    self.getCourses();
                }
                $(document).on('change', '#id_filter_subcategories', function (event) {
                    event.preventDefault();
                    self.getCourses();
                });
            });
        },
        /**
         * Called when a category is selected
         */
        getCourses: function () {
            var self = this;
            $('#id_filter_courses').html('');

            var selectedCategoryId = $("#id_filter_subcategories").val();

            // then do AJAX call to getCourses.php script
            $.ajax({
                type: "GET",
                url: 'get_courses_in_category.php?category=' + selectedCategoryId,
                success: function (data) {
                    self.replaceTemplate('#id_filter_courses', 'block_configurable_reports/courses', data);
                }
            });
        },
        replaceTemplate: function (selector, template, data) {
            templates.render(template, data).done(function (html, js) {
                $(selector).html(html);
                templates.runTemplateJS(js);
            }).fail(notification.exception);
        }
    };
});
