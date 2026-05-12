<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Shortcut;

defined('ABSPATH') || exit;

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

        $rooms_table    = $wpdb->prefix . 'wesanox_rooms';
        $bookings_table = $wpdb->prefix . 'wesanox_bookings';
        $orders_table   = $wpdb->prefix . 'wc_orders';

        // Count active rooms.
        $total_rooms = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM `{$rooms_table}` WHERE room_inactive = 0"
        );

        if ($total_rooms === 0) {
            return new SuiteAvailabilityResult(false, 0, $request->nights());
        }

        $placeholders = implode(',', array_fill(0, count(self::EXCLUDED_STATUSES), '%s'));

        // Find rooms that have at least one booking within the date range.
        $booked_room_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT b.room_id
                 FROM `{$bookings_table}` b
                 LEFT JOIN `{$orders_table}` o ON o.ID = b.wc_order_id
                 WHERE b.booking_date >= %s
                   AND b.booking_date < %s
                   AND (o.ID IS NULL OR o.status NOT IN ({$placeholders}))",
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
}
