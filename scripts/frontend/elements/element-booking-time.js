jQuery(document).ready(function($){
    const s = bookingStore.get();

    // Load the times of the day the calendar shows as selected; only fall back to today without a stored day.
    if( s.day ) {
        ajaxBookingTimeExists(s.day, s.start_time || '');
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

    ajaxRefreshDurationForStart(start_time, null, function(durationState) {
        if (durationState && durationState.duration_minutes && window.wesanoxBookingTime) {
            checkedSvgNav('#how-long-time', '.how-long', window.wesanoxBookingTime.hoursLabel(durationState.duration_minutes));
        }

        jQuery('#loading-four').hide();
    });
}

function ajaxRefreshDurationForStart(start_time, preferredDurationMinutes, callback) {
    const s = bookingStore.get();
    const preferred = preferredDurationMinutes || (
        window.wesanoxBookingTime ? window.wesanoxBookingTime.preferredDurationMinutes(s) : null
    );

    let data = {
        action: 'element_booking_duration',
        start_time: start_time,
        day: s.day,
        area_id: s.area_id || 0
    };

    jQuery.post(ajax_object_time.ajax_url_time, data, function(response) {
        var html = '';
        if (response && response.success && response.data && typeof response.data.html !== 'undefined') {
            html = response.data.html;
        } else if (response && typeof response.html !== 'undefined') {
            html = response.html;
        } else {
            html = '<div class="col-12 p-2 text-center text-muted w-100">Für diese Startzeit ist kein buchbarer Timeslot mehr frei.</div>';
        }

        jQuery('#how-long-selected').html(html);

        var available = true;
        if (response && response.success === false) {
            available = false;
        }
        if (response && response.data && response.data.available === false) {
            available = false;
        }
        if (response && response.available === false) {
            available = false;
        }

        var $select = jQuery('#how-long-selected select');
        if (!available || !$select.length || !$select.find('option').length) {
            bookingStore.set({
                stop_time: null,
                duration_minutes: null,
                how_long: null,
                product_id: null
            });
            jQuery('#element-booking-duration a.forward').removeClass('active').addClass('inactive');

            if (typeof updateBookingRangeNav === 'function') {
                updateBookingRangeNav();
            }

            if (typeof callback === 'function') {
                callback({
                    available: false,
                    stop_time: null,
                    duration_minutes: null,
                    how_long: null
                });
            }
            return;
        }

        var durationState = window.wesanoxBookingTime
            ? window.wesanoxBookingTime.applyDurationSelection('#how-long-selected select', start_time, preferred)
            : null;

        if (!durationState) {
            var fallbackStop = jQuery('#how-long-selected select').val() || '';
            durationState = window.wesanoxBookingTime
                ? window.wesanoxBookingTime.stateForTimes(start_time, fallbackStop)
                : { stop_time: fallbackStop, duration_minutes: null, how_long: getHourDiff(start_time, fallbackStop) };
        }

        bookingStore.set({
            stop_time: durationState.stop_time || null,
            duration_minutes: durationState.duration_minutes || null,
            how_long: durationState.how_long || null,
            product_id: null
        });

        if (durationState.duration_minutes && window.wesanoxBookingTime) {
            checkedSvgNav('#how-long-time', '.how-long', window.wesanoxBookingTime.hoursLabel(durationState.duration_minutes));
        }

        jQuery('#element-booking-duration a.forward').removeClass('inactive').addClass('active');

        if (typeof updateBookingRangeNav === 'function') {
            updateBookingRangeNav();
        }

        if (typeof callback === 'function') {
            callback(durationState);
        }
    });
}

function ajaxBookingTimeExists(day, start_time = '') {
    const s = bookingStore.get();

    var data = {
        action: 'element_booking_time',
        nonce: ajax_object.nonce,
        day: day,
        start_time: start_time,
        area_id: s.area_id || 0
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

            const current = bookingStore.get();
            const preferred = window.wesanoxBookingTime ? window.wesanoxBookingTime.preferredDurationMinutes(current) : null;
            const $activeTime = jQuery('#booking-time-box .time-box.active');

            if (current.start_time && $activeTime.length) {
                bookingStore.set({
                    stop_time: null,
                    how_long: null,
                    duration_minutes: preferred,
                    product_id: null
                });
                ajaxRefreshDurationForStart(current.start_time, preferred);
            } else if (current.start_time) {
                bookingStore.set({
                    start_time: null,
                    stop_time: null,
                    how_long: null,
                    duration_minutes: preferred,
                    product_id: null
                });
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

        const preferred = window.wesanoxBookingTime ? window.wesanoxBookingTime.preferredDurationMinutes(s) : null;
        const startTime = jQuery(this).data('start-time');

        bookingStore.set({
            start_time: startTime,
            stop_time: null,
            how_long: null,
            duration_minutes: preferred,
            product_id: null
        });

        ajaxRefreshDurationForStart(startTime, preferred);

        jQuery('#element-booking-time a.forward').removeClass('inactive').addClass('active');
    });
}
