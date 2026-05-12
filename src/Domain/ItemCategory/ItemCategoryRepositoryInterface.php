<?php

declare(strict_types=1);

namespace Wesanox\Booking\Domain\ItemCategory;

defined('ABSPATH') || exit;

interface ItemCategoryRepositoryInterface
{
    /** @return ItemCategory[] */
    public function findAll(): array;

    public function findById(int $id): ?ItemCategory;

    /** @param array{name: string} $data */
    public function create(array $data): int;

    /** @param array{name: string} $data */
    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;
}
