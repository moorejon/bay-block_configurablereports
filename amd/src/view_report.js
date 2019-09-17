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

define(['jquery', 'core/templates', 'core/notification', 'core/ajax',], function ($, templates, notification, ajax) {

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

                $('#id_prefsave').click(function(event) {
                    event.preventDefault();

                    var form = $(this).closest("form");
                    var prefname = $('input[name=prefname]');
                    var presaved = $('select[name=presaved]');

                    // Ajax parameters.
                    var id = prefname.data('id');
                    var reportid = $('input[name=id]').val();
                    var name =  prefname.val();
                    var parameters = [];

                    var disregard = ['id', 'courseid', 'embedded', 'sesskey', '_qf__report_edit_form',
                        'mform_isexpanded_id_general', 'prefname', 'presaved'];
                    var formelements = form.serializeArray();

                    $.each(formelements, function(index, field) {
                        if ($.inArray(field.name, disregard) === -1) {
                            parameters.push(field);
                        }
                    });

                    ajax.call([{
                        methodname: 'block_configurable_reports_update_filter_preferences',
                        args: {
                            id: id,
                            reportid: reportid,
                            name: name,
                            parameters: JSON.stringify(parameters)
                        }
                    }])[0].done(function(data) {
                        if (data == true) {
                            presaved.append('<option value="" selected="selected">' + name + '</option>');
                            $('#id_presaved').show().removeAttr('hidden');
                        } else {
                            notification.alert(null, 'Error!');
                        }
                    }).fail(notification.exception);
                });

                $('#id_presaved').change(function() {
                    var preferenceid = $(this).val();

                    if (preferenceid > 0) {
                        ajax.call([{
                            methodname: 'block_configurable_reports_get_filter_preferences',
                            args: {
                                id: preferenceid,
                            }
                        }])[0].done(function(data) {
                            if (data === false) {
                                notification.alert(null, 'Error!');
                            } else {
                                var filter = JSON.parse(data.filter);
                                $.each(filter, function(index, field) {
                                    var elem = $('#id_' + field.name);
                                    if (elem.length > 0) {
                                        elem.val(field.value);
                                    }
                                });
                            }
                        }).fail(notification.exception);
                    }
                });

            });
        },
        /**
         * Called when a category is selected
         */
        getCourses: function() {
            var self = this;
            var selectedcourseid = $('#id_filter_courses').val();
            $('#id_filter_courses').html('');

            var selectedCategoryId = $("#id_filter_subcategories").val();

            // then do AJAX call to getCourses.php script
            $.ajax({
                type: "GET",
                url: 'get_courses_in_category.php?category=' + selectedCategoryId,
                success: function (data) {
                    self.replaceTemplate('#id_filter_courses', 'block_configurable_reports/courses', data, selectedcourseid);
                }
            });
        },
        replaceTemplate: function(selector, template, data, selectedcourseid) {
            templates.render(template, data).done(function(html, js) {
                $(selector).html(html);
                templates.runTemplateJS(js);
                if (selectedcourseid) {
                    if ($("#id_filter_courses option[value='" + selectedcourseid + "']").length !== 0 ) {
                        $('#id_filter_courses').val(selectedcourseid);
                    }
                }
            }).fail(notification.exception);
        }
    };
});
