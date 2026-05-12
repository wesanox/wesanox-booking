<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Holiday;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Holiday\Holiday;
use Wesanox\Booking\Domain\Holiday\HolidayRepositoryInterface;

final class ListHolidaysService
{
    public function __construct(
        private HolidayRepositoryInterface $repository,
    ) {
    }

    /** @return Holiday[] */
    public function execute(): array
    {
        return $this->repository->findAll();
    }
}
