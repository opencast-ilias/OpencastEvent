/**
 * Form related js of OpencastEvent
 *
 * @author Farbod Zamani Boroujeni <zamani@elan-ev.de>
 */
il = il || {};
il.OpencastEvent = il.OpencastEvent || {};
(function ($, il) {
    il.OpencastEvent.form = (function ($) {

        var max_width = 1000;
        var slider = null;

        var initForm = function(mx_width, cont_constrain_proportions_text, s_config) {
            max_width = mx_width ? mx_width : 1000;

            slider = initSlider(s_config);
            updateSlider();
            
            checkContPropText(cont_constrain_proportions_text);
            
            $('input#prop_embed_size_width').change(function() {
                let new_width = $(this).val();
                if (keepAspectRatio()) {
                    let current_width = $('#xoce_thumbnail').width();
                    let current_height = $('#xoce_thumbnail').height();
                    let ratio = (current_width / current_height);
                    let new_height = new_width / ratio;
                    $('#xoce_thumbnail').height(new_height);
                    $('input#prop_embed_size_height').val(new_height);
                }
                $('#xoce_thumbnail').width(new_width);
                updateSlider();
            });

            $('input#prop_embed_size_height').change(function() {
                let new_height = $(this).val();
                if (keepAspectRatio()) {
                    let current_width = $('#xoce_thumbnail').width();
                    let current_height = $('#xoce_thumbnail').height();
                    let ratio = (current_width / current_height);
                    let new_width = new_height * ratio;
                    $('#xoce_thumbnail').width(new_width);
                    $('input#prop_embed_size_width').val(new_width);
                    updateSlider();
                }
                $('#xoce_thumbnail').height(new_height);
            });
        }

        var initSlider = function(config) {
            var slider_config = config;
            slider_config.onChange = sliderCallback;
            $("#xoce_slider").ionRangeSlider(slider_config);
            return $("#xoce_slider").data("ionRangeSlider");
        };

        var updateSlider = function() {
            let width = $('input#prop_embed_size_width').val();
            if (parseInt(width) > parseInt(max_width)) {
                max_width = width;
            }
            let percentage = (width / max_width) * 100;
            slider.update({from: percentage});
        };

        var sliderCallback = function(data) {
            let current_width = $('#xoce_thumbnail').width();
            let current_height = $('#xoce_thumbnail').height();
            let ratio = (current_width / current_height);
            let percentage = data.from;

            let new_width = max_width * (percentage / 100);
            let new_height = (new_width / ratio);

            $('#xoce_thumbnail').width(new_width);
            $('input#prop_embed_size_width').val(new_width);
            $('#xoce_thumbnail').height(new_height);
            $('input#prop_embed_size_height').val(new_height);
        };

        var keepAspectRatio = function() {
            return $('input#prop_embed_size_constr').is(":checked");
        };

        var checkContPropText = function(text) {
            if (text) {
                var input =  $('input#prop_embed_size_constr');
                var parent = input.parent();
                if (parent.text().indexOf('-cont_constrain_proportions-') !== false) {
                    parent.text(text);
                    input.css('margin-left', '5px');
                    input.appendTo(parent);
                }
            }
        };

        return {
            initForm: initForm
        };
    })($);
})($, il);