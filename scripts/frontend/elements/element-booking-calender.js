jQuery(document).ready(function($) {
    const CAL  = '#calendar';
    const BTN  = '#count-days-btn';

    $('body').on('click', '#check-in-out-box-back', function() {
        $('#element-booking-calender').removeClass('d-none');
        $('#element-booking-time').addClass('d-none');
    });

    $(CAL).zabuto_calendar({
        header_format: "[month] [year]",
        show_days: true,
        ajax: {
            url: ajax_object_calendar.ajax_url_calendar,
            ajax_settings: {
                type: 'POST',
                dataType: 'json'
            },
            success: function (data) { $('#loading').hide(); },
            error:   function (xhr) {}
        },
        navigation_markup: {
            prev: '<div class="prev"></div>',
            next: '<div class="next"></div>'
        },
        translation: {
            months: {
                "1": "Januar",
                "2": "Februar",
                "3": "März",
                "4": "April",
                "5": "Mai",
                "6": "Juni",
                "7": "Juli",
                "8": "August",
                "9": "September",
                "10": "Oktober",
                "11": "November",
                "12": "Dezember"
            },
            days: {
                "0": "So",
                "1": "Mo",
                "2": "Di",
                "3": "Mi",
                "4": "Do",
                "5": "Fr",
                "6": "Sa"
            }
        }
    });

    const restoreActiveFromStore = () => {
        const s = bookingStore.get();
        if (!s.day) return;

        const sel = `.zabuto-calendar__day[data-date="${formatYMD(s.day)}"]`;
        const $el = $(sel);
        if ($el.length && !$el.hasClass('inactive') && !$el.hasClass('fully-booked')) {
            $('.zabuto-calendar__day.active').removeClass('active').attr('day','');
            $el.addClass('active').attr('day', new Date(s.day).toDateString());
            $(BTN).removeClass('inactive').addClass('active');
        }
    };
    setTimeout(restoreActiveFromStore, 300);

    $(CAL).on('zabuto:calendar:day', function (e) {
        const now = new Date();

        $('.zabuto-calendar__day.active').removeClass('active').attr('day','');

        const isSelectable =
            !$(e.element).hasClass('fully-booked') &&
            (e.today || e.date.getTime() > now.getTime());

        if (!isSelectable) return;

        $(e.element).addClass('active').attr('day', e.date.toDateString());

        const s = bookingStore.get();
        // if (s.product_id) {
        //     $.post(ajax_object,ajax_url, { action: 'delete_cart_only_booking' }, function() {
        //         bookingStore.set({ extras: [] });
        //         $('body').trigger('wc_fragment_refresh');
        //     });
        // }

        const pickedYMD = formatYMD(e.date);
        bookingStore.set({ day: pickedYMD });

        ajaxBookingTime(pickedYMD);

        $(BTN).removeClass('inactive').addClass('active');
    });

    function formatYMD(dateLike){
        const d = (dateLike instanceof Date) ? dateLike : new Date(dateLike);
        const y = d.getFullYear();
        const m = String(d.getMonth()+1).padStart(2,'0');
        const da= String(d.getDate()).padStart(2,'0');
        return `${y}-${m}-${da}`;
    }
});

function ajaxBookingTime(day) {
    const s = bookingStore.get();
    const start_time = s.start_time || '';

    jQuery.ajax({
        url: ajax_object.ajax_url,
        method: 'POST',
        dataType: 'json',
        data: {
                action: 'element_booking_time',
                day: day,
                start_time: start_time
            }
        })
        .done(function (res) {
            if (res && res.success && res.data && typeof res.data.html !== 'undefined') {
                jQuery('#booking-time-box').html(res.data.html);
                jQuery('#element-booking-time').removeClass('d-none');
            } else if (res && typeof res.html !== 'undefined') {
                jQuery('#booking-time-box').html(res.html);
                jQuery('#element-booking-time').removeClass('d-none');
            } else {
                jQuery('#booking-time-box').html('<div class="col-12 p-2 text-center text-muted">Keine Zeiten gefunden.</div>');
                jQuery('#element-booking-time').removeClass('d-none');
            }

            if (jQuery(window).width() < 765) {
                jQuery('#element-booking-calender').addClass('d-none');
            }
        })
        .fail(function () {
            jQuery('#booking-time-box').html('<div class="col-12 p-2 text-center text-danger">Fehler beim Laden.</div>');
        });
}

