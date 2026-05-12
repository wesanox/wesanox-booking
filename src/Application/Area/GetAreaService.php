<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Area;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Area\Area;
use Wesanox\Booking\Domain\Area\AreaRepositoryInterface;

final class GetAreaService
{
    public function __construct(
        private AreaRepositoryInterface $repository,
    ) {
    }

    public function execute(int $id): ?Area
    {
        if ($id <= 0) {
            return null;
        }

        return $this->repository->findById($id);
    }
}
