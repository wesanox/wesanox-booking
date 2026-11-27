<?php

declare(strict_types=1);

namespace Wesanox\Booking\Domain\Cart;

/**
 * Splits the booked hours across the tariffs of a booking.
 *
 * A booking has one tariff for its whole duration, or two when it crosses the tariff cutoff
 * (16:00): the first for the hours before the cutoff, the second for the hours from it. The
 * segment decides the quantity, never the variation ID, so tariffs configured later (e.g. the
 * Friday afternoon tariff) are charged for their own hours only.
 *
 * No WordPress dependencies – fully unit-testable.
 */
final class TariffQuantityAllocator
{
    /**
     * @param int[] $tariff_ids           One tariff, or [before cutoff, from cutoff]
     * @param float $hours_before_cutoff  Booked hours per guest before the cutoff
     * @param float $hours_from_cutoff    Booked hours per guest from the cutoff
     * @param float $fallback_hours       Booked duration, used for a single tariff when the split is unknown
     *
     * @return array<int, array{product_id: int, quantity: float}>  Empty when nothing can be allocated
     */
    public function allocate(
        array $tariff_ids,
        int $persons,
        float $hours_before_cutoff,
        float $hours_from_cutoff,
        float $fallback_hours = 0.0,
    ): array {
        $tariff_ids = array_values($tariff_ids);
        $hours_before_cutoff = max(0.0, $hours_before_cutoff);
        $hours_from_cutoff = max(0.0, $hours_from_cutoff);
        $total_hours = $hours_before_cutoff + $hours_from_cutoff;

        if ($persons < 1 || $tariff_ids === [] || count($tariff_ids) > 2) {
            return [];
        }

        if (count($tariff_ids) === 1) {
            return $this->lines([[$tariff_ids[0], $total_hours > 0 ? $total_hours : max(0.0, $fallback_hours)]], $persons);
        }

        return $this->lines(
            [
                [$tariff_ids[0], $hours_before_cutoff],
                [$tariff_ids[1], $hours_from_cutoff],
            ],
            $persons,
        );
    }

    /**
     * @param array<int, array{int, float}> $segments  Tariff ID and hours per guest
     *
     * @return array<int, array{product_id: int, quantity: float}>
     */
    private function lines(array $segments, int $persons): array
    {
        $lines = [];

        foreach ($segments as [$tariff_id, $hours]) {
            if ($hours <= 0) {
                continue;
            }

            $lines[] = ['product_id' => (int) $tariff_id, 'quantity' => $persons * $hours];
        }

        return $lines;
    }
}
