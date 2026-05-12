<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Rate;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Rate\RateRepositoryInterface;

final class DeleteRateService
{
    public function __construct(
        private RateRepositoryInterface $repository,
    ) {
    }

    public function execute(int $id): bool
    {
        return $this->repository->delete($id);
    }
}
