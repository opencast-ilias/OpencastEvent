/**
 * Table (event list) related js of OpencastEvent
 *
 * @author Farbod Zamani Boroujeni <zamani@elan-ev.de>
 */
export default class TableHandler {
    /**
     * @type {jQuery}
     */
    jquery;

    constructor(
        jquery,
    ){
        this.jquery = jquery;
    }

    /**
     * Init
     */
    init() {
        this.initSelectedRow();
        this.rowClickListener();
        this.changeEventClickListener();
        this.sortClickListener();
        this.filterKeyListener();
    }

    initSelectedRow() {
        $('.xoce_table_row_selectable').each(function (index, row) {
            if ($(row).data('selectable') != true) {
                $(row).removeClass('xoce_table_row_selectable');
            }
        });
        $('.xoce_table_row_selectable').removeClass('selected');
        $(`.xoce_table_row_selectable[data-event_id="${$('#event_id').val()}"]`).addClass('selected');
    }

    rowClickListener() {
        $('.xoce_table_row_selectable').on('click', function() {
            let deselect = false;
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
    }

    changeEventClickListener() {
        let self = this;
        $('#change_event').on('click', function (e) {
            if ($(this).is(':checked')) {
                $('#event_table').show();
            } else {
                $('#event_table').hide();
                $('#event_id').val($('#current_event_id').val());
                $('#event_id_display').text($('#current_event_id').val());
                $('#title').text($('#current_title').val());
                self.initSelectedRow();
            }
        });

        if ($('#change_event').is(':visible')) {
            if ($('#change_event').is(':checked')) {
                $('#event_table').show();
            } else {
                $('#event_table').hide();
            }
        }
    }

    sortClickListener() {
        $('#event_table table th.sortable-th > a').on('click', function(e) {
            e.preventDefault();
            let href = $(this).attr('href');
            let query_string = href.split('?')[1];
            let base_url = href.split('?')[0];

            let url_params = new URLSearchParams(query_string);
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
    }

    filterKeyListener() {
        $('#event_table .ilTableFilterInput .form-control').on('keydown', function(e) {
            if (e.key === 'Enter') {
                if ($('input[name="cmd[applyFilterEdit]"]').is(':visible')) {
                    $('input[name="cmd[applyFilterEdit]"]').trigger('click');
                } else if ($('input[name="cmd[applyFilter]"]').is(':visible')) {
                    $('input[name="cmd[applyFilter]"]').trigger('click');
                }
                e.preventDefault();
                return false;
            }
        });
    }
}
