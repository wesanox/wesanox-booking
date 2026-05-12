<?php

declare(strict_types=1);

namespace Wesanox\Booking\Domain\Booking;

/**
 * Repository interface for Booking aggregates.
 * Domain layer — no WordPress dependencies.
 */
interface BookingRepositoryInterface
{
    /**
     * @param  string|null $status  Canonical BookingStatus constant, or null for all.
     * @return Booking[]
     */
    public function findAll(?string $status = null): array;

    public function findById(int $id): ?Booking;

    /**
     * Cancels the WC order linked to the booking.
     * Returns true on success, false if order not found or already cancelled.
     */
    public function cancelByWcOrder(int $wc_order_id): bool;
}
