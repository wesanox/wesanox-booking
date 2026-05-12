<?php

declare(strict_types=1);

namespace Wesanox\Booking\Domain\Item;

defined('ABSPATH') || exit;

interface ItemRepositoryInterface
{
    /**
     * @param int|null  $area_id      filter by area (null = all)
     * @param int|null  $category_id  filter by item category (null = all)
     * @param bool|null $inactive     true = only inactive, false = only active, null = all
     * @return Item[]
     */
    public function findAll(?int $area_id = null, ?int $category_id = null, ?bool $inactive = null): array;

    public function findById(int $id): ?Item;

    /**
     * @param array{name: string, area_id: ?int, item_category_id: ?int, inactive: bool, inactiv_from: ?string, inactiv_to: ?string, inactiv_note: ?string} $data
     */
    public function create(array $data): int;

    /**
     * @param array{name: string, area_id: ?int, item_category_id: ?int, inactive: bool, inactiv_from: ?string, inactiv_to: ?string, inactiv_note: ?string} $data
     */
    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;
}
