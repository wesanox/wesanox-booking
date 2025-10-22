<?php

namespace Wesanox\Booking\View\Frontend\Elements;

defined( 'ABSPATH' )|| exit;

use Wesanox\Booking\Service\ServiceBookingTime;
class ElementBookingTime
{
    protected ServiceBookingTime $service_booking_time;

    public function __construct()
    {
        $this->service_booking_time = new ServiceBookingTime();

        add_action('wp_ajax_element_booking_time', [$this, 'wesanox_ajax_element_booking_time']);
        add_action('wp_ajax_nopriv_element_booking_time', [$this, 'wesanox_ajax_element_booking_time']);
    }

    /**
     * @param $active_box
     * @param $active
     * @return string
     */
    public function wesanox_render_element_booking_time($active_box, $active): string
    {
        return '
            <div id="element-booking-time" class="col-12 col-md-6 col-xl-3 py-4 px-3 position-relative' . $active_box . ' step">
                <h4 class="mb-3">Verfügbare Startzeiten</h4>
                <div class="d-flex align-items-center justify-content-center box-header">
                    <strong>
                        Bitte Uhrzeit auswählen
                    </strong>
                </div>
                <div id="booking-time-box" class="d-flex flex-wrap position-relative">
                    <div id="loading-three" class="position-absolute" style="display: none;">
                        <div class="loader"></div>
                    </div>
                </div>
                <div class="position-lg-absolute bottom-0 w-100 d-flex  justify-content-between justify-content-md-end px-lg-4 pb-lg-4">
                    <a id="check-in-out-box-back" class="btn btn-primary d-flex d-md-none justify-content-between align-items-center back mt-3">
                        <span><</span> Zurück
                    </a>
                    <a href="#how-long-time-slide" value="how-long-time-box" step="4" class="btn btn-primary d-flex justify-content-between align-items-center forward mt-3' . $active . '">
                        Weiter <span>></span>
                    </a>
                </div>
            </div>
            ';
    }

    /**
     * Handles AJAX requests to render available times for a selected day and start date.
     *
     * This method processes the incoming POST request, validates the provided data,
     * and retrieves the available times using the service_booking_time object.
     * It responds with a JSON object containing the HTML of the available times
     * or an error message if the selected day is not provided.
     *
     * @return void Outputs a JSON response with either the HTML of available times or an error message.
     */
    public function wesanox_ajax_element_booking_time(): void
    {
//        if ( isset($_POST['nonce']) && ! wp_verify_nonce( $_POST['nonce'], 'booking_nonce' ) ) {
//            wp_send_json_error(['message' => 'Ungültige Anfrage (Nonce).'], 403);
//        }

        $booking = ( function_exists('WC') && WC()->session ) ? (array) WC()->session->get('booking', []) : [];

        $selected_day = isset($_POST['day']) ? sanitize_text_field( wp_unslash($_POST['day']) ) : '';
        $start_date   = isset($_POST['start_time']) ? sanitize_text_field( wp_unslash($_POST['start_time']) ) : '';

        if ($selected_day === '' && !empty($booking['day'])) {
            $selected_day = sanitize_text_field($booking['day']);
        }

        if ($start_date === '') {
            $bDay  = !empty($booking['day']) ? sanitize_text_field($booking['day']) : '';
            $bTime = !empty($booking['start_time']) ? sanitize_text_field($booking['start_time']) : '';
            if ($bDay && $bTime) {
                $start_date = $bDay . ' ' . $bTime;
            }
        }

        if ($selected_day === '') {
            wp_send_json_error(['message' => 'Kein Datum übergeben und kein gespeicherter Tag vorhanden.'], 400);
        }

        $html = $this->service_booking_time->getTimeSeparatet($selected_day, $start_date);

        wp_send_json_success(['html' => $html]);
    }
}