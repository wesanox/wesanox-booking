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

        const variation_id = Number($(this).data('variation_id'));
        const product_id   = variation_id;
        const person_count = Number(s.person_count) || 1;
        const qty_basic    = getQtyBasicFallback();

        const modalId = '#product_modal_' + product_id;
        let attrs = {};

        if (product_id === 95 || product_id === 96) {
            const val = $(`${modalId} input[type=radio]:checked`).val();
            attrs = { option: val ?? null };
        } else {
            let key = 0;
            $(`${modalId} select`).each(function(){
                key++;
                const attrKey = 'person_' + key;
                attrs[attrKey] = $('option:selected', this).val();
            });
        }

        const nextExtras = upsertExtra([...(s.extras || [])], {
            product_id,
            variation_id,
            qty_basic,
            person_count,
            attrs
        });

        bookingStore.set({ extras: nextExtras });

        const payload = {
            action: 'add_variation_to_cart',
            variation_id: variation_id,
            quantity: person_count,
            quant: qty_basic,
            extras: JSON.stringify(nextExtras)
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
            const s = bookingStore.get();

            const nextExtras = removeExtra([...(s.extras || [])], product_id);
            bookingStore.set({ extras: nextExtras });

            const data = {
                url: '/wp-admin/admin-ajax.php?wc-ajax=remove_product_from_cart',
                action: 'remove_product_from_cart',
                product_id: product_id
            };

            $.post(ajax_object_extras.ajax_url_extras, data, function(){
                const $box = $('#extra-box-select-' + product_id);
                $box.attr('data-bs-toggle', 'modal').removeClass('active');

                // Preis neu holen – falls vorhanden
                // const qty_basic = getQtyBasicFallback();
                // getPrice( s.product_id, nextExtras.map(x => x.product_id), s.person_count, qty_basic );
            });
        });
    });

    $('body').on('click', '.remove', function (e) {
        e.preventDefault();

        const link = $(this);
        const product_id = Number(link.data('product_id'));
        const cartItemKey = link.data('cart_item_key') || link.attr('data-cart_item_key');

        const s = bookingStore.get();
        const nextExtras = removeExtra([...(s.extras || [])], product_id);
        bookingStore.set({ extras: nextExtras });

        $.post(ajax_object_extras.ajax_url_extras, {
            action: 'wesanox_update_booking_session',
            extras: JSON.stringify(nextExtras),
        }, function(res){
            const data = {
                url: '/wp-admin/admin-ajax.php?wc-ajax=remove_product_from_cart',
                action: 'remove_product_from_cart',
                product_id: product_id
            };

            $.post(ajax_object_extras.ajax_url_extras, data, function(){
                window.location.href = link.attr('href');
            });
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
