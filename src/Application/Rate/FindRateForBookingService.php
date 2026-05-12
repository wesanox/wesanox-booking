<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Rate;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Holiday\HolidayRepositoryInterface;
use Wesanox\Booking\Domain\Rate\Rate;
use Wesanox\Booking\Domain\Rate\RateRepositoryInterface;

/**
 * Find the matching active Rate for a booking request.
 *
 * A Rate matches when:
 *   - area_id matches
 *   - item_category_id matches
 *   - rate.time_from <= booking_from AND booking_to <= rate.time_to
 *   - rate applies to the booking's effective weekday
 *   - is_active = true
 *
 * Holiday pricing: dates marked with opening_holiday=1 are treated as 'sunday'
 * so that weekend/premium rates (Feiertagstarif) apply on public holidays.
 */
final class FindRateForBookingService
{
    public function __construct(
        private RateRepositoryInterface      $repository,
        private ?HolidayRepositoryInterface  $holidays = null,
    ) {
    }

    /**
     * Returns the first matching Rate, or null if none covers the booking window.
     *
     * @param string $booking_date  Y-m-d — enables day-of-week filtering and holiday check.
     *                               Pass empty string to skip day filtering (backwards-compat).
     * @param string $booking_from  HH:MM  start of booking
     * @param string $booking_to    HH:MM  end of booking (00:00 = midnight)
     */
    public function execute(
        int    $area_id,
        int    $item_category_id,
        string $booking_from,
        string $booking_to,
        string $booking_date = '',
    ): ?Rate {
        $candidates = $this->repository->findActiveByAreaAndCategory($area_id, $item_category_id);

        $day_name = '';
        if ($booking_date !== '') {
            $day_name = $this->resolveEffectiveDay($booking_date, $area_id);
        }

        foreach ($candidates as $rate) {
            if ($day_name !== '' && !$rate->appliesToDay($day_name)) {
                continue;
            }
            if ($rate->coversBooking($booking_from, $booking_to)) {
                return $rate;
            }
        }

        return null;
    }

    /**
     * Resolve the effective weekday name for rate matching.
     *
     * If the date is a public holiday with opening_holiday=1, returns 'sunday'
     * so that rates configured for Sunday also apply on that holiday (Feiertagstarif).
     * Otherwise returns the actual lowercase English weekday name (e.g. 'friday').
     */
    private function resolveEffectiveDay(string $booking_date, int $area_id): string
    {
        if ($this->holidays !== null) {
            $holiday = $this->holidays->findByDate($booking_date, $area_id ?: null);
            if ($holiday !== null && $holiday->isHolidayPricing()) {
                return 'sunday';
            }
        }

        $ts = strtotime($booking_date);
        return $ts !== false ? strtolower((string) date('l', $ts)) : '';
    }
}
