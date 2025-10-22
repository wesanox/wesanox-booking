/**
 * A module representing a store for managing booking-related data.
 * Provides methods to load, save, modify, retrieve, and subscribe to changes
 * in booking information, which is persisted in session storage.
 *
 * @module bookingStore
 */
const BOOKING_KEY = 'booking_data';

const bookingStore = (() => {
    let defaultState = {
        person_count: 2,
        day: null,
        start_time: null,
        how_long: null,
        stop_time: null,
        product_id: null,
        extras: [],
    };

    let state = { ...defaultState };
    const listeners = new Set();

    function load() {
        try {
            const raw = sessionStorage.getItem(BOOKING_KEY);
            if (raw) state = {...state, ...JSON.parse(raw)};
        } catch(e) { /* ignore */ }
        notify();
    }

    function save() {
        sessionStorage.setItem(BOOKING_KEY, JSON.stringify(state));
    }

    function notify() {
        listeners.forEach(fn => fn({...state}));
    }

    function set(partial) {
        state = {...state, ...partial};
        save();
        notify();
    }

    function get() { return {...state}; }

    function reset() {
        state = { ...defaultState };
        sessionStorage.removeItem(BOOKING_KEY);
        notify();
    }

    function onChange(fn) { listeners.add(fn); return () => listeners.delete(fn); }

    return { load, set, get, onChange, reset };
})();

document.addEventListener('DOMContentLoaded', bookingStore.load);

/**
 * Check if the cart a empty / if not, display the cart icon
 */
getCheckCart();

/**
 * init
 */
jQuery(document).ready(function($) {
    if( $(window).width() > 1024 ) {
        const step_width = $('.step-btn').width() / 5;

        $(`#element-booking-calender`).css('margin-left', step_width + 25 + 'px');
        $('#element-booking-duration').css('margin-left', step_width * 2 + 25 + 'px');
    }


    /**
     * Slider for the Booking Navigation and Content
     *
     * @type {Swiper}
     */
    window.swiper_nav = new Swiper(".nav-swiper", {
        spaceBetween: 0,
        slidesPerView: 5,
        watchOverflow: false,
        hashNavigation: {
            watchState: true,
        },
        breakpoints: {
            320: {
                slidesPerView: 1,
            },
            765: {
                slidesPerView: 2,
            },
            1200: {
                slidesPerView: 5,
            }
        }
    });

    window.swiper_content = new Swiper(".content-swiper", {
        spaceBetween: 0,
        slidesPerView: 1,
        autoHeight: true,
        parallax:true,
        hashNavigation: {
            watchState: true,
        },
    });

    $( document.body ).on( 'wc_fragment_refresh', function() {
        getCheckCart();
    });

    /**
     * Delete Button for the cart
     */
    $('body').on('click', '.delete-cart', function() {
        data = {
            action: 'delete_cart_booking',
        }

        $.post(ajax_object.ajax_url, data, function(response) {
            bookingStore.reset();

            sessionStorage.removeItem("activity");

            window.location.href = '/';
        });
    });

    /**
     * Timer for the Booking
     * @type {number}
     */
    const TTL_MS = 20 * 60 * 1000;
    const ACTIVITY_KEY = 'activity';
    let timerInterval = null;

    function now() {
        return Date.now();
    }

    function setActivity(ts = now()) {
        sessionStorage.setItem(ACTIVITY_KEY, String(ts));
    }

    function getActivity() {
        const raw = sessionStorage.getItem(ACTIVITY_KEY);
        const n = Number(raw);
        return Number.isFinite(n) ? n : null;
    }

    function clearActivity() {
        sessionStorage.removeItem(ACTIVITY_KEY);
    }

    function formatMMSS(ms) {
        if (ms < 0) ms = 0;
        const m = Math.floor(ms / 60000);
        const s = Math.floor((ms % 60000) / 1000);
        return `${m}:${s < 10 ? '0' : ''}${s}`;
    }

    function stopTimer() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
    }

    function resetUI() {
        $('#timer').html(formatMMSS(TTL_MS));
        $('.timer-box').addClass('d-none');
    }

    function onExpire() {
        stopTimer();
        clearActivity();

        $.post(ajax_object.ajax_url, {action: 'delete_cart_booking'}, function () {
            if (window.bookingStore && bookingStore.reset) {
                bookingStore.reset();
            }
            resetUI();
        });
    }

    function tick(expiryTs) {
        const left = expiryTs - now();
        $('#timer').html(formatMMSS(left));
        if (left <= 0) onExpire();
    }

    function startTimerFrom(activityTs) {
        stopTimer();
        const expiry = activityTs + TTL_MS;

        $('.timer-box').removeClass('d-none');
        $('#timer').html(formatMMSS(expiry - now()));

        timerInterval = setInterval(function () {
            tick(expiry);
        }, 1000);
    }

    if ($("#wesanox-booking").length || $('#wesanox-booking-card').length) {
        const a = getActivity();
        if (a) {
            startTimerFrom(a);
        } else {
            // Erst beim Wechsel auf Step 2 starten
            $('body').on('click', 'a.active', function () {
                const step = Number($(this).attr('step'));
                if (step === 2) {
                    const ts = now();
                    setActivity(ts);
                    startTimerFrom(ts);
                }
            });
        }
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            const a = getActivity();
            if (a && timerInterval) {
                const expiry = a + TTL_MS;
                $('#timer').html(formatMMSS(expiry - now()));
            }
        }
    });
})

/**
 * Header Step Count
 *
 * @param step
 */
function getStepNumber (step) {
    jQuery('#step-count').html(step);
}

/**
 * Updates the state of specified SVG navigation elements by toggling classes and setting text content.
 *
 * @param {string} id_name The selector or ID for the parent container that holds the elements to be updated.
 * @param {string} class_name The selector for the specific child element where the span text will be updated.
 * @param {string} span_text The text content to be set for the specified child element.
 * @return {void} This function does not return a value.
 */
function checkedSvgNav(id_name, class_name, span_text) {
    jQuery(id_name + ' .checked').removeClass('d-none').addClass('d-flex');
    jQuery(id_name + ' .not-checked').removeClass('d-flex').addClass('d-none');
    jQuery(id_name + ' ' + class_name).html(span_text);
}

/**
 * Sends an AJAX POST request to check the cart status and updates the cart icon accordingly.
 *
 * @return {void} This function does not return a value. It updates the UI elements based on the server response.
 */
function getCheckCart() {
    jQuery.ajax({
        url: ajax_object.ajax_url,
        type: 'POST',
        data: {
            action: 'check_cart'
        },
        success: function(response) {
            if(response === 'not_empty'){
                jQuery('#cart_icon').addClass('active');
            } else {
                jQuery('#cart_icon').removeClass('active');
            }
        }
    });
}