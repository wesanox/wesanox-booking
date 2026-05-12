<?php

declare(strict_types=1);

namespace Wesanox\Booking\Infrastructure\Rate;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Rate\Rate;
use Wesanox\Booking\Domain\Rate\RateRepositoryInterface;

final class WordPressRateRepository implements RateRepositoryInterface
{
    private string $table;
    private string $areas_table;

    public function __construct()
    {
        global $wpdb;
        $this->table       = $wpdb->prefix . 'wesanox_rates';
        $this->areas_table = $wpdb->prefix . 'wesanox_areas';
    }

    /** @return Rate[] */
    public function findAll(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT r.*, a.area_name
             FROM `{$this->table}` r
             LEFT JOIN `{$this->areas_table}` a ON a.id = r.area_id
             WHERE r.deleted_at IS NULL
             ORDER BY r.area_id ASC, r.item_category_id ASC, r.sort_order ASC, r.time_from ASC",
            ARRAY_A
        );

        return is_array($rows) ? array_map([$this, 'hydrate'], $rows) : [];
    }

    /** @return Rate[] */
    public function findByArea(int $area_id): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, a.area_name
                 FROM `{$this->table}` r
                 LEFT JOIN `{$this->areas_table}` a ON a.id = r.area_id
                 WHERE r.area_id = %d AND r.deleted_at IS NULL
                 ORDER BY r.item_category_id ASC, r.sort_order ASC, r.time_from ASC",
                $area_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? array_map([$this, 'hydrate'], $rows) : [];
    }

    /** @return Rate[] */
    public function findActiveByArea(int $area_id): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, a.area_name
                 FROM `{$this->table}` r
                 LEFT JOIN `{$this->areas_table}` a ON a.id = r.area_id
                 WHERE r.area_id = %d AND r.is_active = 1 AND r.deleted_at IS NULL
                 ORDER BY r.item_category_id ASC, r.sort_order ASC, r.time_from ASC",
                $area_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? array_map([$this, 'hydrate'], $rows) : [];
    }

    /** @return Rate[] */
    public function findActiveByAreaAndCategory(int $area_id, int $category_id): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, a.area_name
                 FROM `{$this->table}` r
                 LEFT JOIN `{$this->areas_table}` a ON a.id = r.area_id
                 WHERE r.area_id = %d
                   AND r.item_category_id = %d
                   AND r.is_active = 1
                   AND r.deleted_at IS NULL
                 ORDER BY r.sort_order ASC, r.time_from ASC",
                $area_id,
                $category_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? array_map([$this, 'hydrate'], $rows) : [];
    }

    public function findById(int $id): ?Rate
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT r.*, a.area_name
                 FROM `{$this->table}` r
                 LEFT JOIN `{$this->areas_table}` a ON a.id = r.area_id
                 WHERE r.id = %d AND r.deleted_at IS NULL",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /** @return Rate[] */
    public function findOverlapping(
        int    $area_id,
        int    $item_category_id,
        string $time_from,
        string $time_to,
        ?int   $exclude_id = null,
    ): array {
        global $wpdb;

        // Special case: time_to = '00:00' means '24:00:00' (midnight end-of-day).
        $to_sql   = ($time_to   === '00:00') ? '24:00:00' : $time_to . ':00';
        $from_sql = $time_from . ':00';

        $exclude_clause = '';
        if ($exclude_id !== null) {
            $exclude_clause = $wpdb->prepare(' AND r.id != %d', $exclude_id);
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*
                 FROM `{$this->table}` r
                 WHERE r.area_id = %d
                   AND r.item_category_id = %d
                   AND r.is_active = 1
                   AND r.deleted_at IS NULL
                   AND TIME_TO_SEC(IF(r.time_to = '00:00:00', '24:00:00', r.time_to)) > TIME_TO_SEC(%s)
                   AND TIME_TO_SEC(r.time_from) < TIME_TO_SEC(%s)" .
                $exclude_clause,
                $area_id,
                $item_category_id,
                $from_sql,
                $to_sql
            ),
            ARRAY_A
        );

        return is_array($rows) ? array_map([$this, 'hydrate'], $rows) : [];
    }

    public function create(array $data): int
    {
        global $wpdb;

        $wpdb->insert(
            $this->table,
            [
                'area_id'                  => (int) $data['area_id'],
                'item_category_id'         => (int) $data['item_category_id'],
                'name'                     => (string) $data['name'],
                'time_from'                => (string) $data['time_from'],
                'time_to'                  => (string) $data['time_to'],
                'days'                     => json_encode($data['days'] ?? []),
                'woocommerce_product_id'   => (int) $data['woocommerce_product_id'],
                'woocommerce_variation_id' => $data['woocommerce_variation_id'] ? (int) $data['woocommerce_variation_id'] : null,
                'is_active'                => (int) $data['is_active'],
                'sort_order'               => (int) ($data['sort_order'] ?? 0),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d']
        );

        return (int) $wpdb->insert_id;
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $rows = $wpdb->update(
            $this->table,
            [
                'area_id'                  => (int) $data['area_id'],
                'item_category_id'         => (int) $data['item_category_id'],
                'name'                     => (string) $data['name'],
                'time_from'                => (string) $data['time_from'],
                'time_to'                  => (string) $data['time_to'],
                'days'                     => json_encode($data['days'] ?? []),
                'woocommerce_product_id'   => (int) $data['woocommerce_product_id'],
                'woocommerce_variation_id' => $data['woocommerce_variation_id'] ? (int) $data['woocommerce_variation_id'] : null,
                'is_active'                => (int) $data['is_active'],
                'sort_order'               => (int) ($data['sort_order'] ?? 0),
                'updated_at'               => current_time('mysql'),
            ],
            ['id' => $id],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s'],
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
    private function hydrate(array $row): Rate
    {
        $time_from = substr((string) ($row['time_from'] ?? '00:00'), 0, 5);
        $time_to   = substr((string) ($row['time_to']   ?? '00:00'), 0, 5);

        $days_raw = $row['days'] ?? '';
        $days     = is_string($days_raw) && $days_raw !== ''
            ? (array) (json_decode($days_raw, true) ?? [])
            : Rate::WEEKDAYS;

        return new Rate(
            id:               (int) $row['id'],
            area_id:          (int) $row['area_id'],
            item_category_id: (int) ($row['item_category_id'] ?? 0),
            name:             (string) ($row['name'] ?? ''),
            time_from:        $time_from,
            time_to:          $time_to,
            days:             $days,
            wc_product_id:    (int) ($row['woocommerce_product_id'] ?? 0),
            wc_variation_id:  isset($row['woocommerce_variation_id']) && $row['woocommerce_variation_id'] !== null
                                ? (int) $row['woocommerce_variation_id']
                                : null,
            is_active:        (bool) ($row['is_active'] ?? true),
            sort_order:       (int) ($row['sort_order'] ?? 0),
            area_name:        isset($row['area_name']) ? (string) $row['area_name'] : null,
        );
    }
}
