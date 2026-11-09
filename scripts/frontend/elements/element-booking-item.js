jQuery(document).ready(function($){
    $('body').on('click', '.show-settings', function() {
        let show_class = $(this).data('box');

        $('.' + show_class).toggleClass('d-none');
    });

    $('body').on('click', '.btn-settings-close', function() {
        let show_class = $(this).data('box');

        $('.' + show_class).addClass('d-none');
    });

    $("body").on('click', '.get-product_id', function() {
        let elementId = $(this).attr('id');
        let product_id = 0;

        product_id = $('#' + elementId).attr('data-product_id');

        bookingStore.set({ product_id: product_id });

        $('.get-product_id').each(function() {
            $(this).removeClass('inactive').html('BUCHEN');
        });

        $(".add-to-cart-btn").removeClass('inactive').attr('data-product_id', product_id);

        $(this).addClass('inactive').html('AUSGEWÄHLT');
    });

    $(".add-to-cart-btn").click(function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();

        const $button = $(this);
        const product_id = $button.data("product_id");
        const s = bookingStore.get();

        if ($button.hasClass('is-loading')) return false;

        if (!product_id) {
            showBookingCartMessage('Bitte wähle zuerst eine Suite und einen Tarif.', true);
            return false;
        }

        $button.addClass('is-loading inactive').removeClass('active').attr('aria-disabled', 'true');

        syncBookingToServer()
            .done(function(syncResponse) {
                if (!syncResponse || syncResponse.success === false) {
                    restoreBookingButton($button);
                    showBookingCartMessage(responseMessage(syncResponse, 'Die Buchungsdaten konnten nicht gespeichert werden.'), true);
                    return;
                }

                $.post(ajax_object_item.ajax_url_item, {
                    action: 'add_product_to_cart',
                    nonce: ajax_object_item.nonce,
                    product_id: product_id,
                    day: s.day,
                    person_count: s.person_count,
                    start_time: s.start_time,
                    stop_time: s.stop_time,
                    how_long: s.how_long,
                    duration_minutes: s.duration_minutes,
                    area_id: s.area_id,
                })
                    .done(function(response) {
                        if (!response || response.success === false) {
                            restoreBookingButton($button);
                            showBookingCartMessage(responseMessage(response, 'Der Tarif konnte nicht in den Warenkorb gelegt werden.'), true);
                            return;
                        }

                        $button.removeClass('is-loading inactive').addClass('active').removeAttr('aria-disabled');
                        showBookingCartMessage('Buchung wurde zum Warenkorb hinzugefügt!', false);

                        $('body').trigger('wc_fragment_refresh');

                        const btn_id = $('#extras');
                        btn_id.addClass('active swiper-slide-active');
                        btn_id.attr('href', '#extras-slide');

                        checkedSvgNav('#room', '.suite', 'ausgewählt');

                        if (window.swiper_nav) window.swiper_nav.slideTo(4);
                        if (window.swiper_content) window.swiper_content.slideTo(4);
                        window.location.hash = 'extras-slide';
                        getStepNumber(5);
                    })
                    .fail(function(xhr) {
                        restoreBookingButton($button);
                        showBookingCartMessage(xhrMessage(xhr, 'Der Tarif konnte nicht in den Warenkorb gelegt werden.'), true);
                    });
            })
            .fail(function(xhr) {
                restoreBookingButton($button);
                showBookingCartMessage(xhrMessage(xhr, 'Die Buchungsdaten konnten nicht gespeichert werden.'), true);
            });

        return false;
    });

    function restoreBookingButton($button) {
        $button.removeClass('is-loading inactive').addClass('active').removeAttr('aria-disabled');
    }

    function responseMessage(response, fallback) {
        if (response && response.data) {
            return response.data.message || response.data.msg || fallback;
        }

        return fallback;
    }

    function xhrMessage(xhr, fallback) {
        return responseMessage(xhr && xhr.responseJSON ? xhr.responseJSON : null, fallback);
    }

    function showBookingCartMessage(message, isError) {
        $('#message')
            .toggleClass('text-danger', Boolean(isError))
            .removeClass('d-none')
            .text(message);

        setTimeout(function() {
            $('#message').addClass('d-none');
        }, 5000);
    }
})

function syncBookingToServer() {
    const payload = bookingStore.get();
    return jQuery.post(ajax_object.ajax_url, {
        action: 'store_booking_payload',
        nonce: ajax_object.nonce,
        payload: JSON.stringify(payload)
    });
}
