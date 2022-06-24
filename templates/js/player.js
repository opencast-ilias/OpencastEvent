/**
 * Player related js of OpencastEvent
 *
 * @author Farbod Zamani Boroujeni <zamani@elan-ev.de>
 */
il = il || {};
il.OpencastEvent = il.OpencastEvent || {};
(function ($, il) {
    il.OpencastEvent.player = (function ($) {

        var init = function(config) {
            if (!config.maximize) {
                setSize(config.width, config.height);
            } else {
                setMaxSize();
            }
        };

        var setMaxSize = function() {
            $('#xoce_player_container').width('100%');
            $('#xoce_player_container').height('0px');
            var spacekeepr_height = $('#mainspacekeeper').outerHeight();
            var main_height = $('main.il-layout-page-content').outerHeight();
            var remaining_height = main_height - spacekeepr_height;
            if (remaining_height > 0) {
                $('#xoce_player_container').height(remaining_height);
                $('main.il-layout-page-content').animate({
                    scrollTop: ($("#xoce_player_container").offset().top)
                });
            } else {
                $('#xoce_player_container').height('100%');
            }
        };

        var setSize = function(width, height) {
            $('#xoce_player_container').width(width + 'px');
            $('#xoce_player_container').height(height + 'px');
        };

        return {
            init: init
        };
    })($);
})($, il);