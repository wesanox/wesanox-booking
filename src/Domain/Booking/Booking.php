<?php

declare(strict_types=1);

namespace Wesanox\Booking\Domain\Booking;

/**
 * Booking read model (DTO).
 * Domain layer — no WordPress dependencies.
 */
final class Booking
{
    public function __construct(
        public readonly int     $id,
        public readonly string  $booking_date,
        public readonly string  $booking_from,
        public readonly string  $booking_to,
        public readonly ?int    $room_id,
        public readonly ?int    $wc_order_id,
        public readonly ?int    $wc_customer_id,
        public readonly string  $wc_order_status = BookingStatus::NO_ORDER,
        public readonly string  $room_name       = '',
        public readonly string  $customer_name   = '',
    ) {
    }

    public function status(): string
    {
        return BookingStatus::fromWcStatus($this->wc_order_status);
    }

    public function isCancellable(): bool
    {
        return $this->wc_order_id !== null
            && in_array($this->status(), [BookingStatus::PENDING, BookingStatus::PROCESSING], true);
    }
}
