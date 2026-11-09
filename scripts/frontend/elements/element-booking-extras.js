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

    $('body').on('input', '.booking-specials-notice', function () {
        const specialsNotice = $(this).val();

        bookingStore.set({ specials_notice: specialsNotice });
        $('.booking-specials-notice').not(this).val(specialsNotice);
    });

    $('body').on('change', '.booking-has-specials-notice', function () {
        const hasSpecialsNotice = $(this).is(':checked');

        $('.booking-has-specials-notice')
            .prop('checked', hasSpecialsNotice)
            .attr('aria-expanded', hasSpecialsNotice ? 'true' : 'false');
        $('.booking-specials-notice-field').toggleClass('d-none', !hasSpecialsNotice);

        if (!hasSpecialsNotice) {
            $('.booking-specials-notice').val('');
            bookingStore.set({ specials_notice: '' });
        }
    });

    $('body').on('click', '.booking-extras-continue', function (e) {
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) {
            return;
        }

        e.preventDefault();

        const $link = $(this);
        const href = $link.attr('href');
        if (typeof syncBookingToServer !== 'function') {
            window.location.href = href;
            return;
        }

        $link.addClass('inactive').attr('aria-disabled', 'true');

        syncBookingToServer()
            .done(function (response) {
                if (!response || response.success === false) {
                    restoreExtrasContinueLink($link);
                    showSpecialsNoticeError('Der Hinweis konnte nicht gespeichert werden. Bitte versuche es erneut.');
                    return;
                }

                window.location.href = href;
            })
            .fail(function () {
                restoreExtrasContinueLink($link);
                showSpecialsNoticeError('Der Hinweis konnte nicht gespeichert werden. Bitte versuche es erneut.');
            });
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
            nonce: ajax_object_extras.nonce,
        };

        $.post(ajax_object_extras.ajax_url_extras, payload, function (response) {
            if (!response || response.success === false) {
                console.error('Fehler beim Hinzufügen der Variation:', response);
                return;
            }

            $('#message').removeClass('d-none').html('Variation zum Warenkorb hinzugefügt!');

            // Keep bookingStore.extras in sync so store_booking_payload carries them to WC session
            const currentExtras = bookingStore.get().extras || [];
            const updatedExtras = upsertExtra(currentExtras, { product_id: product_id, variation_ids: variation_ids });
            bookingStore.set({ extras: updatedExtras });

            if (typeof syncBookingToServer === 'function') {
                syncBookingToServer();
            }

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

    function restoreExtrasContinueLink($link) {
        $link.removeClass('inactive').removeAttr('aria-disabled');
    }

    function showSpecialsNoticeError(message) {
        $('#message')
            .addClass('text-danger')
            .removeClass('d-none')
            .text(message);
    }

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
            const $container = jQuery('#modal-extra-box_' + product_id);
            $container.html(res.html);

            const $specialsNotice = $container.find('.booking-specials-notice');
            if (!$specialsNotice.length) return;

            const storedSpecialsNotice = bookingStore.get().specials_notice || '';
            const $hasSpecialsNotice = $container.find('.booking-has-specials-notice');
            const $specialsNoticeField = $container.find('.booking-specials-notice-field');

            if (storedSpecialsNotice) {
                $specialsNotice.val(storedSpecialsNotice);
                $hasSpecialsNotice.prop('checked', true).attr('aria-expanded', 'true');
                $specialsNoticeField.removeClass('d-none');
            } else if ($specialsNotice.val()) {
                bookingStore.set({ specials_notice: $specialsNotice.val() });
                $hasSpecialsNotice.prop('checked', true).attr('aria-expanded', 'true');
                $specialsNoticeField.removeClass('d-none');
            } else {
                $hasSpecialsNotice.prop('checked', false).attr('aria-expanded', 'false');
                $specialsNoticeField.addClass('d-none');
            }
        });
    }
}
