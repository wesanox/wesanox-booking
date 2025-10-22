let href            = window.location.hash;
let variation_ids   = [];
let options_select  = [];
let extra_select    = [];
let reset = false;

/**
 * check if there are a personselect in the session storage
 */
if ( sessionStorage.getItem("options_select") ) {
    options_select  = JSON.parse(sessionStorage.getItem("options_select"));

    if(options_select.length != 0) {
        let count = options_select[0]['person_count'];

        changeCount(count, 92);
        changeCount(count, 93);
        changeCount(count, 94);
        changeCount(count, 95);
        changeCount(count, 96);
    }
} else {
    setSessionParam("options_select", options_select);
}

if ( sessionStorage.getItem("extra_select") ) {
    extra_select  = JSON.parse(sessionStorage.getItem("extra_select"));
} else {
    setSessionParam("extra_select", extra_select);
}

/**
 * Check if the cart a empty / if not, display the cart icon
 */
getCheckCart();

jQuery(document).ready(function($) {
    /**
     * show the step preloader
     */
    $('#loading').show();
    $('#loading-three').show();
    $('#loading-four').show();
    $('#loading-five').show();

    /**
     * display some stuff after checkout
     */
    $('body').on('click', '#place_order', function() {
        let isNoValidate = document.forms['checkout'].noValidate;

        if (isNoValidate !== true) {
            $('#medi-booking-card').addClass('d-none');
            $('#after-booking').removeClass('d-none');

            sessionStorage.removeItem("activity");
        }
    });

    if(window.location.href.includes("kasse/order-received")) {
        $('#medi-booking-card').addClass('d-none');
        $('#after-booking').removeClass('d-none');

        sessionStorage.removeItem("activity");
    }

    /**
     * Delete Button for the cart
     */
    $('body').on('click', '.delete-cart', function() {
        let activity = new Date();

        data = {
            action: 'delete_cart_booking',
        }

        $.post(ajax_object.ajaxurl, data, function(response) {
            options_select = [];
            extra_select = [];

            setSessionParam("options_select", options_select);
            setSessionParam("extra_select", extra_select);

            sessionStorage.setItem("activity", activity);
            sessionStorage.removeItem("activity");

            window.location.href = '/';
        });
    });

    /**
     * delete the card after 20 minutes with an ajax-call
     */
    if ($("#wesanox-booking").length || $('#wesanox-booking-card').length ) {
        if ( sessionStorage.getItem("activity") ) {
            let previousActivity = new Date(sessionStorage.getItem("activity"));
            let activity = new Date();

            $('.timer-box').removeClass('d-none');

            let elapsedTime = activity.getTime() - previousActivity.getTime();

            let timeLeft = 1200000 - elapsedTime; // Zeit in Millisekunden

            // Initialisiere den Timer
            $('#timer').html(convertTime(timeLeft));

            let timerInterval = setInterval(function() {
                timeLeft -= 1000; // Ticks jede Sekunde
                if(timeLeft <= 0) {
                    clearInterval(timerInterval);
                }
                else {
                    $('#timer').html(convertTime(timeLeft));
                }
            }, 1000);

            // Konvertiere Millisekunden in Minuten und Sekunden
            function convertTime(ms) {
                let minutes = Math.floor(ms / 60000);
                ms %= 60000;
                let seconds = Math.floor(ms / 1000);

                return `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
            }

            setInterval(function() {
                previousActivity = new Date(sessionStorage.getItem("activity"));
                activity = new Date();

                if ( ( activity - previousActivity ) > 1200000 ) {
                    data = {
                        action: 'delete_cart_booking',
                    }

                    $.post(ajax_object.ajaxurl, data, function(response) {
                        options_select = [];
                        extra_select = [];

                        setSessionParam("options_select", options_select);
                        setSessionParam("extra_select", extra_select);

                        sessionStorage.setItem("activity", activity);

                        reset = true;

                        window.location.reload();
                    });
                }
            }, 1000);
        } else {
            $('body').on('click', 'a.active', function () {
                let step = $(this).attr('step');

                if (step == 2) {
                    $('.timer-box').removeClass('d-none');

                    let activity = new Date();

                    sessionStorage.setItem("activity", activity);

                    let previousActivity = new Date(sessionStorage.getItem("activity"));

                    let elapsedTime = activity.getTime() - previousActivity.getTime();

                    let timeLeft = 1200000 - elapsedTime; // Zeit in Millisekunden

                    // Initialisiere den Timer
                    $('#timer').html(convertTime(timeLeft));

                    let timerInterval = setInterval(function() {
                        timeLeft -= 1000; // Ticks jede Sekunde
                        if(timeLeft <= 0) {
                            clearInterval(timerInterval);
                        }
                        else {
                            $('#timer').html(convertTime(timeLeft));
                        }
                    }, 1000);

                    // Konvertiere Millisekunden in Minuten und Sekunden
                    function convertTime(ms) {
                        let minutes = Math.floor(ms / 60000);
                        ms %= 60000;
                        let seconds = Math.floor(ms / 1000);

                        return `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
                    }
                }
            });
        }
    }

    /**
     * Slider for the Booking Navigation and Content
     *
     * @type {Swiper}
     */
    var swiper_nav = new Swiper(".nav-swiper", {
        spaceBetween: 0,
        slidesPerView: 5,
        hashNavigation: {
            watchState: true,
        },
        breakpoints: {
            // when window width is >= 320px
            320: {
                slidesPerView: 1,
            },
            // when window width is >= 640px
            765: {
                slidesPerView: 2,
            },
            1200: {
                slidesPerView: 5,
            }
        }
    });

    var swiper_content = new Swiper(".content-swiper", {
        spaceBetween: 0,
        slidesPerView: 1,
        autoHeight: true,
        parallax:true,
        hashNavigation: {
            watchState: true,
        },
    });

    /**
     * link and redirects for the booking navigation
     */
    if( reset === true || options_select.length === 0 ) {
        switch (href) {
            case '#check-in-out-slide' :
                window.location.href = '/';
                href = '';
                break;
            case '#how-long-time-slide' :
                window.location.href = '/';
                href = '';
                break;
            case '#room-slide' :
                window.location.href = '/';
                href = '';
                break;
            case '#extras-slide' :
                window.location.href = '/';
                href = '';
                break;
        }
    }

    /**
     * set functions and selects for the links if opttions_select is set
     */

    if (options_select.length != 0) {
        switch (href) {
            case '#count-people-slide' :
                getStepNumber(1);
                break;
            case '#check-in-out-slide' :
                getStepNumber(3);

                jQuery('#loading').hide();

                if(options_select[1]['day']) {
                    $('#count-days-btn').removeClass('inactive').addClass('active');
                }

                break;
            case '#how-long-time-slide' :
                getStepNumber(4);
                break;
            case '#room-slide' :
                ajaxGetStopDate(options_select[4]['stop_date'], options_select[3]['how_long']);

                getStepNumber(5);

                if ( options_select.length >= 5 ) {
                    let product_id = options_select[5]['product_id'];

                    ajaxGetProductInfo(product_id);
                    getPrice(product_id);

                    $(".add-to-cart-btn").removeClass('inactive').attr('data-product_id', product_id);
                }

                break;
            case '#extras-slide' :
                let person_count    = parseInt( options_select[0]['person_count'] );
                let quantity_basic  = person_count;

                getStepNumber(6);

                if ( options_select.length > 6 ) {
                    quantity_basic = parseInt( options_select[6]['quantity_basic'] )
                }

                if ( extra_select.length > 0 ) {
                    for (let i = 0; i < extra_select.length; i++) {
                        let product_id = parseInt( extra_select[i]['product_id'] );
                        let extra_box       = $('#extra-box-select-' + product_id);

                        let index_var = variation_ids.indexOf(Number(product_id));

                        if (index_var !== -1) {
                            variation_ids.splice(index_var, 1);
                        }

                        variation_ids.push(product_id);

                        extra_box.addClass('active');
                        extra_box.removeAttr('data-bs-toggle');
                    }
                }

                getPrice(options_select[5]['product_id'], variation_ids, person_count, quantity_basic);
                break;
        }
    }

    /**
     * Navigation on click after the hole booking process
     */
    $('body').on('click', '.nav-swiper a.active', function() {
        let id = $(this).attr('id');

        switch( id ) {
            case 'count-people' :
                getStepNumber(1);
                break;
            case 'check-in-out' :
                if ( $(window).width() > 1024 ) {
                    $('#' + id + '-box').css('margin-left', (($('.step-btn').width() + 25) + 'px'));
                }

                ajaxBookingTime(options_select[1]['day']);
                ajaxBookingTimeSelected();

                getStepNumber(3);

                jQuery('#loading').hide();

                if(options_select[1]['day']) {
                    $('#count-days-btn').removeClass('inactive').addClass('active');
                }

                break;
            case 'how-long-time' :
                let start_date = options_select[2]['start_date'];

                $('#loading-four').show();

                getStepNumber(4);

                if ( $(window).width() > 1024 ) {
                    $('#' + id + '-box').css('margin-left', (2 * ($('.step-btn').width() + 25) + 'px'));
                }

                ajaxGetStartDate(start_date);
                break;
            case 'room' :
                ajaxGetStopDate(options_select[4]['stop_date'], options_select[3]['how_long']);

                getStepNumber(5);

                if ( options_select.length >= 5 ) {
                    let product_id = options_select[5]['product_id'];

                    ajaxGetProductInfo(product_id);
                    getPrice(product_id);

                    $(".add-to-cart-btn").removeClass('inactive').attr('data-product_id', product_id);
                }

                break;
            case 'extras' :
                let person_count    = parseInt( options_select[0]['person_count'] );
                let quantity_basic  = person_count;

                getStepNumber(6);

                if ( options_select.length > 6 ) {
                    quantity_basic = parseInt( options_select[6]['quantity_basic'] )
                }

                if ( extra_select.length > 0 ) {
                    for (let i = 0; i < extra_select.length; i++) {
                        let product_id = parseInt( extra_select[i]['product_id'] );
                        let extra_box       = $('#extra-box-select-' + product_id);

                        let index_var = variation_ids.indexOf(Number(product_id));

                        if (index_var !== -1) {
                            variation_ids.splice(index_var, 1);
                        }

                        variation_ids.push(product_id);

                        extra_box.addClass('active');
                        extra_box.removeAttr('data-bs-toggle');
                    }
                }

                getPrice(options_select[5]['product_id'], variation_ids, person_count, quantity_basic);
        }
    });


    /**
     * Fifth Step - Add to cart mechanism and some click - Event stuff
     */
    $("body").on('click', '.get-product_id', function() {
        let elementId = $(this).attr('id');
        let product_id = 0;

        product_id = $('#' + elementId).attr('data-product_id');

        $('.get-product_id').each(function() {
            $(this).removeClass('inactive').html('BUCHEN');
        });

        $(".add-to-cart-btn").removeClass('inactive').attr('data-product_id', product_id);

        getPrice(product_id);

        // if ( extra_select.length > 0 ) {
        //     for ( $i = 0; $i <= extra_select.length; $i++ ) {
        //         console.log(extra_select[$i].product_id);
        //     }
        //
        //     getPrice(product_id);
        // } else {
        //     getPrice(product_id);
        // }

        if (options_select.length > 5) {
            overrideSessionParam(options_select, 'product_id', product_id);
        } else {
            options_select.push({product_id: product_id});
        }

        setSessionParam("options_select", options_select);

        $(this).addClass('inactive').html('AUSGEWÄHLT');
    });

    $(".add-to-cart-btn").click(function() {
        var product_id = $(this).data("product_id");

        var data = {
            action: "add_product_to_cart",
            product_id: product_id
        };

        jQuery.post(ajax_object.ajax_url, data, function(response) {
            $('#message').removeClass('d-none').html("Buchung wurde zum Warenkorb hinzugefügt!");

            $('body').trigger('wc_fragment_refresh');

            sessionStorage.setItem("suite", JSON.stringify(product_id));

            setTimeout(function() {
                $('#message').addClass('d-none');
            }, 5000);
        });

        $('#room-box a.forward').removeClass('inactive').addClass('active');
    });

    $( document.body ).on( 'wc_fragment_refresh', function() {
        getCheckCart();
    });

    $('body').on('click', '.show-settings', function() {
        let show_class = $(this).data('box');

        $('.' + show_class).toggleClass('d-none');
    });

    $('body').on('click', '.btn-settings-close', function() {
        let show_class = $(this).data('box');

        $('.' + show_class).addClass('d-none');
    });


    /**
     * get the product attributes form the variations
     */
    // $('#modal-extra-box_96 input').each( function() {
    //     if ( $(this).is(':checked') ) {
    //         console.log($(this).val());
    //     }
    // });
    //
    // $('#modal-extra-box_93 .product_attributes').each( function () {
    //     console.log($('option:selected').val());
    // });
    //
    // $('#modal-extra-box_92 .product_attributes').each( function () {
    //     console.log($('option:selected').val());
    // });

    /**
     * person count change (modal)
     */
    $('body').on('click', '.button-person', function() {
        let change_count_box = $('.person-count');
        let change_count_value = parseInt( change_count_box.attr('value') );

        // Abrufen von Daten aus Session Storage
        let person_count = parseInt( options_select[0]['person_count'] );

        if($(this).hasClass('plus')) {
            let change_count = change_count_value + 1;

            // changeCount(change_count, 92);
            // changeCount(change_count, 93);
            // changeCount(change_count, 94);

            change_count_box.each(function () {
                $(this).html(change_count);
                $(this).attr('value', change_count)
            })

            if (change_count === person_count) {
                $(this).addClass('inactive');
            }

            if ( change_count > 1 ) {
                $('.minus').removeClass('inactive');
            }
        }

        if($(this).hasClass('minus')) {
            let change_count = change_count_value - 1;

            // changeCount(change_count, 92);
            // changeCount(change_count, 93);
            // changeCount(change_count, 94);

            change_count_box.each( function () {
                $(this).html(change_count);
                $(this).attr('value', change_count)
            })

            if ( change_count === 1 ) {
                $(this).addClass('inactive');
            }

            if ( change_count < person_count ) {
                $('.plus').removeClass('inactive');
            }
        }
    });

    /**
     * Zusatzversichung change in the cart
     */
    $('body').on('click', '.checkbox-extras', function() {
        var product_id = $(this).data("product_id");
        var quant = $(this).data('quant');
        var url = '';

        if($(this).hasClass('active')) {
            url = '/wp-admin/admin-ajax.php?wc-ajax=remove_product_from_cart';
            $(this).removeClass('active');
        } else {
            url = '/wp-admin/admin-ajax.php?wc-ajax=add_product_to_cart_upsell';
            $(this).addClass('active');
        }

        if( $(this).hasClass('btn-active') ) {
            $(this).removeClass('btn-active').html('JETZT HINZUFÜGEN');
        }

        if ( $(this).hasClass('btn-primary') ) {
            $(this).addClass('btn-active').html('AUSGEWÄHLT');
        }

        jQuery.ajax({
            type: 'POST',
            url: url,
            data: {
                action: $(this).hasClass('active') ? 'add_product_to_cart_upsell' : 'remove_product_from_cart',
                product_id: product_id,
                quant: quant
            },
            success: function(response) {
                if (response === 'success') {
                    jQuery.ajax({
                        type: 'POST',
                        url: '/wp-admin/admin-ajax.php?wc-ajax=refresh_cart',
                        data: {
                            action: 'refresh_cart',
                        },
                        success: function (html) {
                            jQuery('#cart-segment').html(html);

                            // Trigger the wc_fragment_refresh event after the cart HTML is updated
                            $('body').trigger('wc_fragment_refresh');
                        }
                    });
                }
            }
        });
    });

    $('body').on('click', '#check-in-out-box-back', function() {
        $('#check-in-out-box').removeClass('d-none');
        $('#count-days-box').addClass('d-none');
    });

    $('body').on('click', 'a.active', function () {
        let id              = $('#' + $(this).attr('value'));
        let btn_id          = $('#' + $(this).attr('value').replace('-box', ''));
        let step            = $(this).attr('step');

        getStepNumber(step);

        if (step == 3) {
            let day = options_select[1]['day'];

            ajaxBookingTime(day);
            ajaxBookingTimeSelected();

            id.removeClass('d-none');
            if ($(window).width() > 765 ) {
                $(this).addClass('d-none');
            }

            if ($(window).width() < 765) {
                $('#check-in-out-box').addClass('d-none');
            }
        } else {
            if(step == 2) {
                $('#count-people-box input').each(function () {
                    if ($(this).prop('checked')) {
                        ajaxPersonCount($(this).attr('name'));
                    }
                });

                swiper_nav.slideTo(1);
            }

            $('#booking-time-box .time-box').each(function () {
                if ($(this).hasClass('active')) {
                    let start_date = $(this).attr('time');

                    ajaxGetStartDate(start_date);
                }
            });

            if(step == 6) {
                checkedSvgNav('#room', '.suite', 'ausgewählt');

                swiper_nav.slideTo(4);
            }

            if (step == 4 && options_select.length > 3 && $(window).width() > 1024) {
                $('#loading-four').show();

                $('#booking-time-box .time-box.active').each(function() {
                    let start_date = $(this).attr('time');

                    var timeString = options_select[3]['how_long'];
                    var timeParts = timeString.split(":");
                    var hours = parseInt(timeParts[0], 10);

                    var hoursToAdd = hours;
                    var parts = start_date.split(' ');
                    var dateParts = parts[0].split('.');
                    var formattedDatetimeString = dateParts[1] + "/" + dateParts[0] + "/" + dateParts[2] + " " + parts[1];
                    var datetime = new Date(formattedDatetimeString);

                    datetime.setHours(datetime.getHours() + hoursToAdd);

                    var year = datetime.getFullYear();
                    var month = ("0" + (datetime.getMonth() + 1)).slice(-2); // Monate beginnen bei 0 in JavaScript
                    var day = ("0" + datetime.getDate()).slice(-2);
                    var hour = ("0" + datetime.getHours()).slice(-2);
                    var minute = ("0" + datetime.getMinutes()).slice(-2);
                    var second = ("0" + datetime.getSeconds()).slice(-2);

                    var datetimeString = year + '-' + month + '-' + day + ' ' + hour + ":" + minute + ":" + second;

                    ajaxGetStopDate(datetimeString, options_select[3]['how_long']);
                });

                swiper_nav.slideTo(2);

                id.css('margin-left', ($(this).attr('step') - 2) * ($('.step-btn').width() + 25) + 'px');
            }

            if (step == 5) {
                $('#loading-five').show();

                ajaxGetStopDate(
                    $('#how-long-selected select').val(),
                    $('#how-long-selected select option:selected').html(),
                    function() {
                        // This callback is executed once ajaxGetStopDate has completed
                        ajaxGetProductInfo(product_id = '');
                        swiper_nav.slideTo(3);
                    }
                )
            }

            if (step == 4 && $(window).width() > 1024) {
                $('#loading-four').show();

                swiper_nav.slideTo(2);

                id.css('margin-left', ($(this).attr('step') - 2) * ($('.step-btn').width() + 25) + 'px');
            } else if ((step == 5 || step == 6) && $(window).width() > 1024) {
                $('.step-boxen').addClass('justify-content-end');
            } else if ($(window).width() > 1024) {
                id.css('margin-left', ($(this).attr('step') - 1) * ($('.step-btn').width() + 25) + 'px');
            }

            $('.step-btn').each(function () {
                if ($(this).hasClass('active')) {
                    $(this).removeClass('active');
                    $(this).addClass('active-background');
                }
            });

            id.removeClass('d-none');

            btn_id.addClass('active');
            btn_id.addClass('swiper-slide-active');
            btn_id.attr('href', '#' + btn_id.attr('id')  + '-slide');
        }
    });
});

/**
 * function to get or set the session varaible for the booking
 *
 * @param param
 * @param array
 */
function setSessionParam(param, array) {
    sessionStorage.setItem(param, JSON.stringify(array));
}

function getSessionParam(param) {
    return JSON.parse(sessionStorage.getItem(param));
}

function overrideSessionParam(array, param, value) {
    let found = false;

    for(let i = 0; i < array.length; i++){
        if(array[i].hasOwnProperty(param)){
            if(!found){
                array[i][param] = value;
                found = true;
            } else {
                array.splice(i, 1);
                i--;
            }
        }
    }
}

/**
 *
 * @param step
 */
function getStepNumber (step) {
    jQuery('#step-count').html(step);
}

function ajaxPersonCount(count) {
    let change_count_box = jQuery('.person-count');
    let data = {
        action: 'booking_person_render',
        person: count
    };

    change_count_box.each(function () {
        jQuery(this).html(count);
        jQuery(this).attr('value', count);
    })

    changeCount(count, 92);
    changeCount(count, 93);
    changeCount(count, 94);
    changeCount(count, 95);
    changeCount(count, 96);

    jQuery.post(ajax_object.ajax_url, data, function(response) {
        jQuery('#count-people .checked').removeClass('d-none').addClass('d-flex');
        jQuery('#count-people .not-checked').removeClass('d-flex').addClass('d-none');

        if (options_select.length > 0) {
            if ( options_select[0]['day'] || options_select[0]['how_long'] || options_select[0]['start_date'] || options_select[0]['stop_date'] || options_select[0]['product_id'] || options_select[0]['quantity_basic'] ) {
                options_select = [];

                options_select.push({person_count: count});
            } else {
                overrideSessionParam(options_select, 'person_count', count);
            }
        } else {
            options_select.push({person_count: count});
        }

        bookingStore.set({ person_count: count });

        setSessionParam("options_select", options_select);

        if ( count > 1 ) {
            jQuery('#count-people .count').html(count + ' Personen')
        } else {
            jQuery('#count-people .count').html(count + ' Person')
        }

        jQuery('#loading').hide();
    });
}

function ajaxBookingTime(day) {
    let start_date = '';

    if ( options_select.length >= 3 ) {
        start_date = options_select[2]['start_date'];
    }

    var data = {
        action: 'booking_available_time_render',
        nonce: ajax_object.nonce,
        day: day,
        start_date: start_date
    };

    jQuery.ajax({
            url: ajax_object.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: data
        })
        .done(function (res) {
            if (res && res.success && res.data && typeof res.data.html !== 'undefined') {
                jQuery('#booking-time-box').html(res.data.html);
            } else if (res && typeof res.html !== 'undefined') {
                jQuery('#booking-time-box').html(res.html);
            } else {
                jQuery('#booking-time-box').html('<div class="col-12 p-2 text-center text-muted">Keine Zeiten gefunden.</div>');
            }
        })
        .fail(function () {
            jQuery('#booking-time-box').html('<div class="col-12 p-2 text-center text-danger">Fehler beim Laden.</div>');
        });
}

function ajaxGetStartDate(start_date) {
    var data = {
        action: 'booking_duration_render',
        start_date: start_date
    };

    jQuery.post(ajax_object.ajax_url, data, function(response) {
        jQuery('#how-long-selected').html(response.html);
        checkedSvgNav('#check-in-out', '.day', start_date);

        if (options_select.length > 2) {
            overrideSessionParam(options_select, 'start_date', start_date);
        } else {
            options_select.push({start_date: start_date});
        }

        setSessionParam("options_select", options_select);

        jQuery('#loading-four').hide();
    });
}

function ajaxGetStopDate(stop_date, stop_date_html, callback) {
    var data = {
        action: 'booking_stop_date_render',
        stop_date: stop_date,
        stop_date_html: stop_date_html
    };

    jQuery.post(ajax_object.ajax_url, data, function(response) {
        checkedSvgNav('#how-long-time', '.how-long', stop_date_html);

        if (options_select.length > 3) {
            overrideSessionParam(options_select, 'how_long', stop_date_html);
            overrideSessionParam(options_select, 'stop_date', stop_date);
        } else {
            options_select.push({how_long: stop_date_html}, {stop_date: stop_date});
        }

        setSessionParam("options_select", options_select);

        // Call the callback function if it exists
        if (typeof callback === "function") {
            callback();
        }
    });
}

function ajaxGetProductInfo(product_id) {
    var data = {
        action: 'booking_product_infos_render',
    };

    jQuery.post(ajax_object.ajax_url, data, function(response) {
        console.log(response);

        if (!response.available_one) {
            jQuery('#variation_price_1').html(response.variation_price_one);
            jQuery('#variation_btn_1').addClass('d-none');
            jQuery('#variation_message_1').removeClass('d-none').css('z-index', 1).html('ausgebucht');
        } else {
            jQuery('#variation_price_1').html(response.variation_price_one);
            jQuery('#variation_btn_1').attr('data-product_id', response.variation_id_one);

            if (jQuery('#variation_btn_1').hasClass('d-none')) {
                jQuery('#variation_btn_1').removeClass('d-none');
                jQuery('#variation_message_1').addClass('d-none');
            }
        }

        if (!response.available_two) {
            jQuery('#variation_price_2').html(response.variation_price_two);
            jQuery('#variation_btn_2').addClass('d-none');
            jQuery('#variation_message_2').removeClass('d-none').css('z-index', 1).html('ausgebucht');
        } else {
            jQuery('#variation_price_2').html(response.variation_price_two);
            jQuery('#variation_btn_2').attr('data-product_id', response.variation_id_two);

            if (jQuery('#variation_btn_2').hasClass('d-none')) {
                jQuery('#variation_btn_2').removeClass('d-none');
                jQuery('#variation_message_2').addClass('d-none');
            }
        }


        if ( response.variation_id_one === parseInt(product_id) ) {
            jQuery('#variation_btn_1').addClass('inactive').html('AUSGEWÄHLT');
        }

        if ( response.variation_id_two === parseInt(product_id) ) {
            jQuery('#variation_btn_2').addClass('inactive').html('AUSGEWÄHLT');
        }

        jQuery('#loading-five').hide();
    });
}

function ajaxBookingTimeSelected() {
    jQuery('body').on('click', '#booking-time-box .time-box', function() {
        jQuery('#booking-time-box .time-box').each(function() {
            if(jQuery(this).hasClass('active')) {
                jQuery(this).removeClass('active');
            }
        });

        let hasProductId = options_select.some(opt => opt.hasOwnProperty('product_id'));

        if (hasProductId) {
            data = {
                action: 'delete_cart_only_booking',
            }

            jQuery.post(ajax_object.ajax_url, data, function(response) {
                extra_select = [];

                setSessionParam("extra_select", extra_select);

                jQuery('body').trigger('wc_fragment_refresh');
            });
        }

        jQuery('#count-days-box a.forward').removeClass('inactive').addClass('active');

        jQuery(this).addClass('active');
    });
}

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

function getPrice(product_id, variation_ids, person_count, quantity_basic) {
    var data = {
        action: 'get_price',
        product_id: product_id,
        variation_ids: variation_ids,
        person_count: person_count,
        quantity_basic: quantity_basic
    }

    jQuery.post(ajax_object.ajax_url, data, function(response) {
        jQuery('#price-booking').removeClass('d-none').addClass('d-flex').html(response.html);
        jQuery('#price-variation').removeClass('d-none').addClass('d-flex').html(response.html);
    });
}

function checkedSvgNav(id_name, class_name, span_text) {
    jQuery(id_name + ' .checked').removeClass('d-none').addClass('d-flex');
    jQuery(id_name + ' .not-checked').removeClass('d-flex').addClass('d-none');
    jQuery(id_name + ' ' + class_name).html(span_text);
}

function changeCount(change_count, product_id) {
    if( product_id != 235) {
        var data = {
            action: 'get_modal',
            person_count: change_count,
            product_id: product_id
        };

        jQuery.post(ajax_object.ajax_url, data, function(response) {
            jQuery('#modal-extra-box_' + product_id).html(response.html);
        });
    }
}