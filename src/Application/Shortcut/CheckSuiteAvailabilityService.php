<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Shortcut;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Booking\BookingOccupancy;
use Wesanox\Booking\Repository\RepositoryBooking;

/**
 * Checks room availability for a suite (multi-day) booking.
 *
 * A room is considered unavailable for the stay if it has any booking
 * on any day within the period [checkin, checkout).
 */
final class CheckSuiteAvailabilityService
{
    /** @var string[] WooCommerce order statuses that don't block a room. */
    private const EXCLUDED_STATUSES = ['wc-cancelled', 'wc-refunded', 'wc-failed', 'trash'];

    public function execute(SuiteBookingRequest $request): SuiteAvailabilityResult
    {
        global $wpdb;

        $this->cleanupExpiredCheckoutHolds();

        $rooms_table    = $wpdb->prefix . 'wesanox_rooms';
        $items_table    = $wpdb->prefix . 'wesanox_items';
        $bookings_table = $wpdb->prefix . 'wesanox_bookings';
        $orders_table   = $wpdb->prefix . 'wc_orders';

        // Count active rooms.
        $total_rooms = (int) $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT COUNT(*)
                FROM `{$rooms_table}` r
                LEFT JOIN `{$items_table}` i ON i.id = r.id
                WHERE (r.room_inactive IS NULL OR r.room_inactive = 0)
                  AND NOT (
                        r.room_inactiv_from IS NOT NULL
                    AND r.room_inactiv_from < %s
                    AND (r.room_inactiv_to IS NULL OR r.room_inactiv_to >= %s)
                  )
                  AND NOT (
                        i.id IS NOT NULL
                    AND i.inactive = 1
                    AND (
                            (i.inactiv_from IS NULL AND i.inactiv_to IS NULL)
                        OR  (i.inactiv_from IS NOT NULL AND i.inactiv_from < %s AND (i.inactiv_to IS NULL OR i.inactiv_to >= %s))
                    )
                  )
                ",
                $request->checkout,
                $request->checkin,
                $request->checkout,
                $request->checkin
            )
        );

        if ($total_rooms === 0) {
            return new SuiteAvailabilityResult(false, 0, $request->nights());
        }

        $placeholders = implode(',', array_fill(0, count(self::EXCLUDED_STATUSES), '%s'));
        $not_cancelled = BookingOccupancy::notCancelledSql('b');

        // Find rooms that have at least one booking within the date range.
        $booked_room_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT b.room_id
                 FROM `{$bookings_table}` b
                 LEFT JOIN `{$orders_table}` o ON o.ID = b.wc_order_id
                 WHERE b.booking_date >= %s
                   AND b.booking_date < %s
                   AND b.room_id IS NOT NULL
                   AND (o.ID IS NULL OR o.status NOT IN ({$placeholders})) AND {$not_cancelled}",
                array_merge(
                    [$request->checkin, $request->checkout],
                    self::EXCLUDED_STATUSES
                )
            )
        );

        $available_count = $total_rooms - count(array_unique($booked_room_ids));

        return new SuiteAvailabilityResult(
            available:       $available_count > 0,
            available_rooms: max(0, $available_count),
            nights:          $request->nights(),
        );
    }

    private function cleanupExpiredCheckoutHolds(): void
    {
        try {
            (new RepositoryBooking())->cleanup_expired_checkout_holds();
        } catch (\Throwable $e) {
            error_log('wesanox-booking: Abgelaufene Checkout-Holds konnten nicht bereinigt werden: ' . $e->getMessage());
        }
    }
}
