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

define(['jquery', 'core/templates', 'core/notification', 'core/ajax', 'core/str'], function ($, templates, notification, ajax, str) {

    return {
        init: function () {

            var self = this;

            var updatefilterpreferences = function(form, id, reportid, name, preflistelem) {
                var disregard = ['id', 'courseid', 'embedded', 'sesskey', '_qf__report_edit_form',
                    'mform_isexpanded_id_general', 'prefname', 'presaved', 'prefdelete', 'prefdefault'];
                var formelements = form.serializeArray();
                var parameters = [];
                var defaultfilter = 0;

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
                        parameters: JSON.stringify(parameters),
                        defaultfilter: defaultfilter,
                        action: 'update',
                    }
                }])[0].done(function(data) {
                    if (data.success === true) {
                        if (preflistelem !== null) {
                            preflistelem.append('<option value="' + data.id + '" selected="selected">' + name + '</option>');
                        }
                        $('#id_presaved').show().removeAttr('hidden');
                        $('#id_prefupdate').show().removeAttr('hidden');
                        $('#id_prefdelete').show().removeAttr('hidden');
                        $('#id_prefdefault').show().removeAttr('hidden');
                    } else {
                        notification.alert(null, data.msg);
                    }
                }).fail(notification.exception);
            };

            $(document).ready(function() {
                if ($('#id_filter_subcategories').val() > 0) {
                    self.getCourses();
                }
                $(document).on('change', '#id_filter_subcategories', function(event) {
                    event.preventDefault();
                    self.getCourses();
                });


                $('#id_prefsave').click(function(event) {
                    event.preventDefault();

                    var form = $(this).closest("form");
                    var prefname = $('input[name=prefname]');
                    var presaved = $('select[name=presaved]');

                    // Ajax parameters.
                    var reportid = $('input[name=id]').val();
                    var name = prefname.val().trim();

                    if (name === '') {
                        return;
                    }

                    updatefilterpreferences(form, 0, reportid, name, presaved);
                });

                $('#id_prefupdate').click(function(event) {
                    event.preventDefault();

                    var form = $(this).closest("form");
                    var prefname = $('input[name=prefname]');
                    var presaved = $('select[name=presaved]');

                    // Ajax parameters.
                    var id = presaved.val();
                    var reportid = $('input[name=id]').val();

                    updatefilterpreferences(form, id, reportid, '', null);
                });

                $('#id_presaved').change(function() {
                    var form = $(this).closest("form");
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

                            form.submit();

                        }).fail(notification.exception);
                    }
                });

                $('#id_prefdelete').click(function(event) {
                    event.preventDefault();

                    var id = $('select[name=presaved]').val();
                    var reportid = $('input[name=id]').val();
                    var name = $('select[name=presaved] option:selected').text().trim();

                    if (!id) {
                        return;
                    }

                    ajax.call([{
                        methodname: 'block_configurable_reports_update_filter_preferences',
                        args: {
                            id: id,
                            reportid: reportid,
                            name: name,
                            parameters: '',
                            defaultfilter: 0,
                            action: 'delete'
                        }
                    }])[0].done(function(data) {
                        if (data.success === true) {
                            $('select[name=presaved] option[value=' + id + ']').remove();
                        } else {
                            notification.alert(null, data.msg);
                        }
                    }).fail(notification.exception);
                });

                $('#id_prefdefault').click(function(event) {
                    event.preventDefault();

                    var id = $('select[name=presaved]').val();
                    var reportid = $('input[name=id]').val();
                    var name = $('select[name=presaved] option:selected').text().trim();

                    if (!id) {
                        return;
                    }

                    ajax.call([{
                        methodname: 'block_configurable_reports_update_filter_preferences',
                        args: {
                            id: id,
                            reportid: reportid,
                            name: name,
                            parameters: '',
                            defaultfilter: 1,
                            action: 'setdefault'
                        }
                    }])[0].done(function(data) {
                        if (data.success === true) {
                            str.get_string('default').done(function(string) {
                                $('select[name=presaved] option:contains("(' + string + ')")').each(function() {
                                    $(this).text($(this).text().replace(' (' + string + ')', ''));
                                });
                                var defaultoption = $('select[name=presaved] option[value=' + id + ']');
                                defaultoption.text(defaultoption.text() + ' (' + string + ')');
                            });

                        } else {
                            notification.alert(null, data.msg);
                        }
                    }).fail(notification.exception);
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
