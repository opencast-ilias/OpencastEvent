/**
 * Table (event list) related js of OpencastEvent
 *
 * @author Farbod Zamani Boroujeni <zamani@elan-ev.de>
 */
il = il || {};
il.OpencastEvent = il.OpencastEvent || {};
(function ($, il) {
    il.OpencastEvent.table = (function ($) {

        var init = function() {
            initSelectedRow();
            rowClickListener();
            changeEventClickListener();
            sortClickListener();
            filterKeyListener();
        };

        var initSelectedRow = function() {
            $('.xoce_table_row_selectable').each(function (index, row) {
                if ($(row).data('selectable') != true) {
                    $(row).removeClass('xoce_table_row_selectable');
                }
            });
            $('.xoce_table_row_selectable').removeClass('selected');
            $(`.xoce_table_row_selectable[data-event_id="${$('#event_id').val()}"]`).addClass('selected');
        };

        var rowClickListener = function() {
            $('.xoce_table_row_selectable').click(function() {
                var deselect = false;
                if ($(this).hasClass('selected')) {
                    deselect = true;
                }
                $('#event_id').val('');
                $('.xoce_table_row_selectable').removeClass('selected');
                $(this).toggleClass('selected', !deselect);
        
                if ($(this).hasClass('selected')) {
                    $('#event_id').val($(this).data('event_id'));
                    if ($('#event_id_display').is(':visible')) {
                        $('#event_id_display').text($(this).data('event_id'));
                    }
                    if ($('#title').is(':visible')) {
                        $('#title').text($(this).data('title'));
                    }
                    if ($('#description').is(':visible')) {
                        $('#description').text($(this).data('description'));
                    }
                }
            });
        };

        var changeEventClickListener = function() {
            $('#change_event').click(function (e) {
                if ($(this).is(':checked')) {
                    $('#event_table').show();
                } else {
                    $('#event_table').hide();
                    $('#event_id').val($('#current_event_id').val());
                    $('#event_id_display').text($('#current_event_id').val());
                    $('#title').text($('#current_title').val());
                    $('#description').text($('#current_description').val());
                    initSelectedRow();
                }
            });

            if ($('#change_event').is(':visible')) {
                if ($('#change_event').is(':checked')) {
                    $('#event_table').show();
                } else {
                    $('#event_table').hide();
                }
            }
        };

        var sortClickListener = function() {
            $('#event_table table th.sortable-th > a').click(function(e) {
                e.preventDefault();
                var href = $(this).attr('href');
                var query_string = href.split('?')[1];
                var base_url = href.split('?')[0];
                
                var url_params = new URLSearchParams(query_string);
                if (url_params.has('cmd') && url_params.get('cmd') == 'editEvent' && !url_params.has('change_event')) {
                    url_params.append('change_event', 1);
                }
                if (url_params.has('offset')) {
                    url_params.set('offset', 0);
                } else {
                    url_params.append('offset', 0);
                }
        
                window.open(base_url + '?' + url_params.toString(), '_self');
            });
        };

        var filterKeyListener = function() {
            $('#event_table .ilTableFilterInput .form-control').on('keydown', function(e) {
                if (e.keyCode == 13) {
                    if ($('input[name="cmd[applyFilterEdit]"]').is(':visible')) {
                        $('input[name="cmd[applyFilterEdit]"]').click();
                    } else if ($('input[name="cmd[applyFilter]"]').is(':visible')) {
                        $('input[name="cmd[applyFilter]"]').click();
                    }
                    e.preventDefault();
                    return false;
                }
            });
        };

        return {
            init: init
        };
    })($);
})($, il);