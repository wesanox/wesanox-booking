<?php

declare(strict_types=1);

namespace Wesanox\Booking\Domain\Booking;

/**
 * Decides the status dot of a day in the frontend booking calendar.
 * Domain layer — no WordPress dependencies.
 */
final class CalendarDayStatus
{
    public const CLOSED       = 'inactive closed';
    public const FULLY_BOOKED = 'fully-booked';
    public const BUSY         = 'middle';
    public const AVAILABLE    = 'available';

    /** From this many active bookings on, a bookable day is shown as "Hohes Aufkommen". */
    public const BUSY_THRESHOLD = 11;

    /**
     * @param mixed $availableTimes result of ServiceGetAvailableTimes::wesanox_get_available_times()
     * @param int   $activeBookings bookings of the day without cancelled, refunded or failed orders
     */
    public static function resolve(mixed $availableTimes, int $activeBookings): string
    {
        if (is_array($availableTimes) && (int) ($availableTimes['closed'] ?? 0) === 1) {
            return self::CLOSED;
        }

        if (is_array($availableTimes) && $availableTimes === []) {
            return self::FULLY_BOOKED;
        }

        return $activeBookings >= self::BUSY_THRESHOLD ? self::BUSY : self::AVAILABLE;
    }
}
