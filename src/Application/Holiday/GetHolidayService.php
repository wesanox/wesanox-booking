<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Holiday;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Holiday\Holiday;
use Wesanox\Booking\Domain\Holiday\HolidayRepositoryInterface;

final class GetHolidayService
{
    public function __construct(
        private HolidayRepositoryInterface $repository,
    ) {
    }

    public function execute(int $id): ?Holiday
    {
        return $this->repository->findById($id);
    }
}
