<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Application\Booking;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Application\Booking\ListBookingsService;
use Wesanox\Booking\Domain\Booking\Booking;
use Wesanox\Booking\Domain\Booking\BookingRepositoryInterface;
use Wesanox\Booking\Domain\Booking\BookingStatus;

final class ListBookingsServiceTest extends TestCase
{
    public function testItDelegatesToRepositoryWithNullStatus(): void
    {
        $bookings   = [$this->makeBooking(1), $this->makeBooking(2)];
        $repository = $this->makeRepository($bookings, expectedStatus: null);

        $service = new ListBookingsService($repository);
        $result  = $service->execute(null);

        self::assertCount(2, $result);
        self::assertSame($bookings, $result);
    }

    public function testItPassesStatusFilterToRepository(): void
    {
        $bookings   = [$this->makeBooking(1)];
        $repository = $this->makeRepository($bookings, expectedStatus: BookingStatus::PENDING);

        $service = new ListBookingsService($repository);
        $result  = $service->execute(BookingStatus::PENDING);

        self::assertCount(1, $result);
    }

    public function testItReturnsEmptyArrayWhenNoBookings(): void
    {
        $repository = $this->makeRepository([], expectedStatus: null);

        $service = new ListBookingsService($repository);
        $result  = $service->execute();

        self::assertSame([], $result);
    }

    // --- Helpers ---

    private function makeBooking(int $id): Booking
    {
        return new Booking(
            id:             $id,
            booking_date:   '2025-06-15',
            booking_from:   '10:00:00',
            booking_to:     '12:00:00',
            room_id:        null,
            wc_order_id:    null,
            wc_customer_id: null,
        );
    }

    /**
     * @param Booking[] $bookings
     */
    private function makeRepository(array $bookings, ?string $expectedStatus): BookingRepositoryInterface
    {
        return new class ($bookings, $expectedStatus) implements BookingRepositoryInterface {
            public function __construct(
                private array   $bookings,
                private ?string $expectedStatus,
            ) {
            }

            public function findAll(?string $status = null): array
            {
                // Verify the service passes the correct status to the repo.
                assert($status === $this->expectedStatus);
                return $this->bookings;
            }

            public function findById(int $id): ?Booking { return null; }
            public function cancelByWcOrder(int $wc_order_id): bool { return false; }
        };
    }
}
