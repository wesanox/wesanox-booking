/**
 * Timeslot Booking Widget — multi-step booking flow.
 *
 * Reuses existing AJAX endpoints:
 *   element_booking_time, element_booking_duration, element_booking_item,
 *   store_booking_payload, add_product_to_cart
 */
(function ($) {
    'use strict';

    function TimeslotBookingWidget(root) {
        var $root    = $(root);
        var areaId   = parseInt($root.data('area-id') || '0', 10);
        var redirect = $root.data('redirect') || '';
        var ajaxUrl  = (typeof ajax_object !== 'undefined') ? ajax_object.ajax_url : '/wp-admin/admin-ajax.php';
        var nonce    = (typeof ajax_object_timeslot !== 'undefined') ? ajax_object_timeslot.nonce : '';

        var state = {
            day:          '',
            person_count: 2,
            start_time:   '',
            stop_time:    '',
            how_long:     0,
            product_id:   '',
        };

        // ── helpers ───────────────────────────────────────────────────────────

        function showPanel(n) {
            $root.find('.wsn-panel').hide();
            $root.find('.wsn-panel[data-panel="' + n + '"]').show();
            $root.find('.wsn-step').removeClass('wsn-step--active wsn-step--done');
            for (var i = 1; i < n; i++) {
                $root.find('.wsn-step[data-step="' + i + '"]').addClass('wsn-step--done');
            }
            $root.find('.wsn-step[data-step="' + n + '"]').addClass('wsn-step--active');
        }

        function loading(on) {
            $root.find('.wesanox-shortcode__loading').toggle(!!on);
        }

        function showErrors(msgs) {
            var $e = $root.find('.wesanox-shortcode__errors');
            if (!msgs || !msgs.length) { $e.hide().empty(); return; }
            var html = '<ul>' + msgs.map(function (m) {
                return '<li>' + $('<span>').text(m).html() + '</li>';
            }).join('') + '</ul>';
            $e.html(html).show();
        }

        function formatDate(ymd) {
            try {
                var d = new Date(ymd + 'T00:00:00');
                return d.toLocaleDateString('de-DE', {
                    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
                });
            } catch (e) { return ymd; }
        }

        function getHourDiff(start, end) {
            if (!start || !end) return 0;
            var sh = parseInt(start.split(':')[0], 10), sm = parseInt(start.split(':')[1], 10);
            var eh = parseInt(end.split(':')[0], 10),   em = parseInt(end.split(':')[1], 10);
            var diff = (eh * 60 + em) - (sh * 60 + sm);
            if (diff < 0) diff += 24 * 60;
            return diff / 60;
        }

        function escAttr(str) {
            return String(str)
                .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
                .replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function syncStore() {
            if (typeof bookingStore !== 'undefined') {
                bookingStore.set({
                    day:          state.day,
                    person_count: state.person_count,
                    start_time:   state.start_time,
                    stop_time:    state.stop_time,
                    how_long:     state.how_long,
                    product_id:   state.product_id,
                    area_id:      areaId,
                });
            }
        }

        function cartUrl() {
            return typeof woocommerce_params !== 'undefined' && woocommerce_params.cart_url
                ? woocommerce_params.cart_url
                : '/warenkorb/';
        }

        // ── step 1 → 2: load time slots ──────────────────────────────────────

        $root.on('click', '.wsn-to-step-2', function () {
            var day     = $root.find('.wsn-date-input').val();
            var persons = parseInt($root.find('.wsn-persons-input').val() || '2', 10);

            if (!day) { showErrors(['Bitte wähle ein Datum.']); return; }

            state.day          = day;
            state.person_count = persons;
            state.start_time   = '';
            showErrors([]);
            loading(true);

            $.ajax({
                url:      ajaxUrl,
                method:   'POST',
                dataType: 'json',
                data:     { action: 'element_booking_time', day: day, start_time: '', area_id: areaId },
            }).done(function (res) {
                loading(false);
                var html = '';
                if (res && res.success && res.data && res.data.html) {
                    html = res.data.html;
                } else if (res && res.html) {
                    html = res.html;
                } else {
                    html = '<p class="text-muted p-2">Keine Zeiten verfügbar.</p>';
                }
                $root.find('.wsn-time-slots').html(html);
                $root.find('.wsn-date-label').text(formatDate(day));
                showPanel(2);
            }).fail(function () {
                loading(false);
                showErrors(['Fehler beim Laden der Uhrzeiten. Bitte versuche es erneut.']);
            });
        });

        // ── step 2: time box selected ─────────────────────────────────────────

        $root.on('click', '.wsn-time-slots .time-box', function () {
            var startTime = $(this).data('start-time') || $(this).text().trim();
            $root.find('.wsn-time-slots .time-box').removeClass('active');
            $(this).addClass('active');
            state.start_time = startTime;

            loading(true);
            $.ajax({
                url:      ajaxUrl,
                method:   'POST',
                dataType: 'json',
                data:     { action: 'element_booking_duration', start_time: startTime, day: state.day, area_id: areaId },
            }).done(function (res) {
                loading(false);
                var html = (res && res.html) ? res.html : '';
                $root.find('.wsn-duration-wrap').html(html);
                state.stop_time = $root.find('.wsn-duration-wrap select').val() || '';
                showPanel(3);
            }).fail(function () {
                loading(false);
                showErrors(['Fehler beim Laden der Dauer.']);
            });
        });

        // ── step 2 back ───────────────────────────────────────────────────────

        $root.on('click', '.wsn-panel[data-panel="2"] .wsn-back', function () { showPanel(1); });

        // ── step 3: duration change ───────────────────────────────────────────

        $root.on('change', '.wsn-duration-wrap select', function () {
            state.stop_time = $(this).val();
        });

        // ── step 3 → 4: load items ────────────────────────────────────────────

        $root.on('click', '.wsn-to-step-4', function () {
            if (!state.stop_time) {
                state.stop_time = $root.find('.wsn-duration-wrap select').val() || '';
            }
            if (!state.stop_time) { showErrors(['Bitte wähle eine Dauer.']); return; }

            state.how_long = getHourDiff(state.start_time, state.stop_time);
            syncStore();
            showErrors([]);
            loading(true);

            $.ajax({
                url:      ajaxUrl,
                method:   'POST',
                dataType: 'json',
                data: {
                    action:     'element_booking_item',
                    start_time: state.start_time,
                    stop_time:  state.stop_time,
                    day:        state.day,
                    area_id:    areaId,
                },
            }).done(function (res) {
                loading(false);
                renderItems(res, 'p.P./Std.');
                showPanel(4);
            }).fail(function () {
                loading(false);
                showErrors(['Fehler beim Laden der Räume.']);
            });
        });

        // ── step 3 back ───────────────────────────────────────────────────────

        $root.on('click', '.wsn-panel[data-panel="3"] .wsn-back', function () { showPanel(2); });

        // ── step 4: item selection ────────────────────────────────────────────

        $root.on('click', '.wsn-items .wsn-book-btn:not([disabled])', function () {
            state.product_id = String($(this).data('product-id'));
            syncStore();

            $root.find('.wsn-book-btn').prop('disabled', false).text('Buchen');
            $(this).prop('disabled', true).text('Ausgewählt ✓');

            addToCart();
        });

        // ── step 4 back ───────────────────────────────────────────────────────

        $root.on('click', '.wsn-panel[data-panel="4"] .wsn-back', function () { showPanel(3); });

        // ── render product cards ──────────────────────────────────────────────

        function renderItems(res, unit) {
            var cards = [
                { n: 1, price: res.variation_price_one, varId: res.variation_id_one, available: res.available_one },
                { n: 2, price: res.variation_price_two, varId: res.variation_id_two, available: res.available_two },
            ];
            var html = cards.map(function (c) {
                var priceHtml = c.price
                    ? '<div class="wsn-item-card__price"><strong>' + c.price + ' €</strong>' +
                      '<span class="wsn-item-card__unit"> ' + unit + '</span></div>' +
                      '<small class="d-block text-muted mb-2">inkl. MwSt.</small>'
                    : '';
                var btnHtml = c.available
                    ? '<button class="btn btn-primary wsn-book-btn w-100 mt-3" data-product-id="' + escAttr(String(c.varId)) + '">Buchen</button>'
                    : '<button class="btn btn-primary wsn-book-btn w-100 mt-3" disabled>Ausgebucht</button>';
                return '<div class="wsn-item-card bg-white p-3">' +
                    '<h5 class="text-blue text-uppercase">Suite ' + c.n + '</h5>' +
                    '<hr class="my-2">' +
                    priceHtml +
                    btnHtml +
                    '</div>';
            }).join('');
            $root.find('.wsn-items').html(html);
        }

        // ── add to cart + redirect ────────────────────────────────────────────

        function addToCart() {
            showErrors([]);
            loading(true);
            syncStore();

            var s = (typeof bookingStore !== 'undefined') ? bookingStore.get() : state;

            $.ajax({
                url:    ajaxUrl,
                method: 'POST',
                data:   { action: 'store_booking_payload', nonce: nonce, payload: JSON.stringify(s) },
            }).always(function () {
                $.ajax({
                    url:      ajaxUrl,
                    method:   'POST',
                    dataType: 'json',
                    data: {
                        action:       'add_product_to_cart',
                        nonce:        nonce,
                        product_id:   state.product_id,
                        day:          state.day,
                        person_count: state.person_count,
                        start_time:   state.start_time,
                        stop_time:    state.stop_time,
                        how_long:     state.how_long,
                    },
                }).done(function (res) {
                    loading(false);
                    if (res && res.success === false) {
                        showErrors([res.data && res.data.message ? res.data.message : 'Fehler beim Buchen.']);
                        return;
                    }
                    $('body').trigger('wc_fragment_refresh');
                    window.location.href = redirect || cartUrl();
                }).fail(function () {
                    loading(false);
                    showErrors(['Verbindungsfehler. Bitte versuche es erneut.']);
                });
            });
        }
    }

    $(document).ready(function () {
        $('.wsn-timeslot-widget').each(function () {
            new TimeslotBookingWidget(this);
        });
    });

}(jQuery));
