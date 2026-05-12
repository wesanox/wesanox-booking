<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Integration;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Integration\ExternalBooking;
use Wesanox\Booking\Domain\Integration\ExternalBookingValidator;

/**
 * Application-layer facade for validating ExternalBooking objects.
 *
 * Thin wrapper that delegates to the domain validator.
 * Kept as a separate service so it can be injected and mocked in higher layers.
 */
final class ValidateExternalBookingService
{
    public function __construct(
        private ExternalBookingValidator $validator,
    ) {
    }

    /**
     * @return string[]  Validation error messages (empty = valid)
     */
    public function validate(ExternalBooking $booking): array
    {
        return $this->validator->validate($booking);
    }

    public function isValid(ExternalBooking $booking): bool
    {
        return $this->validator->isValid($booking);
    }

    /**
     * Validate a list of bookings and return only the valid ones.
     * Invalid entries are silently dropped (callers may log them).
     *
     * @param  ExternalBooking[] $bookings
     * @return ExternalBooking[]
     */
    public function filterValid(array $bookings): array
    {
        return array_values(
            array_filter($bookings, fn(ExternalBooking $b) => $this->isValid($b))
        );
    }
}
