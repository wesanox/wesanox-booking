<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Holiday;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Holiday\HolidayRepositoryInterface;

final class DeleteHolidayService
{
    public function __construct(
        private HolidayRepositoryInterface $repository,
    ) {
    }

    public function execute(int $id): bool
    {
        return $this->repository->delete($id);
    }
}
