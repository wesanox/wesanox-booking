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
        duration_minutes: null,
        product_id: null,
        extras: [],
        specials_notice: '',
        area_id: 0,
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

window.wesanoxBookingTime = (() => {
    function normalizeTime(value) {
        if (value === null || typeof value === 'undefined') return '';

        const parts = String(value).trim().split(':');
        if (parts.length < 2) return '';

        const hours = parseInt(parts[0], 10);
        const minutes = parseInt(parts[1], 10);

        if (!Number.isFinite(hours) || !Number.isFinite(minutes)) return '';
        if (hours < 0 || hours > 24 || minutes < 0 || minutes > 59) return '';
        if (hours === 24 && minutes !== 0) return '';

        return String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
    }

    function toMinutes(value) {
        const time = normalizeTime(value);
        if (!time) return null;

        const parts = time.split(':').map(Number);
        return parts[0] * 60 + parts[1];
    }

    function durationMinutes(start, end) {
        const startMinutes = toMinutes(start);
        const endMinutes = toMinutes(end);

        if (startMinutes === null || endMinutes === null) return null;

        let diff = endMinutes - startMinutes;
        if (diff < 0) diff += 24 * 60;

        return diff > 0 ? diff : null;
    }

    function preferredDurationMinutes(state) {
        if (!state) return null;

        const stored = parseInt(state.duration_minutes, 10);
        if (Number.isFinite(stored) && stored > 0) return stored;

        const howLong = parseFloat(state.how_long);
        if (Number.isFinite(howLong) && howLong > 0) {
            return Math.round(howLong * 60);
        }

        return durationMinutes(state.start_time, state.stop_time);
    }

    function stateForTimes(start, end) {
        const stopTime = normalizeTime(end);
        const minutes = durationMinutes(start, stopTime);

        if (!stopTime || minutes === null) {
            return {
                stop_time: null,
                duration_minutes: null,
                how_long: null,
            };
        }

        return {
            stop_time: stopTime,
            duration_minutes: minutes,
            how_long: minutes / 60,
        };
    }

    function applyDurationSelection(select, start, preferredMinutes) {
        const $select = jQuery(select);
        if (!$select.length) return null;

        const startTime = normalizeTime(start);
        if (!startTime) return null;

        const preferred = parseInt(preferredMinutes, 10);
        let fallback = null;
        let selectedOption = null;
        let smallerOrEqual = null;
        let exact = null;

        $select.find('option').each(function () {
            const value = normalizeTime(jQuery(this).val());
            const minutes = durationMinutes(startTime, value);

            if (!value || minutes === null) return;

            const option = {
                value: value,
                minutes: minutes,
            };

            if (fallback === null) {
                fallback = option;
            }

            if (jQuery(this).is(':selected')) {
                selectedOption = option;
            }

            if (Number.isFinite(preferred) && preferred > 0) {
                if (minutes === preferred) {
                    exact = option;
                    return false;
                }

                if (minutes < preferred || minutes === preferred) {
                    smallerOrEqual = option;
                }
            }
        });

        const selected = exact || smallerOrEqual || selectedOption || fallback;
        if (!selected) return null;

        $select.val(selected.value);
        return stateForTimes(startTime, selected.value);
    }

    function hoursLabel(minutes) {
        const parsed = parseInt(minutes, 10);
        if (!Number.isFinite(parsed) || parsed <= 0) return '';

        const hours = parsed / 60;
        return hours + ' Stunden';
    }

    function dateLabel(value) {
        if (!value) return '';

        if (value instanceof Date) {
            return Number.isNaN(value.getTime()) ? '' : value.toLocaleDateString('de-DE');
        }

        const raw = String(value).trim();
        const ymd = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);

        if (ymd) {
            return ymd[3] + '.' + ymd[2] + '.' + ymd[1];
        }

        const date = new Date(raw);
        return Number.isNaN(date.getTime()) ? raw : date.toLocaleDateString('de-DE');
    }

    function bookingRangeLabel(day, start, end) {
        const date = dateLabel(day);
        const startTime = normalizeTime(start);
        const endTime = normalizeTime(end);
        const timeRange = startTime && endTime
            ? startTime + ' - ' + endTime
            : startTime || '';

        return [date, timeRange].filter(Boolean).join(' ');
    }

    return {
        normalizeTime,
        durationMinutes,
        preferredDurationMinutes,
        stateForTimes,
        applyDurationSelection,
        hoursLabel,
        bookingRangeLabel,
    };
})();

document.addEventListener('DOMContentLoaded', function () {
    bookingStore.load();
    // Initialise area_id from the shortcode wrapper attribute (set via [booking_view area_id="X"]).
    var wrapper = document.getElementById('wesanox-booking');
    if (wrapper) {
        var areaId = parseInt(wrapper.getAttribute('data-area-id') || '0', 10);
        if (areaId > 0) {
            bookingStore.set({ area_id: areaId });
        }
    }
});

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
            window.location.href = '/';
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

    if ($("#wesanox-booking").length || $('#wesanox-booking-card').length || $('#wesanox-booking-checkout').length) {
        const a = getActivity();

        if (a) {
            const expiry = a + TTL_MS;

            if (now() >= expiry) {
                onExpire();
            } else {
                startTimerFrom(a);
            }
        } else {
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

function updateBookingRangeNav() {
    if (!window.wesanoxBookingTime || typeof bookingStore === 'undefined') return;

    const state = bookingStore.get();
    const label = window.wesanoxBookingTime.bookingRangeLabel(state.day, state.start_time, state.stop_time);

    if (!label) return;

    checkedSvgNav('#check-in-out', '.day', label);
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
