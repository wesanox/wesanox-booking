/**
 * Suite Booking Widget — multi-step booking flow (date-range, overnight).
 *
 * Step 1: Check-in / check-out dates + persons
 * Step 2: Suite selection (product cards loaded via element_booking_item)
 * Cart:   add_product_to_cart → redirect
 */
(function ($) {
    'use strict';

    function SuiteBookingWidget(root) {
        var $root    = $(root);
        var areaId   = parseInt($root.data('area-id') || '0', 10);
        var redirect = $root.data('redirect') || '';
        var ajaxUrl  = (typeof ajax_object !== 'undefined') ? ajax_object.ajax_url : '/wp-admin/admin-ajax.php';
        var nonce    = (typeof ajax_object_suite !== 'undefined') ? ajax_object_suite.nonce : '';

        var state = {
            checkin:      '',
            checkout:     '',
            person_count: 2,
            nights:       0,
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

        function nightDiff(checkin, checkout) {
            var a = new Date(checkin  + 'T00:00:00');
            var b = new Date(checkout + 'T00:00:00');
            return Math.max(0, Math.round((b - a) / (1000 * 60 * 60 * 24)));
        }

        function nightsLabel(n) {
            return n === 1 ? '1 Nacht' : n + ' Nächte';
        }

        function escAttr(str) {
            return String(str)
                .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
                .replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function syncStore() {
            if (typeof bookingStore !== 'undefined') {
                bookingStore.set({
                    day:          state.checkin,
                    person_count: state.person_count,
                    start_time:   state.checkin,
                    stop_time:    state.checkout,
                    how_long:     state.nights,
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

        // ── sync checkout min-date when checkin changes ───────────────────────

        $root.on('change', '.wsn-checkin-input', function () {
            var checkin   = $(this).val();
            var $checkout = $root.find('.wsn-checkout-input');
            if (checkin) {
                var next = new Date(checkin + 'T00:00:00');
                next.setDate(next.getDate() + 1);
                var min = next.toISOString().slice(0, 10);
                $checkout.attr('min', min);
                if ($checkout.val() && $checkout.val() <= checkin) {
                    $checkout.val(min);
                }
            }
        });

        // ── step 1 → 2: check availability, then load items ──────────────────

        $root.on('click', '.wsn-to-step-2', function () {
            var checkin    = $root.find('.wsn-checkin-input').val();
            var checkout   = $root.find('.wsn-checkout-input').val();
            var persons    = parseInt($root.find('.wsn-persons-input').val() || '2', 10);
            var suiteNonce = $root.find('.wsn-suite-nonce').val() || '';

            if (!checkin || !checkout) {
                showErrors(['Bitte wähle An- und Abreisedatum.']); return;
            }
            if (checkout <= checkin) {
                showErrors(['Das Abreisedatum muss nach dem Anreisedatum liegen.']); return;
            }
            if (persons < 1) {
                showErrors(['Mindestens 1 Person erforderlich.']); return;
            }

            state.checkin      = checkin;
            state.checkout     = checkout;
            state.person_count = persons;
            state.nights       = nightDiff(checkin, checkout);

            showErrors([]);
            loading(true);

            // Validate availability first
            $.ajax({
                url:      ajaxUrl,
                method:   'POST',
                dataType: 'json',
                data: {
                    action:   'wesanox_suite_check',
                    _nonce:   suiteNonce,
                    checkin:  checkin,
                    checkout: checkout,
                    persons:  persons,
                    area_id:  areaId,
                },
            }).done(function (res) {
                if (!res.success) {
                    loading(false);
                    showErrors(res.data && res.data.errors ? res.data.errors : ['Fehler bei der Verfügbarkeitsprüfung.']);
                    return;
                }
                if (!res.data.available) {
                    loading(false);
                    showErrors(['Für diesen Zeitraum sind leider keine Suiten verfügbar.']);
                    return;
                }

                // Load product cards for the check-in date
                $.ajax({
                    url:      ajaxUrl,
                    method:   'POST',
                    dataType: 'json',
                    data: {
                        action:     'element_booking_item',
                        start_time: '08:00',
                        stop_time:  '18:00',
                        day:        checkin,
                        area_id:    areaId,
                    },
                }).done(function (itemRes) {
                    loading(false);
                    $root.find('.wsn-suite-nights-label').text(
                        nightsLabel(state.nights) + ' · ' + persons +
                        (persons === 1 ? ' Person' : ' Personen')
                    );
                    renderItems(itemRes);
                    showPanel(2);
                }).fail(function () {
                    loading(false);
                    showErrors(['Fehler beim Laden der Suiten.']);
                });

            }).fail(function () {
                loading(false);
                showErrors(['Verbindungsfehler. Bitte versuche es erneut.']);
            });
        });

        // ── step 2 back ───────────────────────────────────────────────────────

        $root.on('click', '.wsn-panel[data-panel="2"] .wsn-back', function () { showPanel(1); });

        // ── step 2: suite selected ────────────────────────────────────────────

        $root.on('click', '.wsn-items .wsn-book-btn:not([disabled])', function () {
            state.product_id = String($(this).data('product-id'));
            syncStore();

            $root.find('.wsn-book-btn').prop('disabled', false).text('Jetzt buchen');
            $(this).prop('disabled', true).text('Ausgewählt ✓');

            addToCart();
        });

        // ── render product cards ──────────────────────────────────────────────

        function renderItems(res) {
            var cards = [
                { n: 1, price: res.variation_price_one, varId: res.variation_id_one, available: res.available_one },
                { n: 2, price: res.variation_price_two, varId: res.variation_id_two, available: res.available_two },
            ];
            var html = cards.map(function (c) {
                var priceHtml = c.price
                    ? '<div class="wsn-item-card__price"><strong>' + c.price + ' €</strong>' +
                      '<span class="wsn-item-card__unit"> p.P./Nacht</span></div>' +
                      '<small class="d-block text-muted mb-2">inkl. MwSt.</small>'
                    : '';
                var btnHtml = c.available
                    ? '<button class="btn btn-primary wsn-book-btn w-100 mt-3" data-product-id="' + escAttr(String(c.varId)) + '">Jetzt buchen</button>'
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

            var s = (typeof bookingStore !== 'undefined') ? bookingStore.get() : {};

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
                        day:          state.checkin,
                        person_count: state.person_count,
                        start_time:   state.checkin,
                        stop_time:    state.checkout,
                        how_long:     state.nights,
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
        $('.wsn-suite-widget').each(function () {
            new SuiteBookingWidget(this);
        });
    });

}(jQuery));
