<?php

declare(strict_types=1);

namespace Wesanox\Booking\Infrastructure\Item;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Item\Item;
use Wesanox\Booking\Domain\Item\ItemRepositoryInterface;

final class WordPressItemRepository implements ItemRepositoryInterface
{
    private string $table;
    private string $areas_table;
    private string $categories_table;

    public function __construct()
    {
        global $wpdb;
        $this->table            = $wpdb->prefix . 'wesanox_items';
        $this->areas_table      = $wpdb->prefix . 'wesanox_areas';
        $this->categories_table = $wpdb->prefix . 'wesanox_item_categories';
    }

    /** @return Item[] */
    public function findAll(?int $area_id = null, ?int $category_id = null, ?bool $inactive = null): array
    {
        global $wpdb;

        $where  = ['1=1'];
        $params = [];

        if ($area_id !== null) {
            $where[]  = 'i.area_id = %d';
            $params[] = $area_id;
        }

        if ($category_id !== null) {
            $where[]  = 'i.item_category_id = %d';
            $params[] = $category_id;
        }

        if ($inactive === true) {
            $where[] = 'i.inactive = 1';
        } elseif ($inactive === false) {
            $where[] = '(i.inactive IS NULL OR i.inactive = 0)';
        }

        $where_sql = implode(' AND ', $where);

        $sql = "
            SELECT
                i.id,
                i.name,
                i.area_id,
                i.item_category_id,
                i.inactive,
                i.inactiv_from,
                i.inactiv_to,
                i.inactiv_note,
                a.area_name AS area_name,
                c.name      AS category_name
            FROM `{$this->table}` i
            LEFT JOIN `{$this->areas_table}` a ON a.id = i.area_id
            LEFT JOIN `{$this->categories_table}` c ON c.id = i.item_category_id
            WHERE {$where_sql}
            ORDER BY i.name ASC
        ";

        if (!empty($params)) {
            $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        } else {
            $rows = $wpdb->get_results($sql, ARRAY_A);
        }

        if (!is_array($rows)) {
            return [];
        }

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findById(int $id): ?Item
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT
                    i.id,
                    i.name,
                    i.area_id,
                    i.item_category_id,
                    i.inactive,
                    i.inactiv_from,
                    i.inactiv_to,
                    i.inactiv_note,
                    a.area_name AS area_name,
                    c.name      AS category_name
                FROM `{$this->table}` i
                LEFT JOIN `{$this->areas_table}` a ON a.id = i.area_id
                LEFT JOIN `{$this->categories_table}` c ON c.id = i.item_category_id
                WHERE i.id = %d
                ",
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
            [
                'name'             => $data['name'],
                'area_id'          => $data['area_id'],
                'item_category_id' => $data['item_category_id'],
                'inactive'         => $data['inactive'] ? 1 : 0,
                'inactiv_from'     => $data['inactiv_from'],
                'inactiv_to'       => $data['inactiv_to'],
                'inactiv_note'     => $data['inactiv_note'],
            ],
            ['%s', '%d', '%d', '%d', '%s', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $rows = $wpdb->update(
            $this->table,
            [
                'name'             => $data['name'],
                'area_id'          => $data['area_id'],
                'item_category_id' => $data['item_category_id'],
                'inactive'         => $data['inactive'] ? 1 : 0,
                'inactiv_from'     => $data['inactiv_from'],
                'inactiv_to'       => $data['inactiv_to'],
                'inactiv_note'     => $data['inactiv_note'],
            ],
            ['id' => $id],
            ['%s', '%d', '%d', '%d', '%s', '%s', '%s'],
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
    private function hydrate(array $row): Item
    {
        return new Item(
            id:               (int) $row['id'],
            name:             (string) ($row['name'] ?? ''),
            area_id:          isset($row['area_id']) && $row['area_id'] !== null ? (int) $row['area_id'] : null,
            item_category_id: isset($row['item_category_id']) && $row['item_category_id'] !== null ? (int) $row['item_category_id'] : null,
            inactive:         (bool) ($row['inactive'] ?? false),
            inactiv_from:     isset($row['inactiv_from']) && $row['inactiv_from'] !== null ? (string) $row['inactiv_from'] : null,
            inactiv_to:       isset($row['inactiv_to']) && $row['inactiv_to'] !== null ? (string) $row['inactiv_to'] : null,
            inactiv_note:     isset($row['inactiv_note']) && $row['inactiv_note'] !== null ? (string) $row['inactiv_note'] : null,
            area_name:        (string) ($row['area_name'] ?? ''),
            category_name:    (string) ($row['category_name'] ?? ''),
        );
    }
}
