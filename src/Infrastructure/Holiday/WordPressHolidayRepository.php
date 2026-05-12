<?php

declare(strict_types=1);

namespace Wesanox\Booking\Infrastructure\Holiday;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Holiday\Holiday;
use Wesanox\Booking\Domain\Holiday\HolidayRepositoryInterface;

final class WordPressHolidayRepository implements HolidayRepositoryInterface
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'wesanox_holidays';
    }

    /** @return Holiday[] */
    public function findAll(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT * FROM `{$this->table()}` ORDER BY opening_date ASC",
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findById(int $id): ?Holiday
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$this->table()}` WHERE id = %d LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByDate(string $ymd, ?int $area_id = null): ?Holiday
    {
        global $wpdb;

        // Priority: area-specific entry first, then global (area_id IS NULL).
        if ($area_id !== null) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM `{$this->table()}`
                     WHERE opening_date = %s AND area_id = %d
                     LIMIT 1",
                    $ymd,
                    $area_id
                ),
                ARRAY_A
            );

            if ($row) {
                return $this->hydrate($row);
            }
        }

        // Global fallback (area_id IS NULL).
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$this->table()}`
                 WHERE opening_date = %s AND area_id IS NULL
                 LIMIT 1",
                $ymd
            ),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function create(array $data): int
    {
        global $wpdb;

        $wpdb->insert(
            $this->table(),
            $this->prepareRow($data),
            $this->formats($data)
        );

        return (int) $wpdb->insert_id;
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->table(),
            $this->prepareRow($data),
            ['id' => $id],
            $this->formats($data),
            ['%d']
        );

        return $result !== false;
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        $result = $wpdb->delete(
            $this->table(),
            ['id' => $id],
            ['%d']
        );

        return $result !== false && $result > 0;
    }

    // -------------------------------------------------------------------------

    private function hydrate(array $row): Holiday
    {
        return new Holiday(
            id:              (int)  $row['id'],
            opening_date:    $row['opening_date']    ?: null,
            opening_from:    $row['opening_from']    ?: null,
            opening_to:      $row['opening_to']      ?: null,
            opening_closed:  (bool) $row['opening_closed'],
            opening_holiday: (bool) $row['opening_holiday'],
            area_id:         isset($row['area_id']) && $row['area_id'] !== null ? (int) $row['area_id'] : null,
        );
    }

    private function prepareRow(array $data): array
    {
        return [
            'opening_date'    => $data['opening_date']    ?? null,
            'opening_from'    => $data['opening_from']    ?? null,
            'opening_to'      => $data['opening_to']      ?? null,
            'opening_closed'  => (int) ($data['opening_closed']  ?? 0),
            'opening_holiday' => (int) ($data['opening_holiday'] ?? 0),
            'area_id'         => isset($data['area_id']) ? ((int) $data['area_id'] ?: null) : null,
        ];
    }

    private function formats(array $data): array
    {
        return ['%s', '%s', '%s', '%d', '%d', '%d'];
    }
}
