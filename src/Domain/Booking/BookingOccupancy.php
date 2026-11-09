<?php

declare(strict_types=1);

namespace Wesanox\Booking\Domain\Booking;

/**
 * Decides which rows of wesanox_bookings still occupy a room.
 * Domain layer — no WordPress dependencies.
 *
 * A booking cancelled in the backend (booking_status 3) must free the room,
 * even if its WooCommerce order is still open or the booking has no order at all.
 */
final class BookingOccupancy
{
    /** booking_status of a cancelled booking (backend status "Storniert"). */
    public const CANCELLED_BOOKING_STATUS = 3;

    /**
     * SQL condition that keeps only bookings which are not cancelled.
     * NULL is kept on purpose: checkout holds and older rows have no booking_status and must still block.
     */
    public static function notCancelledSql(string $bookingAlias = 'b'): string
    {
        $alias = preg_replace('/[^A-Za-z0-9_]/', '', $bookingAlias) ?: 'b';

        return sprintf(
            '(%1$s.booking_status IS NULL OR %1$s.booking_status <> %2$d)',
            $alias,
            self::CANCELLED_BOOKING_STATUS
        );
    }

    public static function blocksRoom(?int $bookingStatus): bool
    {
        return $bookingStatus !== self::CANCELLED_BOOKING_STATUS;
    }
}
