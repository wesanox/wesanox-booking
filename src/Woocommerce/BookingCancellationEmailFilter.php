<?php

declare(strict_types=1);

namespace Wesanox\Booking\Woocommerce;

defined('ABSPATH') || exit;

/**
 * Cancellation mails for booking orders are sent by the Laravel backend, which distinguishes guest
 * cancellations, cancellations for non-payment and back-office cancellations. The identical
 * WooCommerce "cancelled order" mails are therefore suppressed for orders that contain a booking;
 * all other orders (e.g. voucher purchases) keep their WooCommerce mails.
 */
final class BookingCancellationEmailFilter
{
    /** @var list<string> */
    public const SUPPRESSED_EMAIL_IDS = ['cancelled_order', 'customer_cancelled_order'];

    private const BOOKING_META_KEY = '_booking_id';

    public function register(): void
    {
        foreach (self::SUPPRESSED_EMAIL_IDS as $email_id) {
            add_filter('woocommerce_email_enabled_' . $email_id, [$this, 'filterEnabled'], 10, 2);
        }
    }

    public function filterEnabled(mixed $enabled, mixed $order = null): bool
    {
        if (!$enabled) {
            return false;
        }

        return !self::isBookingOrder($order);
    }

    public static function isBookingOrder(mixed $order): bool
    {
        return is_object($order)
            && method_exists($order, 'get_meta')
            && (string) $order->get_meta(self::BOOKING_META_KEY, true) !== '';
    }
}
