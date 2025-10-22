jQuery(document).ready( function($) {
    $('body').on('change', '#element-booking-duration select', function() {
        bookingStore.set({ stop_time: $(this).val() });
    });

    $('body').on('click', '#element-booking-duration a.active', function() {
        bookingStore.set({ stop_time: $('#element-booking-duration select').val() });

        const s = bookingStore.get();

        bookingStore.set({ how_long: getHourDiff(s.start_time, s.stop_time) });

        let html_long = getHourDiff(s.start_time, s.stop_time) + ' Stunden';

        let btn_id          = $('#' + $(this).attr('value').replace('-box', ''));

        btn_id.addClass('active');
        btn_id.addClass('swiper-slide-active');
        btn_id.attr('href', '#' + btn_id.attr('id')  + '-slide');

        checkedSvgNav('#how-long-time', '.how-long', html_long);

        ajaxGetProductInfo();
    });
});

function getHourDiff(start, end) {
    if (!start || !end) return 0;

    const [sh, sm] = start.split(':').map(Number);
    const [eh, em] = end.split(':').map(Number);

    let diffMinutes = (eh * 60 + em) - (sh * 60 + sm);
    if (diffMinutes < 0) diffMinutes += 24 * 60;

    return diffMinutes / 60;
}

function ajaxGetProductInfo(product_id = '') {
    const s = bookingStore.get();

    let data = {
        action: 'element_booking_item',
        start_time: s.start_time,
        stop_time: s.stop_time,
        day: s.day,
    };

    jQuery.post(ajax_object_duration.ajax_url_duration, data, function(response) {
        if (!response.available_one) {
            jQuery('#variation_price_1').html(response.variation_price_one);
            jQuery('#variation_btn_1').addClass('d-none');
            jQuery('#variation_message_1').removeClass('d-none').css('z-index', 1).html('ausgebucht');
        } else {
            jQuery('#variation_price_1').html(response.variation_price_one);
            jQuery('#variation_btn_1').attr('data-product_id', response.variation_id_one);

            if (jQuery('#variation_btn_1').hasClass('d-none')) {
                jQuery('#variation_btn_1').removeClass('d-none');
                jQuery('#variation_message_1').addClass('d-none');
            }
        }

        if (!response.available_two) {
            jQuery('#variation_price_2').html(response.variation_price_two);
            jQuery('#variation_btn_2').addClass('d-none');
            jQuery('#variation_message_2').removeClass('d-none').css('z-index', 1).html('ausgebucht');
        } else {
            jQuery('#variation_price_2').html(response.variation_price_two);
            jQuery('#variation_btn_2').attr('data-product_id', response.variation_id_two);

            if (jQuery('#variation_btn_2').hasClass('d-none')) {
                jQuery('#variation_btn_2').removeClass('d-none');
                jQuery('#variation_message_2').addClass('d-none');
            }
        }


        if ( response.variation_id_one === parseInt(product_id) ) {
            jQuery('#variation_btn_1').addClass('inactive').html('AUSGEWÄHLT');
        }

        if ( response.variation_id_two === parseInt(product_id) ) {
            jQuery('#variation_btn_2').addClass('inactive').html('AUSGEWÄHLT');
        }

        jQuery('#loading-five').hide();
    });
}