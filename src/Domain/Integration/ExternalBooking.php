<?php

declare(strict_types=1);

namespace Wesanox\Booking\Domain\Integration;

defined('ABSPATH') || exit;

/**
 * Immutable DTO representing a booking fetched from an external API.
 * No WordPress dependencies — fully unit-testable.
 */
final class ExternalBooking
{
    /**
     * @param string      $externalId    Unique identifier on the remote system
     * @param int         $areaId        Local wesanox area ID this booking belongs to
     * @param string      $externalAreaId  Area/resource ID on the remote system
     * @param string      $date          Booking date (Y-m-d)
     * @param string      $startTime     Start time (H:i)
     * @param string      $endTime       End time (H:i)
     * @param string      $status        Raw status string from the remote API
     * @param string|null $customerName  Optional customer name
     * @param string|null $notes         Optional booking notes
     * @param array<string, mixed> $rawData  Full raw payload for extension / debugging
     */
    public function __construct(
        public readonly string  $externalId,
        public readonly int     $areaId,
        public readonly string  $externalAreaId,
        public readonly string  $date,
        public readonly string  $startTime,
        public readonly string  $endTime,
        public readonly string  $status,
        public readonly ?string $customerName = null,
        public readonly ?string $notes = null,
        public readonly array   $rawData = [],
    ) {
    }

    public function isConfirmed(): bool
    {
        return in_array(strtolower($this->status), ['confirmed', 'booked', 'active'], true);
    }

    public function isCancelled(): bool
    {
        return in_array(strtolower($this->status), ['cancelled', 'canceled', 'rejected'], true);
    }
}
