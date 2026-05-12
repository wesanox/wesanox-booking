<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Shortcut;

defined('ABSPATH') || exit;

use Wesanox\Booking\Service\ServiceGetAvailableTimes;

/**
 * Checks availability for a same-day, hourly timeslot booking.
 *
 * Uses the existing priority chain (Holiday → Area Opening → Rates → Defaults)
 * to determine whether the area is open and whether rooms are free.
 */
final class CheckTimeslotAvailabilityService
{
    /** @var string[] */
    private const EXCLUDED_STATUSES = ['wc-cancelled', 'wc-refunded', 'wc-failed', 'trash'];

    public function execute(TimeslotBookingRequest $request): TimeslotAvailabilityResult
    {
        global $wpdb;

        $area_id_or_null = $request->area_id > 0 ? $request->area_id : null;

        // Check opening window via existing priority chain.
        try {
            $ow = ServiceGetAvailableTimes::get_opening_window($request->date, $area_id_or_null);
        } catch (\Throwable) {
            return new TimeslotAvailabilityResult(false, false, 0, '', '');
        }

        if ($ow['closed'] ?? false) {
            return new TimeslotAvailabilityResult(false, false, 0, '', '');
        }

        $opening_from = $ow['opening_from'] ?? '';
        $opening_to   = $ow['opening_to']   ?? '';

        // Check whether the requested slot is within opening hours.
        $to_cmp = ($request->to === '00:00') ? '24:00' : $request->to;

        if ($request->from < $opening_from || $to_cmp > $this->normalizeOpeningTo($opening_to)) {
            return new TimeslotAvailabilityResult(false, true, 0, $opening_from, $opening_to);
        }

        // Count rooms available for the requested time range.
        $rooms_table    = $wpdb->prefix . 'wesanox_rooms';
        $bookings_table = $wpdb->prefix . 'wesanox_bookings';
        $orders_table   = $wpdb->prefix . 'wc_orders';

        $total_rooms = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM `{$rooms_table}` WHERE room_inactive = 0"
        );

        if ($total_rooms === 0) {
            return new TimeslotAvailabilityResult(false, true, 0, $opening_from, $opening_to);
        }

        $placeholders = implode(',', array_fill(0, count(self::EXCLUDED_STATUSES), '%s'));

        // Rooms that have an overlapping booking on that day.
        $booked_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT b.room_id
                 FROM `{$bookings_table}` b
                 LEFT JOIN `{$orders_table}` o ON o.ID = b.wc_order_id
                 WHERE b.booking_date = %s
                   AND b.booking_from < %s
                   AND b.booking_to   > %s
                   AND (o.ID IS NULL OR o.status NOT IN ({$placeholders}))",
                array_merge(
                    [$request->date, $request->to . ':00', $request->from . ':00'],
                    self::EXCLUDED_STATUSES
                )
            )
        );

        $available_count = $total_rooms - count(array_unique($booked_ids));

        return new TimeslotAvailabilityResult(
            available:       $available_count > 0,
            area_open:       true,
            available_rooms: max(0, $available_count),
            opening_from:    $opening_from,
            opening_to:      $opening_to,
        );
    }

    /** Normalize 23:59:59 → 24:00 for display comparison. */
    private function normalizeOpeningTo(string $time): string
    {
        return ($time === '23:59:59' || $time === '23:59:00') ? '24:00' : $time;
    }
}
