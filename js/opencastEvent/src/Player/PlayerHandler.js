/**
 * The class that handles the player container scales and sizes.
 * PlayerHandler class
 *
 * @author Farbod Zamani Boroujeni <zamani@elan-ev.de>
 */
export default class PlayerHandler {
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
     * Init method
     * @param {object} config defined config object
     */
    init(config) {
        if (!config.maximize) {
            this.setSize(parseInt(config.width, 10), parseInt(config.height, 10));
        } else {
            this.setMaxSize();
        }
    }

    /**
     * Helper function that sets the max size.
     */
    setMaxSize() {
        $('#xoce_player_container').width('100%');
        $('#xoce_player_container').height('0px');
        let spacekeepr_height = $('#mainspacekeeper').outerHeight();
        spacekeepr_height = parseInt(spacekeepr_height, 10);
        let main_height = $('main.il-layout-page-content').outerHeight();
        main_height = parseInt(main_height, 10);
        let remaining_height = main_height - spacekeepr_height;
        if (remaining_height > 0) {
            $('#xoce_player_container').height(remaining_height);
            $('main.il-layout-page-content').animate({
                scrollTop: ($("#xoce_player_container").offset().top)
            });
        } else {
            $('#xoce_player_container').height('100%');
        }
    }

    /**
     * Helper function that sets sizes.
     * @param {int} width
     * @param {int} height
     */
    setSize(width, height) {
        $('#xoce_player_container').css("maxWidth", width + 'px');
        this.applyAspectRatio(width, height);
        let self = this;
        $(window).on('resize', function() {
            self.applyAspectRatio(width, height);
        });
    }

    /**
     * Helper function that adjusts and applies aspect ratio.
     * @param {int} width
     * @param {int} height
     */
    applyAspectRatio(width, height) {
        let aspect_ratio = width / height;
        let current_width = $('#xoce_player_container').width();
        current_width = parseInt(current_width, 10);
        let current_height = current_width / aspect_ratio;
        $('#xoce_player_container').height(current_height + 'px');
    }
}
