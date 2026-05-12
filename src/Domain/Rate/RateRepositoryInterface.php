<?php

declare(strict_types=1);

namespace Wesanox\Booking\Domain\Rate;

defined('ABSPATH') || exit;

interface RateRepositoryInterface
{
    /** @return Rate[] */
    public function findAll(): array;

    /** @return Rate[] */
    public function findByArea(int $area_id): array;

    /** @return Rate[] only is_active = 1 */
    public function findActiveByArea(int $area_id): array;

    /**
     * All active rates for a specific area + category combination.
     *
     * @return Rate[]
     */
    public function findActiveByAreaAndCategory(int $area_id, int $category_id): array;

    public function findById(int $id): ?Rate;

    /**
     * Find active rates whose time window overlaps [time_from, time_to]
     * within the same area + item_category scope.
     * Optionally excludes a specific rate ID (used when updating an existing rate).
     *
     * @return Rate[]
     */
    public function findOverlapping(
        int     $area_id,
        int     $item_category_id,
        string  $time_from,
        string  $time_to,
        ?int    $exclude_id = null,
    ): array;

    /** @param array<string, mixed> $data */
    public function create(array $data): int;

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;
}
