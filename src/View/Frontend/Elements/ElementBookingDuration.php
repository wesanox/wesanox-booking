<?php

namespace Wesanox\Booking\View\Frontend\Elements;

defined('ABSPATH' )|| exit;

use Wesanox\Booking\Service\ServiceGetAvailableTimes;

class ElementBookingDuration
{
    protected ServiceGetAvailableTimes $service_get_times;

    public function __construct()
    {
        $this->service_get_times = new ServiceGetAvailableTimes();

        add_action('wp_ajax_element_booking_duration', [$this, 'wesanox_ajax_element_booking_duration']);
        add_action('wp_ajax_nopriv_element_booking_duration', [$this, 'wesanox_ajax_element_booking_duration']);
    }

    /**
     * @return string
     */
    public function wesanox_render_element_booking_duration(): string
    {
        return '
            <div data-hash="how-long-time-slide" class="swiper-slide">
                <div class="row mx-0 w-100">
                    <div id="element-booking-duration" class="col-12 col-md-6 col-xl-3 offset-md-6 offset-xl-0 py-4 px-3 position-relative step">
                        <div id="loading-four" class="position-absolute" style="display: none;">
                            <div class="loader"></div>
                        </div>
                        <h4 class="mb-3">Dauer des Aufenthalts</h4>
                        <div id="how-long-selected" class="d-flex align-items-center justify-content-center">
                        </div>
                        <div class="d-flex flex-wrap">
                            <div class="bg-white mt-2 py-3 px-2">
                                <div class="text-justify">
                                    Für eine perfekte Entspannung empfehlen wir eine Aufenthaltsdauer von mindestens drei Stunden.
                                </div>
                            </div>
                        </div>
                        <div class="w-100 d-flex justify-content-between">
                            <a href="#check-in-out-slide" class="btn btn-primary d-flex justify-content-between align-items-center back mt-3">
                                <span><</span> Zurück
                            </a>
                            <a href="#room-slide" step="5" value="room-box" class="btn btn-primary d-flex justify-content-between align-items-center forward mt-3 active">
                                Weiter <span>></span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>';
    }

    /**
     * Handles an AJAX request to calculate and return booking duration options based on a provided start date.
     *
     * This method processes a date provided via a POST request, calculates available booking durations,
     * formats them into HTML <select> options, and sends the result back as a JSON response.
     * The session variable 'start_date' is updated to reflect the selected start date.
     *
     * The response contains the message about the status of the operation and the generated HTML for duration options.
     *
     * @return void Sends a JSON response containing a message and HTML for booking duration options.
     */
    public function wesanox_ajax_element_booking_duration(): void
    {
        if (isset($_POST['start_time']) && isset($_POST['day'])) {
            $datetime = sanitize_text_field($_POST['start_time']);
            $day = sanitize_text_field($_POST['day']);
        } else {
            $datetime = date('Y-m-d H:i:s');
        }

        $html_option = '';
        $start_timestamp = strtotime($datetime);

        if ($start_timestamp === false) {
            wp_send_json(array('message' => 'Ungültiges Startdatum'));
            return;
        }

        $data = $this->service_get_times->wesanox_get_available_times($day);

        $closing_time_str = $this->service_get_times->get_opening_window($day)['opening_to'] ?? '24:00:00';

        $closing_edge_ts = ($closing_time_str === '23:59:59')
            ? strtotime(date('Y-m-d', $start_timestamp) . ' 24:00:00')
            : strtotime(date('Y-m-d', $start_timestamp) . ' ' . $closing_time_str);

        $html_option = '';
        for ($hours = 2; $hours <= 5; $hours++) {
            $end_ts = strtotime("+{$hours} hours", $start_timestamp);

            if ($end_ts > $closing_edge_ts) {
                break;
            }

            $label_end = date('H:i', $end_ts);
            if (date('H:i', $end_ts) === '00:00') {
                $label_end = '24:00';
            }

            $selected = ($hours === 3) ? ' selected' : '';
            $html_option .= '<option value="' . $label_end . '"' . $selected . '>' . $hours . ' Stunden</option>';
        }

        $html = '<select>' . $html_option . '</select>';

        $response = array(
            'message' => 'AJAX-Request erfolgreich abgefangen',
            'html'    => $html,
        );

        wp_send_json($response);
    }

    private function search_booking($start_time) {

    }
}