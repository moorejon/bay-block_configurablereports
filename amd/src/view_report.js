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

define(['jquery', 'core/templates', 'core/notification', 'core/ajax', 'core/str'],
    function ($, templates, notification, ajax, str) {

    return {
        init: function () {

            var self = this;

            var updatefilterpreferences = function(form, id, reportid, name, preflistelem) {
                var parameters = getFormElements(form);
                var defaultfilter = 0;

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
                            if (!preflistelem.children().length > 0) {
                                preflistelem.append('<option value="0"></option>');
                            }
                            preflistelem.append('<option value="' + data.id + '" selected="selected">' + name + '</option>');
                        }
                        if (id > 0) {
                            str.get_strings([
                                {key: 'message', component: 'block_configurable_reports'},
                                {key: 'saved', component: 'block_configurable_reports'}
                            ]).done(function(strings) {
                                notification.alert(strings[0], strings[1]);
                            }).fail(notification.exception);
                        }
                        showPrefButtons();
                        $('#id_prefname').val('');
                    } else {
                        notification.alert(null, data.msg);
                    }
                }).fail(notification.exception);
            };
            /**
             * Used to hide preference buttons when not relevant
             */
            var showPrefButtons = function() {
                $('#id_prefupdate').show();
                $('#id_prefdelete').show();
                $('#id_prefdefault').show();
            };

            var hidePrefButtons = function() {
                $('#id_prefupdate').hide();
                $('#id_prefdelete').hide();
                $('#id_prefdefault').hide();
            };

            var clearForm = function(form) {
                var formelements = getFormElements(form);
                $.each(formelements, function(index, field) {
                    var elem = $('#id_' + field.name);
                    if (elem.length > 0) {
                        if (elem.prop("tagName") == 'SELECT') {
                            elem.prop('selectedIndex',0);
                        } else {
                            elem.val('');
                        }
                    }
                });
            };

            var getFormElements = function(form) {
                var disregard = ['id', 'courseid', 'embedded', 'sesskey', '_qf__report_edit_form',
                    'mform_isexpanded_id_general', 'prefname', 'presaved', 'prefdelete', 'prefdefault'];
                var rawformelements = form.serializeArray();
                var formelements = [];

                $.each(rawformelements, function(index, field) {
                    if ($.inArray(field.name, disregard) === -1) {
                        formelements.push(field);
                    }
                });

                return formelements;
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

                    var prefname = $('input[name=prefname]');
                    var presaved = $('select[name=presaved]');
                    var form = $(this).closest("form");

                    // Ajax parameters.
                    var reportid = $('input[name=id]').val();
                    var name = prefname.val().trim();

                    if (name === '') {
                        return;
                    }

                    updatefilterpreferences(form,0, reportid, name, presaved);
                });

                $('#id_prefupdate').click(function(event) {
                    event.preventDefault();

                    var presaved = $('select[name=presaved]');
                    var form = $(this).closest("form");

                    // Ajax parameters.
                    var id = presaved.val();
                    var reportid = $('input[name=id]').val();

                    updatefilterpreferences(form, id, reportid, '', null);
                });

                $('#id_presaved').change(function() {
                    var preferenceid = $(this).val();
                    var form = $(this).closest("form");

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
                                clearForm(form);
                                var filter = JSON.parse(data.filter);
                                $.each(filter, function(index, field) {
                                    var elem = $('#id_' + field.name);
                                    if (elem.length > 0) {
                                        elem.val(field.value);
                                    }
                                });
                            }
                        }).fail(notification.exception);
                        showPrefButtons();
                    } else {
                        clearForm(form);
                        hidePrefButtons();
                    }
                });

                $('#id_prefdelete').click(function(event) {
                    event.preventDefault();

                    var id = parseInt($('select[name=presaved]').val());
                    var reportid = $('input[name=id]').val();
                    var name = $('select[name=presaved] option:selected').text().trim();
                    var form = $(this).closest("form");

                    if (id === 0) {
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
                            clearForm(form);
                            hidePrefButtons();
                        } else {
                            notification.alert(null, data.msg);
                        }
                    }).fail(notification.exception);
                });

                $('#id_prefdefault').click(function(event) {
                    event.preventDefault();

                    var id = parseInt($('select[name=presaved]').val());
                    var reportid = $('input[name=id]').val();
                    var name = $('select[name=presaved] option:selected').text().trim();

                    if (id === 0) {
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
                                if (data.msg !== 'removed') {
                                    var defaultoption = $('select[name=presaved] option[value=' + id + ']');
                                    defaultoption.text(defaultoption.text() + ' (' + string + ')');
                                }
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
