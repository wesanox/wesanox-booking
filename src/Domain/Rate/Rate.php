<?php

declare(strict_types=1);

namespace Wesanox\Booking\Domain\Rate;

defined('ABSPATH') || exit;

/**
 * Rate domain entity (immutable).
 *
 * A Rate maps a time window (time_from → time_to) on specific days of the week
 * to a WooCommerce product (and optionally a variation) for a specific area + item category.
 *
 * Overlap checks are scoped to area_id + item_category_id + shared days.
 *
 * No WordPress dependencies – fully unit-testable.
 */
final class Rate
{
    public const WEEKDAYS = [
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
    ];

    /**
     * @param string[] $days  Weekday names this rate applies to (subset of WEEKDAYS)
     */
    public function __construct(
        public readonly int     $id,
        public readonly int     $area_id,
        public readonly int     $item_category_id,
        public readonly string  $name,
        /** HH:MM */
        public readonly string  $time_from,
        /** HH:MM — 00:00 represents midnight / end-of-day */
        public readonly string  $time_to,
        public readonly array   $days,
        public readonly int     $wc_product_id,
        public readonly ?int    $wc_variation_id,
        public readonly bool    $is_active,
        public readonly int     $sort_order,
        /** Denormalised for display only */
        public readonly ?string $area_name = null,
    ) {
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Convert HH:MM to minutes since midnight.
     * "00:00" is treated as 1440 (= 24:00) when used as an upper bound.
     */
    public static function toMinutes(string $time, bool $midnight_as_1440 = false): int
    {
        [$h, $m] = array_map('intval', explode(':', $time, 2));
        $minutes = $h * 60 + $m;

        if ($midnight_as_1440 && $minutes === 0) {
            return 1440;
        }

        return $minutes;
    }

    /**
     * Whether this rate covers the given booking window.
     *
     * booking_start >= rate.time_from  AND  booking_end <= rate.time_to
     */
    public function coversBooking(string $booking_from, string $booking_to): bool
    {
        $rate_from = self::toMinutes($this->time_from);
        $rate_to   = self::toMinutes($this->time_to, true);
        $book_from = self::toMinutes($booking_from);
        $book_to   = self::toMinutes($booking_to, true);

        return $book_from >= $rate_from && $book_to <= $rate_to;
    }

    /**
     * Whether this rate's time window overlaps with another range.
     * Used for conflict detection (ignores day scope — caller checks days separately).
     */
    public function overlaps(string $other_from, string $other_to): bool
    {
        $a_from = self::toMinutes($this->time_from);
        $a_to   = self::toMinutes($this->time_to, true);
        $b_from = self::toMinutes($other_from);
        $b_to   = self::toMinutes($other_to, true);

        // Overlap when they share any minute: a_from < b_to AND b_from < a_to
        return $a_from < $b_to && $b_from < $a_to;
    }

    /**
     * Whether this rate shares at least one weekday with the given days array.
     *
     * @param string[] $other_days
     */
    public function sharesDay(array $other_days): bool
    {
        return !empty(array_intersect($this->days, $other_days));
    }

    /** Whether this rate applies on the given weekday name (e.g. 'monday'). */
    public function appliesToDay(string $day): bool
    {
        return in_array($day, $this->days, true);
    }
}
