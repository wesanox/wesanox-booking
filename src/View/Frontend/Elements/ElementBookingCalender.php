<?php

namespace Wesanox\Booking\View\Frontend\Elements;

defined( 'ABSPATH' )|| exit;

use Wesanox\Booking\Service\ServiceGetAvailableTimes;

class ElementBookingCalender
{
    protected ServiceGetAvailableTimes $service_get_times;

    public function __construct()
    {
        $this->service_get_times = new ServiceGetAvailableTimes();

        add_action('wp_ajax_booking_calender_render', [$this, 'wesanox_ajax_element_booking_calender']);
        add_action('wp_ajax_nopriv_booking_calender_render', [$this, 'wesanox_ajax_element_booking_calender']);
    }

    /**
     * Renders the frontend booking calendar.
     *
     * This method generates the HTML structure for the booking calendar, including the calendar
     * itself, the available and fully booked indicators, and the navigation buttons.
     *
     * @return string
     */
    public function wesanox_render_element_booking_calender(): string
    {
        return '
            <div id="element-booking-calender" class="col-12 col-md-6 col-xl-3 py-4 px-3 step">
                <h4 class="mb-3">Checkin</h4>
                <div class="position-relative">
                    <div id="loading" class="position-absolute" style="display: none;">
                        <div class="loader"></div>
                    </div>
                    <div id="calendar" class="w-100"></div>                  
                </div>
                <div class="row mt-3 mb-1">
                    <div class="col-12 d-flex align-items-center gap-2 px-3">
                        <span class="available"></span>Verfügbar
                    </div>
                    <div class="col-12 d-flex align-items-center gap-2 px-3">
                        <span class="middle"></span>Hohes Aufkommen
                    </div>
                    <div class="col-12 d-flex align-items-center gap-2 px-3">
                        <span class="fully-booked"></span>Ausgebucht
                    </div>
                </div>
                <div class="w-100 d-flex justify-content-between gap-2">
                    <a href="#count-people-slide" class="btn btn-primary d-flex justify-content-between align-items-center back mt-3">
                        <span><</span> Zurück
                    </a>
                </div>
            </div>';
    }

    /**
     * Handles an AJAX request to generate a booking calendar.
     *
     * This method processes the year and month from the request, calculates the number of days
     * for the specified month and year, and generates an array of booking events. Each event
     * contains a date and a class name based on its availability status: inactive, fully booked,
     * or available. Special handling is applied for past dates and the selected start date
     * in the session.
     *
     * @return void Outputs a JSON response containing the generated booking calendar events.
     */
    public function wesanox_ajax_element_booking_calender(): void
    {
        global $wpdb;

        $year  = isset($_REQUEST['year'])  ? (int) $_REQUEST['year']  : (int) date('Y');
        $month = isset($_REQUEST['month']) ? (int) $_REQUEST['month'] : (int) date('m');
        if ($month < 1 || $month > 12) {
            wp_send_json([]);
        }

        $days_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $today      = current_time('Y-m-d');

        $booking    = ( function_exists('WC') && WC()->session ) ? (array) WC()->session->get('booking', []) : [];
        $selected   = !empty($booking['day']) ? sanitize_text_field($booking['day']) : null; // "YYYY-MM-DD"

        $bookings_table = $wpdb->prefix . 'wesanox_bookings';

        $events = [];
        for ($day = 1; $day <= $days_month; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);

            if ($date < $today) {
                $events[] = ['date' => $date, 'classname' => 'inactive'];
                continue;
            }

            $result = $this->service_get_times->wesanox_get_available_times($date);

            if (is_array($result) && isset($result['closed']) && (int)$result['closed'] === 1) {
                $classname = 'inactive';
            } elseif (is_array($result) && empty($result)) {
                $classname = 'fully-booked';
            } else {
                $classname = 'available';
            }

            // **NEU**: Buchungsanzahl für den Tag prüfen
            if ($classname === 'available') {
                $countForDay = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$bookings_table} WHERE booking_date = %s",
                        $date
                    )
                );

                if ($countForDay > 10) {
                    $classname = 'middle';
                }
            }

            $active = ($selected && $date === $selected) ? ' active' : '';

            $events[] = [
                'date'      => $date,
                'classname' => $classname . $active,
            ];
        }

        wp_send_json(['data' => $events]);
    }
}