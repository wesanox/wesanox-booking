jQuery(function($){
    const s = bookingStore.get();

    if(s.person_count !== '' || s.person_count != null || s.person_count !== undefined) {
        changeCount(s.person_count, 92);
        changeCount(s.person_count, 93);
        changeCount(s.person_count, 94);
        changeCount(s.person_count, 95);
        changeCount(s.person_count, 96);
    }

    function upsertExtra(extras, item) {
        const idx = extras.findIndex(x => Number(x.product_id) === Number(item.product_id));
        if (idx >= 0) extras[idx] = { ...extras[idx], ...item };
        else extras.push(item);
        return extras;
    }

    function removeExtra(extras, product_id) {
        const pid = Number(product_id);
        return extras.filter(x => Number(x.product_id) !== pid);
    }

    function getQtyBasicFallback() {
        const v = Number($('.person-count').attr('value'));
        return Number.isFinite(v) && v > 0 ? v : 1;
    }

    $('body').on('change', '.quantity-input', function () {
        $('.add-var-to-cart-btn').first().removeClass('inactive').addClass('active');
    });

    $('body').on('click', '.add-var-to-cart-btn', function (e) {
        e.preventDefault();

        const s = bookingStore.get();

        const product_id = Number($(this).data('product_id'));
        const modal_id = '#product_modal_' + product_id;
        const checked_radio = $(modal_id + ' input[type=radio]:checked');
        const variation_ids = [];
        const person_count = $('.person-count').length ? Number($('.person-count').attr('value')) : 1;

        $(modal_id + ' select').each(function () {
            variation_ids.push($(this).val());
        });

        if (checked_radio.length) {
            variation_ids.push(checked_radio.val());
        }

        const payload = {
            action: 'add_variation_to_cart',
            product_id: product_id,
            variation_ids: variation_ids,
            quantity: person_count,
        };

        $.post(ajax_object_extras.ajax_url_extras, payload, function (response) {
            $('#message').removeClass('d-none').html('Variation zum Warenkorb hinzugefügt!');

            const $box = $('#extra-box-select-' + product_id);
            $box.addClass('active').removeAttr('data-bs-toggle');

            $('body').trigger('wc_fragment_refresh');

            setTimeout(() => $('#message').addClass('d-none'), 5000);
        });

        return false;
    });

    $('.extra-box-select').each(function(){
        $(this).on('click', function(){
            if (!$(this).hasClass('active')) return;

            const product_id = Number($(this).attr('value-product'));

            const data = {
                url: '/wp-admin/admin-ajax.php?wc-ajax=remove_product_from_cart',
                action: 'remove_product_from_cart',
                product_id: product_id
            };

            $.post(ajax_object_extras.ajax_url_extras, data, function(){
                const $box = $('#extra-box-select-' + product_id);
                $box.attr('data-bs-toggle', 'modal').removeClass('active');
            });
        });
    });

    $('body').on('click', '.remove', function (e) {
        e.preventDefault();

        const link = $(this);
        const product_id = Number(link.data('product_id'));

        const data = {
            url: '/wp-admin/admin-ajax.php?wc-ajax=remove_product_from_cart',
            action: 'remove_product_from_cart',
            product_id: product_id
        };

        $.post(ajax_object_extras.ajax_url_extras, data, function () {
            window.location.href = link.attr('href');
        });
    });

    // --- Personen +/- (im Extra-Modal; beeinflusst nur Anzeige im Modal) -------
    $('body').on('click', '.button-person', function(){
        const s = bookingStore.get();
        const $counter = $('.person-count');
        const current  = Number($counter.attr('value')) || 1;
        const max      = Number(s.person_count) || 1;

        if ($(this).hasClass('plus')) {
            const next = current + 1;
            $counter.text(next).attr('value', next);
            if (next >= max) $(this).addClass('inactive');
            if (next > 1) $('.minus').removeClass('inactive');
            return;
        }
        if ($(this).hasClass('minus')) {
            const next = Math.max(1, current - 1);
            $counter.text(next).attr('value', next);
            if (next <= 1) $(this).addClass('inactive');
            if (next < max) $('.plus').removeClass('inactive');
            return;
        }
    });
});

// Falls du die „Modale Inhalte pro Personenzahl“ noch brauchst:
function changeCount(change_count, product_id) {
    if (product_id != 235) {
        jQuery.post(ajax_object_extras.ajax_url_extras, {
            action: 'element_booking_extras',
            person_count: change_count,
            product_id: product_id
        }, function(res){
            jQuery('#modal-extra-box_' + product_id).html(res.html);
        });
    }
}
