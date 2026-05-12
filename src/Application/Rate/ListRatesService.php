<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Rate;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Rate\Rate;
use Wesanox\Booking\Domain\Rate\RateRepositoryInterface;

final class ListRatesService
{
    public function __construct(
        private RateRepositoryInterface $repository,
    ) {
    }

    /** @return Rate[] */
    public function execute(?int $area_id = null): array
    {
        if ($area_id !== null && $area_id > 0) {
            return $this->repository->findByArea($area_id);
        }

        return $this->repository->findAll();
    }
}
