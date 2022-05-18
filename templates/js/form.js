OpencastEvent = {
    max_width: 1000,
    slider: null,
    initForm: function(max_width, cont_constrain_proportions_text) {
        this.max_width = max_width ? max_width : 1000;
        OpencastEvent.slider = $("#xoce_slider").data("ionRangeSlider");
        OpencastEvent.updateSlider();
        OpencastEvent.checkContPropText(cont_constrain_proportions_text);
        
        $('input#prop_embed_size_width').change(function() {
            let new_width = $(this).val();
            if (OpencastEvent.keepAspectRatio()) {
                let current_width = $('#xoce_thumbnail').width();
                let current_height = $('#xoce_thumbnail').height();
                let ratio = (current_width / current_height);
                let new_height = new_width / ratio;
                $('#xoce_thumbnail').height(new_height);
                $('input#prop_embed_size_height').val(new_height);
            }
            $('#xoce_thumbnail').width(new_width);
            OpencastEvent.updateSlider();
        });

        $('input#prop_embed_size_height').change(function() {
            let new_height = $(this).val();
            if (OpencastEvent.keepAspectRatio()) {
                let current_width = $('#xoce_thumbnail').width();
                let current_height = $('#xoce_thumbnail').height();
                let ratio = (current_width / current_height);
                let new_width = new_height * ratio;
                $('#xoce_thumbnail').width(new_width);
                $('input#prop_embed_size_width').val(new_width);
                OpencastEvent.updateSlider();
            }
            $('#xoce_thumbnail').height(new_height);
        });
    },

    updateSlider: function() {
        let width = $('input#prop_embed_size_width').val();
        if (parseInt(width) > parseInt(OpencastEvent.max_width)) {
            OpencastEvent.max_width = width;
        }
        let percentage = (width / OpencastEvent.max_width) * 100;
        OpencastEvent.slider.update({from: percentage});
    },

    sliderCallback: function(data) {
        let current_width = $('#xoce_thumbnail').width();
        let current_height = $('#xoce_thumbnail').height();
        let ratio = (current_width / current_height);
        let percentage = data.from;

        let new_width = OpencastEvent.max_width * (percentage / 100);
        let new_height = (new_width / ratio);

        $('#xoce_thumbnail').width(new_width);
        $('input#prop_embed_size_width').val(new_width);
        $('#xoce_thumbnail').height(new_height);
        $('input#prop_embed_size_height').val(new_height);
    },

    keepAspectRatio: function() {
        return $('input#prop_embed_size_constr').is(":checked");
    },

    checkContPropText: function(text) {
        if (text) {
            var input =  $('input#prop_embed_size_constr');
            var parent = input.parent();
            if (parent.text().indexOf('-cont_constrain_proportions-') !== false) {
                parent.text(text);
                input.css('margin-left', '5px');
                input.appendTo(parent);
            }
        }
    },
}