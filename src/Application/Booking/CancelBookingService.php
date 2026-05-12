<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Booking;

use Wesanox\Booking\Domain\Booking\BookingRepositoryInterface;

final class CancelBookingService
{
    public function __construct(
        private BookingRepositoryInterface $repository,
    ) {
    }

    /**
     * Cancels the WC order linked to the given booking.
     *
     * Returns false when:
     * - booking not found
     * - booking has no linked WC order
     * - WC order is already in a non-cancellable state
     */
    public function execute(int $booking_id): bool
    {
        $booking = $this->repository->findById($booking_id);

        if ($booking === null) {
            return false;
        }

        if (!$booking->isCancellable()) {
            return false;
        }

        return $this->repository->cancelByWcOrder((int) $booking->wc_order_id);
    }
}
