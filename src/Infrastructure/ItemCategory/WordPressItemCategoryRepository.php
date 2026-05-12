<?php

declare(strict_types=1);

namespace Wesanox\Booking\Infrastructure\ItemCategory;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\ItemCategory\ItemCategory;
use Wesanox\Booking\Domain\ItemCategory\ItemCategoryRepositoryInterface;

final class WordPressItemCategoryRepository implements ItemCategoryRepositoryInterface
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'wesanox_item_categories';
    }

    /** @return ItemCategory[] */
    public function findAll(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT id, name FROM `{$this->table}` ORDER BY name ASC",
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findById(int $id): ?ItemCategory
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, name FROM `{$this->table}` WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        if (!is_array($row)) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function create(array $data): int
    {
        global $wpdb;

        $wpdb->insert(
            $this->table,
            ['name' => $data['name']],
            ['%s']
        );

        return (int) $wpdb->insert_id;
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $rows = $wpdb->update(
            $this->table,
            ['name' => $data['name']],
            ['id'   => $id],
            ['%s'],
            ['%d']
        );

        return $rows !== false;
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        $rows = $wpdb->delete($this->table, ['id' => $id], ['%d']);

        return $rows !== false && $rows > 0;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): ItemCategory
    {
        return new ItemCategory(
            id:   (int) $row['id'],
            name: (string) ($row['name'] ?? ''),
        );
    }
}
