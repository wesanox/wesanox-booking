<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Application\Booking;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Application\Booking\CancelBookingService;
use Wesanox\Booking\Domain\Booking\Booking;
use Wesanox\Booking\Domain\Booking\BookingRepositoryInterface;
use Wesanox\Booking\Domain\Booking\BookingStatus;

final class CancelBookingServiceTest extends TestCase
{
    public function testItReturnsFalseWhenBookingNotFound(): void
    {
        $repository = $this->makeRepository(null, cancelWillReturn: false);
        $service    = new CancelBookingService($repository);

        self::assertFalse($service->execute(999));
    }

    public function testItReturnsFalseWhenBookingHasNoWcOrder(): void
    {
        $booking    = $this->makeBooking(wc_order_id: null, status: BookingStatus::PENDING);
        $repository = $this->makeRepository($booking, cancelWillReturn: false);
        $service    = new CancelBookingService($repository);

        self::assertFalse($service->execute($booking->id));
    }

    public function testItReturnsFalseWhenBookingIsAlreadyCancelled(): void
    {
        $booking    = $this->makeBooking(wc_order_id: 42, status: BookingStatus::CANCELLED);
        $repository = $this->makeRepository($booking, cancelWillReturn: false);
        $service    = new CancelBookingService($repository);

        self::assertFalse($service->execute($booking->id));
    }

    public function testItReturnsFalseWhenBookingIsCompleted(): void
    {
        $booking    = $this->makeBooking(wc_order_id: 42, status: BookingStatus::COMPLETED);
        $repository = $this->makeRepository($booking, cancelWillReturn: false);
        $service    = new CancelBookingService($repository);

        self::assertFalse($service->execute($booking->id));
    }

    public function testItReturnsTrueForCancellablePendingBooking(): void
    {
        $booking    = $this->makeBooking(wc_order_id: 42, status: BookingStatus::PENDING);
        $repository = $this->makeRepository($booking, cancelWillReturn: true);
        $service    = new CancelBookingService($repository);

        self::assertTrue($service->execute($booking->id));
    }

    public function testItReturnsTrueForCancellableProcessingBooking(): void
    {
        $booking    = $this->makeBooking(wc_order_id: 42, status: BookingStatus::PROCESSING);
        $repository = $this->makeRepository($booking, cancelWillReturn: true);
        $service    = new CancelBookingService($repository);

        self::assertTrue($service->execute($booking->id));
    }

    public function testItReturnsFalseWhenRepositoryCancelFails(): void
    {
        $booking    = $this->makeBooking(wc_order_id: 42, status: BookingStatus::PENDING);
        $repository = $this->makeRepository($booking, cancelWillReturn: false);
        $service    = new CancelBookingService($repository);

        self::assertFalse($service->execute($booking->id));
    }

    // --- Helpers ---

    private function makeBooking(?int $wc_order_id, string $status): Booking
    {
        return new Booking(
            id:              1,
            booking_date:    '2025-06-15',
            booking_from:    '10:00:00',
            booking_to:      '12:00:00',
            room_id:         null,
            wc_order_id:     $wc_order_id,
            wc_customer_id:  null,
            wc_order_status: $status,
        );
    }

    private function makeRepository(?Booking $booking, bool $cancelWillReturn): BookingRepositoryInterface
    {
        return new class ($booking, $cancelWillReturn) implements BookingRepositoryInterface {
            public function __construct(
                private ?Booking $booking,
                private bool     $cancelReturn,
            ) {
            }

            public function findAll(?string $status = null): array { return []; }

            public function findById(int $id): ?Booking { return $this->booking; }

            public function cancelByWcOrder(int $wc_order_id): bool { return $this->cancelReturn; }
        };
    }
}
