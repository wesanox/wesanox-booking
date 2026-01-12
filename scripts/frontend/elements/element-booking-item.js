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

    $(".add-to-cart-btn").click(function() {
        var product_id = $(this).data("product_id");

        const s = bookingStore.get();

        syncBookingToServer().always(function(){
            $.post(ajax_object_item.ajax_url_item, {
                action: 'add_product_to_cart',
                nonce: ajax_object_item.nonce,
                product_id: product_id,
                day:  s.day,
                person_count: s.person_count,
                start_time: s.start_time,
                stop_time: s.stop_time,
                how_long: s.how_long,
            }, function(response){
                console.log(response);

                $('#message').removeClass('d-none').html("Buchung wurde zum Warenkorb hinzugefügt!");

                $('body').trigger('wc_fragment_refresh');

                let btn_id          = $('#extras');

                btn_id.addClass('active');
                btn_id.addClass('swiper-slide-active');
                btn_id.attr('href', '#' + btn_id.attr('id')  + '-slide');

                checkedSvgNav('#room', '.suite', 'ausgewählt');

                setTimeout(function() {
                    $('#message').addClass('d-none');
                }, 5000);
            });
        });

        $('#room-box a.forward').removeClass('inactive').addClass('active');
    });
})

function syncBookingToServer() {
    const payload = bookingStore.get();
    return jQuery.post(ajax_object.ajax_url, {
        action: 'store_booking_payload',
        nonce: ajax_object.nonce,
        payload: JSON.stringify(payload)
    });
}