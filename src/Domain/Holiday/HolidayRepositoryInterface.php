<?php

declare(strict_types=1);

namespace Wesanox\Booking\Domain\Holiday;

defined('ABSPATH') || exit;

interface HolidayRepositoryInterface
{
    /** @return Holiday[] sorted by opening_date ASC */
    public function findAll(): array;

    public function findById(int $id): ?Holiday;

    /**
     * Find the most specific holiday for a given date.
     * Area-specific entry takes priority over global (area_id IS NULL) entry.
     */
    public function findByDate(string $ymd, ?int $area_id = null): ?Holiday;

    /** @return int new record ID */
    public function create(array $data): int;

    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;
}
