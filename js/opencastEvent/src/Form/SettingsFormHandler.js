/**
 * Form related js of OpencastEvent
 *
 * @author Farbod Zamani Boroujeni <zamani@elan-ev.de>
 */
export default class SettingsFormHandler {
    /**
     * @type {jQuery}
     */
    jquery;


    max_width;
    slider;

    constructor(
        jquery,
    ){
        this.jquery = jquery;
        this.max_width = 1000;
        this.slider = null;
    }


    /**
     * initSettingsForm
     * @param {int} mx_width defined max width
     * @param {string} cont_constrain_proportions_text defined text for constrains
     * @param {object} s_config defined slider config
     */
    initSettingsForm(mx_width, cont_constrain_proportions_text, s_config) {
        this.max_width = mx_width ? parseInt(mx_width, 10) : 1000;

        this.slider = this.initSlider(s_config);
        this.updateSlider();

        this.checkContPropText(cont_constrain_proportions_text);

        let self = this;

        $('input#prop_embed_size_width').on('change', function() {
            console.log('HERE... prop_embed_size_width');
            let new_width = $(this).val();
            new_width = parseInt(new_width, 10);
            if (self.keepAspectRatio()) {
                let current_width = $('#xoce_preview_image').width();
                current_width = parseInt(current_width, 10);
                let current_height = $('#xoce_preview_image').height();
                current_height = parseInt(current_height, 10);
                let ratio = (current_width / current_height);
                let new_height = new_width / ratio;
                $('#xoce_preview_image').height(new_height);
                $('input#prop_embed_size_height').val(new_height);
            }
            $('#xoce_preview_image').width(new_width);
            self.updateSlider();
        });

        $('input#prop_embed_size_height').on('change', function() {
            console.log('HERE... prop_embed_size_height');

            let new_height = $(this).val();
            new_height = parseInt(new_height, 10);
            if (self.keepAspectRatio()) {
                let current_width = $('#xoce_preview_image').width();
                current_width = parseInt(current_width, 10);
                let current_height = $('#xoce_preview_image').height();
                current_height = parseInt(current_height, 10);
                let ratio = (current_width / current_height);
                let new_width = new_height * ratio;
                $('#xoce_preview_image').width(new_width);
                $('input#prop_embed_size_width').val(new_width);
                self.updateSlider();
            }
            $('#xoce_preview_image').height(new_height);
        });
    }

    /**
     * Slider initialization
     * @param {object} config
     * @returns {object} slider
     */
    initSlider(config) {
        let slider_config = config;
        let self = this;
        slider_config.onChange = function (data) {
            self.sliderCallback(data, self);
        };
        $("#xoce_slider").ionRangeSlider(slider_config);
        return $("#xoce_slider").data("ionRangeSlider");
    }

    /**
     * helper function to update slider position/width etc.
     */
    updateSlider() {
        let width = $('input#prop_embed_size_width').val();
        if (parseInt(width, 10) > parseInt(this.max_width, 10)) {
            this.max_width = width;
        }
        let percentage = (width / this.max_width) * 100;
        this.slider.update({from: percentage});
    }

    /**
     * slider callback helper function to adjust related elements.
     * @param {object} data slider data object
     * @param {object} parent SettingsFormHandler class instance
     */
    sliderCallback(data, parent) {
        let current_width = $('#xoce_preview_image').width();
        current_width = parseInt(current_width, 10);
        let current_height = $('#xoce_preview_image').height();
        current_height = parseInt(current_height, 10);
        let ratio = (current_width / current_height);
        let percentage = data.from;
        percentage = parseInt(percentage, 10);

        let new_width = parent.max_width * (percentage / 100);
        let new_height = (new_width / ratio);

        $('#xoce_preview_image').width(new_width);
        $('input#prop_embed_size_width').val(new_width);
        $('#xoce_preview_image').height(new_height);
        $('input#prop_embed_size_height').val(new_height);
    }

    /**
     * Get the value of aspect ratio element
     * @returns {boolean}
     */
    keepAspectRatio() {
        return $('input#prop_embed_size_constr').is(":checked");
    }

    /**
     * helper function to add defined text as for the prop_embed_size_constr input element
     * @param {string} text defined text
     */
    checkContPropText(text) {
        if (text) {
            let input =  $('input#prop_embed_size_constr');
            let parent = input.parent();
            if (parent.text().indexOf('-cont_constrain_proportions-') !== false) {
                parent.text(text);
                input.css('margin-left', '5px');
                input.appendTo(parent);
            }
        }
    }
}
