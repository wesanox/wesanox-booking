<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Booking;

use Wesanox\Booking\Domain\Booking\Booking;
use Wesanox\Booking\Domain\Booking\BookingRepositoryInterface;

final class ListBookingsService
{
    public function __construct(
        private BookingRepositoryInterface $repository,
    ) {
    }

    /**
     * @param  string|null $status  Canonical BookingStatus constant or null for all.
     * @return Booking[]
     */
    public function execute(?string $status = null): array
    {
        return $this->repository->findAll($status);
    }
}
