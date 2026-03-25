/**
 * List (event list) related js of OpencastEvent
 *
 * @author Farbod Zamani Boroujeni <zamani@elan-ev.de>
 */
export default class ListHandler {
    /**
     * @type {jQuery}
     */
    jquery;
    item_selector = '#event_listing .il-item-group-items ul li.il-std-item-container .il-item';
    selectable_class = 'selectable';
    not_selectable_class = 'not-selectable';
    selected_class = 'selected';

    constructor(
        jquery,
    ){
        this.jquery = jquery;
    }

    /**
     * Init
     */
    init() {
        this.initSelectable()
        this.initSelectedItem();
        this.itemClickListener();
        this.changeEventClickListener();
    }

    initSelectable() {
        const self = this;
        $(this.item_selector).each(function (index, item) {
            let class_name = self.not_selectable_class;
            if (self.isItemSelectable(item)) {
                class_name = self.selectable_class;
            }
            $(item).addClass(class_name);
        });
    }

    initSelectedItem() {
        const self = this;
        $(this.item_selector).removeClass(this.selected_class);
        $(this.item_selector).each(function (index, item) {
            if (self.isItemSelectable(item)) {
                let item_event_id = self.getItemEventId(item);
                if (item_event_id && $('#event_id').val() == item_event_id) {
                    $(item).addClass(self.selected_class);
                    self.toggleSelectedStatus(item, true);
                }
            }
        });
    }

    isItemSelectable(item) {
        let status_elm = $(item).find('span.status');
        return status_elm && $(status_elm).data('selectable') == true;
    }

    getItemEventId(item) {
        let id_elm = $(item).find('span.event-id');
        if (id_elm) {
            return $(id_elm).data('event_id');
        }
        return null;
    }

    getItemEventTitle(item) {
        let title_elm = $(item).find('span.event-title');
        if (title_elm) {
            return $(title_elm).data('event_title');
        }
        return null;
    }

    getItemEventDesc(item) {
        let desc_elm = $(item).find('span.event-desc');
        if (desc_elm) {
            return $(desc_elm).data('event_desc');
        }
        return null;
    }

    toggleSelectedStatus(item, selected) {
        $(this.item_selector + ' span.status-text').removeClass('hidden');
        $(this.item_selector + ' span.status-selected-text').addClass('hidden');
        if (selected) {
            $(item).find('span.status-selected-text').removeClass('hidden');
            $(item).find('span.status-text').addClass('hidden');
        }
    }

    itemClickListener() {
        const self = this;
        $(this.item_selector).on('click', function() {
            if (!$(this).hasClass(self.selectable_class)) {
                return;
            }
            let deselect = false;
            if ($(this).hasClass(self.selected_class)) {
                deselect = true;
            }
            $('#event_id').val('');
            $(self.item_selector).removeClass(self.selected_class);
            $(this).toggleClass(self.selected_class, !deselect);
            self.toggleSelectedStatus(this, !deselect);

            let item_event_id = self.getItemEventId(this);
            if ($(this).hasClass(self.selected_class) && item_event_id) {
                $('#event_id').val(item_event_id);
                if ($('#event_id_display').is(':visible')) {
                    $('#event_id_display').text(item_event_id);
                }
                if ($('#title').is(':visible')) {
                    $('#title').text(self.getItemEventTitle(this));
                }
                if ($('#description').is(':visible')) {
                    $('#description').text(self.getItemEventDesc(this));
                }
            }
        });
    }

    changeEventClickListener() {
        let self = this;

        $('#change_event_default').val();

        $('#change_event').on('click', function (e) {
            if ($(this).is(':checked')) {
                $('#listing_with_filter').show();
            } else {
                $('#listing_with_filter').hide();
                $('#event_id').val($('#current_event_id').val());
                $('#event_id_display').text($('#current_event_id').val());
                $('#title').text($('#current_title').val());
                self.initSelectedItem();
            }
        });

        if ($('#change_event').is(':visible')) {
            if ($('#change_event').is(':checked')) {
                $('#listing_with_filter').show();
            } else {
                $('#listing_with_filter').hide();
            }
        }
    }
}
