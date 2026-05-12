<?php

declare(strict_types=1);

namespace Wesanox\Booking\Domain\Area;

defined('ABSPATH') || exit;

interface AreaRepositoryInterface
{
    /** @return Area[] */
    public function findAll(): array;

    public function findById(int $id): ?Area;

    /**
     * Persist a new area. Returns the new row ID.
     *
     * @param array{name: string, opening_json: ?string, time_settings_json: ?string} $data
     */
    public function create(array $data): int;

    /**
     * Update an existing area. Returns true on success.
     *
     * @param array{name: string, opening_json: ?string, time_settings_json: ?string} $data
     */
    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;
}
