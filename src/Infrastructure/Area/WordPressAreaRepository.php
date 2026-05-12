<?php

declare(strict_types=1);

namespace Wesanox\Booking\Infrastructure\Area;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Area\Area;
use Wesanox\Booking\Domain\Area\AreaRepositoryInterface;

final class WordPressAreaRepository implements AreaRepositoryInterface
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'wesanox_areas';
    }

    /** @return Area[] */
    public function findAll(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT id, area_name, area_opening, area_time_settings, area_booking_settings, wesanox_api_settings FROM `{$this->table}` ORDER BY area_name ASC",
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findById(int $id): ?Area
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, area_name, area_opening, area_time_settings, area_booking_settings, wesanox_api_settings FROM `{$this->table}` WHERE id = %d",
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
                'area_name'              => $data['name'],
                'area_opening'           => $data['opening_json'],
                'area_time_settings'     => $data['time_settings_json'],
                'area_booking_settings'  => $data['booking_settings_json']  ?? null,
                'wesanox_api_settings'   => $data['api_settings_json']      ?? null,
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $rows = $wpdb->update(
            $this->table,
            [
                'area_name'              => $data['name'],
                'area_opening'           => $data['opening_json'],
                'area_time_settings'     => $data['time_settings_json'],
                'area_booking_settings'  => $data['booking_settings_json']  ?? null,
                'wesanox_api_settings'   => $data['api_settings_json']      ?? null,
            ],
            ['id' => $id],
            ['%s', '%s', '%s', '%s', '%s'],
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
    private function hydrate(array $row): Area
    {
        return new Area(
            id:                    (int) $row['id'],
            name:                  (string) ($row['area_name'] ?? ''),
            opening_json:          isset($row['area_opening'])             ? (string) $row['area_opening']             : null,
            time_settings_json:    isset($row['area_time_settings'])       ? (string) $row['area_time_settings']       : null,
            booking_settings_json: isset($row['area_booking_settings'])    ? (string) $row['area_booking_settings']    : null,
            api_settings_json:     isset($row['wesanox_api_settings'])     ? (string) $row['wesanox_api_settings']     : null,
        );
    }
}
