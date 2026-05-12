<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Booking;

use Wesanox\Booking\Domain\Booking\Booking;
use Wesanox\Booking\Domain\Booking\BookingRepositoryInterface;

final class GetBookingService
{
    public function __construct(
        private BookingRepositoryInterface $repository,
    ) {
    }

    public function execute(int $id): ?Booking
    {
        if ($id <= 0) {
            return null;
        }

        return $this->repository->findById($id);
    }
}
