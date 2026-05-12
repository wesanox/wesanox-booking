<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Area;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Area\AreaRepositoryInterface;

final class DeleteAreaService
{
    public function __construct(
        private AreaRepositoryInterface $repository,
    ) {
    }

    public function execute(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        return $this->repository->delete($id);
    }
}
