<?php

declare(strict_types=1);

namespace Wesanox\Booking\View\Frontend\ShortCodes;

defined('ABSPATH') || exit;

use Wesanox\Booking\Application\Shortcut\CheckTimeslotAvailabilityService;
use Wesanox\Booking\Application\Shortcut\TimeslotBookingRequest;

/**
 * Shortcode [booking_timeslot] — same-day, hourly availability check widget.
 *
 * Attributes:
 *   area_id      (int)    – 0 = any area (default: 0)
 *   redirect_url (string) – URL for the "Jetzt buchen" CTA (default: '')
 *   title        (string) – Heading shown in the widget (translatable)
 */
final class ShortCodeTimeslot
{
    private const AJAX_ACTION = 'wesanox_timeslot_check';
    private const NONCE_KEY   = 'wesanox_timeslot_nonce';

    private CheckTimeslotAvailabilityService $availability_service;

    public function __construct()
    {
        $this->availability_service = new CheckTimeslotAvailabilityService();

        add_shortcode('booking_timeslot', [$this, 'render']);

        add_action('wp_ajax_'        . self::AJAX_ACTION, [$this, 'handleAjax']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, [$this, 'handleAjax']);
    }

    /**
     * @param array|string $atts
     */
    public function render($atts = []): string
    {
        $atts = shortcode_atts(
            [
                'area_id'      => '0',
                'redirect_url' => '',
                'title'        => __('Zeitslot verfügbarkeit prüfen', 'wesanox-booking'),
            ],
            is_array($atts) ? $atts : []
        );

        $area_id      = absint($atts['area_id']);
        $redirect_url = esc_url_raw($atts['redirect_url']);
        $title        = sanitize_text_field($atts['title']);
        $nonce        = wp_create_nonce(self::NONCE_KEY);

        ob_start();
        require __DIR__ . '/../Views/shortcode-timeslot.php';
        return ob_get_clean();
    }

    public function handleAjax(): void
    {
        check_ajax_referer(self::NONCE_KEY, '_nonce');

        $request = TimeslotBookingRequest::fromArray([
            'date'    => sanitize_text_field($_POST['date']    ?? ''),
            'from'    => sanitize_text_field($_POST['from']    ?? ''),
            'to'      => sanitize_text_field($_POST['to']      ?? ''),
            'area_id' => absint($_POST['area_id']              ?? 0),
        ]);

        $errors = $request->validate();

        if (!empty($errors)) {
            wp_send_json_error(['errors' => $errors]);
            return;
        }

        $result = $this->availability_service->execute($request);

        $display_to = ($result->opening_to === '23:59:59') ? '00:00' : $result->opening_to;

        wp_send_json_success(array_merge(
            $result->toArray(),
            ['opening_to_display' => $display_to]
        ));
    }
}
