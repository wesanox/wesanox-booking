<?php

declare(strict_types=1);

namespace Wesanox\Booking\Domain\Cart;

final class BookingCartPolicy
{
    public const MISSING_RATE = 'missing_rate';
    public const MISSING_BOOKING_DATA = 'missing_booking_data';
    public const MISSING_ROOMART = 'missing_roomart';

    public function violation(
        bool $hasRate,
        bool $hasExtras,
        bool $hasCompleteBookingData,
        bool $hasRoomart,
    ): ?string {
        if (!$hasRate && !$hasExtras) {
            return null;
        }

        if (!$hasRate) {
            return self::MISSING_RATE;
        }

        if (!$hasCompleteBookingData) {
            return self::MISSING_BOOKING_DATA;
        }

        if (!$hasRoomart) {
            return self::MISSING_ROOMART;
        }

        return null;
    }

    public function canAddExtra(bool $hasRate, bool $hasCompleteBookingData): bool
    {
        return $hasRate && $hasCompleteBookingData;
    }
}
