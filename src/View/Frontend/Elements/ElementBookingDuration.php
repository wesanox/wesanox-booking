<?php

namespace Wesanox\Booking\View\Frontend\Elements;

defined('ABSPATH' )|| exit;

use Wesanox\Booking\Application\Booking\CalculateAvailableDurationsService;
use Wesanox\Booking\Service\ServiceGetAvailableRoomarts;
use Wesanox\Booking\Service\ServiceGetAvailableTimes;

class ElementBookingDuration
{
    protected ServiceGetAvailableTimes $service_get_times;
    protected CalculateAvailableDurationsService $duration_service;

    public function __construct()
    {
        $this->service_get_times  = new ServiceGetAvailableTimes();
        $this->duration_service   = new CalculateAvailableDurationsService(
            new ServiceGetAvailableRoomarts()
        );

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
                            <a href="#room-slide" step="5" value="room-box" class="btn btn-primary d-flex justify-content-between align-items-center forward mt-3 inactive">
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
        $datetime = isset($_POST['start_time']) ? sanitize_text_field(wp_unslash($_POST['start_time'])) : '';
        $day      = isset($_POST['day'])        ? sanitize_text_field(wp_unslash($_POST['day']))        : '';

        $start_timestamp = strtotime($datetime);

        if ($datetime === '' || $day === '' || $start_timestamp === false) {
            wp_send_json([
                'message'   => 'Ungültiges Startdatum',
                'html'      => $this->renderUnavailableMessage('Für diese Startzeit ist kein buchbarer Timeslot mehr frei.'),
                'available' => false,
            ]);
            return;
        }

        // Accept area_id from the request (forward-compatible; falls back to first area when absent).
        $area_id = isset($_POST['area_id']) ? absint(wp_unslash($_POST['area_id'])) : null;
        $opening_window = $this->service_get_times->get_opening_window($day, $area_id ?: null);

        if (($opening_window['closed'] ?? false) || empty($opening_window['opening_to'])) {
            wp_send_json([
                'message'   => 'Keine freien Timeslots',
                'html'      => $this->renderUnavailableMessage('An diesem Tag ist keine buchbare Dauer verfügbar.'),
                'available' => false,
            ]);
            return;
        }

        $closing_time_str = $opening_window['opening_to'];

        // Delegate to the Application service which checks both closing time AND room availability.
        $options = $this->duration_service->execute($day, $datetime, $closing_time_str);

        if (empty($options)) {
            wp_send_json([
                'message'   => 'Keine freien Timeslots',
                'html'      => $this->renderUnavailableMessage('Für diese Startzeit ist kein buchbarer Timeslot mehr frei.'),
                'available' => false,
            ]);
            return;
        }

        $html_option = '';
        foreach ($options as $i => $option) {
            $value = $option['end_time'];
            // Normalize midnight display
            if ($value === '00:00') {
                $value = '24:00';
            }
            $selected     = ($option['hours'] === 3 || ($i === 0 && count($options) === 1)) ? ' selected' : '';
            $html_option .= '<option value="' . esc_attr($value) . '"' . $selected . '>'
                          . esc_html($option['label']) . '</option>';
        }

        $html = '<select>' . $html_option . '</select>';

        $response = array(
            'message'   => 'AJAX-Request erfolgreich abgefangen',
            'html'      => $html,
            'available' => true,
        );

        wp_send_json($response);
    }

    private function renderUnavailableMessage(string $message): string
    {
        return '<div class="col-12 p-2 text-center text-muted w-100">' . esc_html($message) . '</div>';
    }

    private function search_booking($start_time) {

    }
}
