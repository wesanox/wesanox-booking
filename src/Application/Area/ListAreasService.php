<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Area;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Area\Area;
use Wesanox\Booking\Domain\Area\AreaRepositoryInterface;

final class ListAreasService
{
    public function __construct(
        private AreaRepositoryInterface $repository,
    ) {
    }

    /** @return Area[] */
    public function execute(): array
    {
        return $this->repository->findAll();
    }
}
