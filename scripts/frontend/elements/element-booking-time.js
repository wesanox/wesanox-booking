jQuery(document).ready(function($){
    const s = bookingStore.get();

    if( s.start_time ) {
        ajaxBookingTimeExists(s.day);
    } else {
        ajaxBookingTimeExists(new Date().toLocaleDateString('de-DE'));
    }

    ajaxBookingTimeSelected();

    $('body').on('click', '#element-booking-time a.active', function() {
        let btn_id          = $('#' + $(this).attr('value').replace('-box', ''));

        btn_id.addClass('active');
        btn_id.addClass('swiper-slide-active');
        btn_id.attr('href', '#' + btn_id.attr('id')  + '-slide');

        ajaxGetStartDate();
    })
});

function ajaxGetStartDate() {
    const s = bookingStore.get();
    let start_time = s.start_time;
    let day = new Date(s.day);

    let data = {
        action: 'element_booking_duration',
        start_time: start_time,
        day: s.day
    };

    jQuery.post(ajax_object_time.ajax_url_time, data, function(response) {
        jQuery('#how-long-selected').html(response.html);

        checkedSvgNav('#check-in-out', '.day', day.toLocaleDateString('de-DE') + ' ' + start_time);

        jQuery('#loading-four').hide();
    });
}

function ajaxBookingTimeExists(day, start_time = '') {
    var data = {
        action: 'element_booking_time',
        nonce: ajax_object.nonce,
        day: day,
        start_time: start_time,
    };

    jQuery.ajax({
            url: ajax_object.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: data
        })
        .done(function (res) {
            if (res && res.success && res.data && typeof res.data.html !== 'undefined') {
                jQuery('#booking-time-box').html(res.data.html);
            } else if (res && typeof res.html !== 'undefined') {
                jQuery('#booking-time-box').html(res.html);
            } else {
                jQuery('#booking-time-box').html('<div class="col-12 p-2 text-center text-muted">Keine Zeiten gefunden.</div>');
            }
        })
        .fail(function () {
            jQuery('#booking-time-box').html('<div class="col-12 p-2 text-center text-danger">Fehler beim Laden.</div>');
        });
}

function ajaxBookingTimeSelected() {
    jQuery('body').off('click.timebox').on('click.timebox', '#booking-time-box .time-box', function() {
        jQuery('#booking-time-box .time-box.active').removeClass('active');

        jQuery(this).addClass('active');

        const s = bookingStore.get();

        if (s.product_id) {
            jQuery.post(ajax_object.ajax_url, { action: 'delete_cart_only_booking' }, function() {
                bookingStore.set({ extras: [] });

                jQuery('body').trigger('wc_fragment_refresh');
            });
        }

        bookingStore.set({ start_time: jQuery(this).data('start-time') });

        jQuery('#element-booking-time a.forward').removeClass('inactive').addClass('active');
    });
}