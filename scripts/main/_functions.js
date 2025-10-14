/**
 * Attaches an AJAX form submission handler to the specified form.
 *
 * @param {string} form_id The ID or selector of the form to be handled.
 * @param {string} action The AJAX action identifier used in the AJAX request.
 * @return {jQuery} The jQuery object representing the form, with the submit handler attached.
 */
function ajaxInsertDataForm(form_id, action)
{
    return jQuery(form_id).submit(function(event) {
        event.preventDefault();

        let formData = jQuery(this).serialize();

        jQuery.ajax({
            url: ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: action + '_insert_data_action',
                _ajax_nonce: ajax_object.nonce,
                data: formData,
            },
            success: function (response) {
                if (!response.success) {
                    alert('Fehler: ' + response.data.message);
                } else {
                    window.location.reload();
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                alert('AJAX-Fehler: ', textStatus, errorThrown);
            }
        });
    });
}

/**
 * Sends an AJAX POST request to insert data from the frontend.
 *
 * @param {Object} data - The data object to be sent with the AJAX request.
 * @param {string} action - The base action name to be appended with '_insert_data_action'.
 * @return {Object} jQuery AJAX promise object allowing further chaining or callbacks.
 */
function ajaxInsertDataFrontend(data, action)
{
    return jQuery.ajax({
        url: ajax_object.ajax_url,
        type: 'POST',
        data: {
            action: action + '_insert_data_action',
            _ajax_nonce: ajax_object.nonce,
            data: jQuery.param(data),
        },
        success: function (response) {
            if (!response.success) {
                alert('Fehler: ' + response.data.message);
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            alert('AJAX-Fehler: ', textStatus, errorThrown);
        }
    });
}

/**
 * Attaches a click event listener to elements with the class "button-delete" to delete data via AJAX.
 * Triggers an AJAX POST request using data attributes from the button's parent element.
 *
 * @return {jQuery} The collection of elements with the class "button-delete" after applying the event listeners.
 */
function ajaxDeleteData() {
    return jQuery('.button-delete').each(function () {
        jQuery(this).on('click', function() {
            const dataId = jQuery(this).parent().data('id');
            const dataAction = jQuery(this).parent().data('action');

            jQuery.ajax({
                url: ajax_object.ajax_url,
                type: 'POST',
                data: {
                    action: dataAction + '_delete_data_action',
                    _ajax_nonce: ajax_object.nonce,
                    dataId: dataId,
                },
                success: function (response) {
                    if (!response.success) {
                        alert('Fehler: ' + response.data.message);
                    } else {
                        window.location.reload();
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.log('AJAX-Fehler: ', textStatus, errorThrown);
                }
            });
        })
    })
}

/**
 * Generates an array of time strings based on a given start time, end time, and interval in minutes.
 *
 * @param {string} start - The start time in "HH:mm" format.
 * @param {string} end - The end time in "HH:mm" format.
 * @param {number} [interval=15] - The time interval in minutes; defaults to 15 if not provided.
 * @return {string[]} An array of time strings in "HH:mm" format between start and end times at the specified interval.
 */
function generateTimeArray(start, end, interval = 15) {
    const times = [];
    let currentTime = parseTimeToMinutes(start);
    const endTime = parseTimeToMinutes(end);

    // Generiere die Zeitintervalle
    while (currentTime <= endTime) {
        times.push(formatTimeFromMinutes(currentTime));
        currentTime += interval;
    }

    return times;
}

/**
 * Converts a given time in minutes to a formatted string in hours, minutes, and seconds.
 *
 * @param {number} minutes - The total number of minutes to be formatted.
 * @return {string} A string representing the formatted time in the "HH:MM:SS" format.
 */
function formatTimeFromMinutes(minutes) {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}`;
}

/**
 * Converts a time string in "HH:MM" format to the total number of minutes.
 *
 * @param {string} time - The time string in "HH:MM" format.
 * @return {number} The total number of minutes represented by the input time.
 */
function parseTimeToMinutes(time) {
    const [hours, mins] = time.split(':').map(Number);
    return hours * 60 + mins;
}

/**
 * Updates the week view based on the provided current date. This updates the displayed week title,
 * sends an AJAX request to fetch booking data, and populates the week view with the response data.
 * Additionally, it adjusts the state of the "previous week" navigation button.
 *
 * @param {string} renderClass A CSS class or selector where the week's rendered HTML will be updated.
 * @param {Date} [currentDate=new Date()] The date within the week to display. Defaults to the current date.
 * @param {Date} [today=new Date()] The reference date to determine the state of the "previous week" navigation button. Defaults to the current date.
 * @return {void}
 */
function updateWeekView(renderClass, currentDate = new Date(), today = new Date()) {
    let week_days = [];
    let weekStart = new Date(currentDate);
    let weekEnd = new Date(weekStart);

    weekStart.setDate(weekStart.getDate() - weekStart.getDay() + 1);
    weekEnd.setDate(weekStart.getDate() + 6);

    jQuery("#week-title").text(`Woche: ${weekStart.toLocaleDateString()} - ${weekEnd.toLocaleDateString()}`);

    for (let i = 0; i < 7; i++) {
        let day = new Date(weekStart);

        day.setDate(day.getDate() + i);
        week_days.push(day.toISOString().split("T")[0]); // YYYY-MM-DD
    }

    // AJAX Request an PHP-Skript senden
    jQuery.ajax({
        url: ajax_object.ajax_url,
        type: "POST",
        data: {
            action:  'booking_calender_render_week',
            _ajax_nonce: ajax_object.nonce,
            week_days: week_days
        },
        success: function (response) {
            let data = response.data;
            let weekHTML = "";

            jQuery(data).each(function () {
                let className = this.classname ? `class="${this.classname}"` : "";
                let username = this.booking_data.account_username ? this.booking_data.account_username : "";
                weekHTML += `<div ${className} day="${this.date}">${new Date(this.date).getDate()}<div>${username}</div></div>`;
            });

            jQuery(renderClass).html(weekHTML);
        }
    });

    if (weekStart <= today) {
        jQuery("#week-prev").addClass("nonactive");
    } else {
        jQuery("#week-prev").removeClass("nonactive");
    }
}
