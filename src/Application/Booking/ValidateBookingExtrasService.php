<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Booking;

/**
 * Validates and sanitizes the extras selection submitted by the user.
 *
 * Only product IDs on the whitelist are accepted; all others are rejected
 * or stripped depending on the method called.
 */
final class ValidateBookingExtrasService
{
    /** Allowed extras product IDs. */
    private const ALLOWED_PRODUCT_IDS = [92, 93, 94, 95, 96, 235];

    /**
     * Return true only when every item in $extras has an allowed product_id.
     *
     * @param array<array{product_id: int|string, ...}> $extras
     */
    public function isValid(array $extras): bool
    {
        foreach ($extras as $item) {
            if (!in_array((int) ($item['product_id'] ?? 0), self::ALLOWED_PRODUCT_IDS, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Strip items whose product_id is not on the whitelist.
     *
     * @param array<array{product_id: int|string, ...}> $extras
     * @return array<array{product_id: int|string, ...}>
     */
    public function sanitize(array $extras): array
    {
        return array_values(
            array_filter($extras, static fn (array $item): bool =>
                in_array((int) ($item['product_id'] ?? 0), self::ALLOWED_PRODUCT_IDS, true)
            )
        );
    }

    /** @return int[] */
    public static function allowedProductIds(): array
    {
        return self::ALLOWED_PRODUCT_IDS;
    }
}
