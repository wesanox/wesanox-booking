<?php

declare(strict_types=1);

namespace Wesanox\Booking\View\Frontend\ShortCodes;

defined('ABSPATH') || exit;

use Wesanox\Booking\Application\Shortcut\CheckSuiteAvailabilityService;
use Wesanox\Booking\Application\Shortcut\SuiteBookingRequest;

/**
 * Shortcode [booking_suite] — multi-day / suite availability check widget.
 *
 * Attributes:
 *   area_id      (int)    – 0 = any area (default: 0)
 *   redirect_url (string) – URL for the "Jetzt buchen" CTA (default: '')
 *   title        (string) – Heading shown in the widget (translatable)
 */
final class ShortCodeSuite
{
    private const AJAX_ACTION = 'wesanox_suite_check';
    private const NONCE_KEY   = 'wesanox_suite_nonce';

    private CheckSuiteAvailabilityService $availability_service;

    public function __construct()
    {
        $this->availability_service = new CheckSuiteAvailabilityService();

        add_shortcode('booking_suite', [$this, 'render']);

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
                'title'        => __('Suite verfügbarkeit prüfen', 'wesanox-booking'),
            ],
            is_array($atts) ? $atts : []
        );

        $area_id      = absint($atts['area_id']);
        $redirect_url = esc_url_raw($atts['redirect_url']);
        $title        = sanitize_text_field($atts['title']);
        $nonce        = wp_create_nonce(self::NONCE_KEY);

        ob_start();
        require __DIR__ . '/../Views/shortcode-suite.php';
        return ob_get_clean();
    }

    public function handleAjax(): void
    {
        check_ajax_referer(self::NONCE_KEY, '_nonce');

        $request = SuiteBookingRequest::fromArray([
            'checkin'     => sanitize_text_field($_POST['checkin']     ?? ''),
            'checkout'    => sanitize_text_field($_POST['checkout']    ?? ''),
            'persons'     => absint($_POST['persons']                   ?? 0),
            'area_id'     => absint($_POST['area_id']                   ?? 0),
            'with_extras' => !empty($_POST['with_extras']),
        ]);

        $errors = $request->validate();

        if (!empty($errors)) {
            wp_send_json_error(['errors' => $errors]);
            return;
        }

        $result = $this->availability_service->execute($request);

        wp_send_json_success(array_merge(
            $result->toArray(),
            ['nights_label' => $this->nightsLabel($result->nights)]
        ));
    }

    private function nightsLabel(int $nights): string
    {
        return $nights === 1
            ? __('1 Nacht', 'wesanox-booking')
            : sprintf(__('%d Nächte', 'wesanox-booking'), $nights);
    }
}
