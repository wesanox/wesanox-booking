<?php

declare(strict_types=1);

namespace Wesanox\Booking\Rest\Controllers;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use Wesanox\Booking\Service\Api\ServiceAuth;

final class OpeningHoursController
{
    private const REST_NAMESPACE = 'wesanox/v1';
    private const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function registerRoutes(): void
    {
        register_rest_route(self::REST_NAMESPACE, '/opening-hours/sync', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'permission_callback' => [ServiceAuth::class, 'permission_callback'],
                'callback'            => [$this, 'sync'],
                'args'                => [
                    'wordpress_area_id' => ['required' => true, 'type' => 'integer'],
                    'external_source'   => ['required' => false, 'type' => 'string'],
                    'external_area_id'  => ['required' => false, 'type' => 'string'],
                    'replace_holidays'  => ['required' => false, 'type' => 'boolean'],
                    'opening'           => ['required' => false, 'type' => 'object'],
                    'holidays'          => ['required' => false, 'type' => 'array'],
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/opening-hours/export', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'permission_callback' => [ServiceAuth::class, 'permission_callback'],
                'callback'            => [$this, 'export'],
                'args'                => [
                    'wordpress_area_id'      => ['required' => false, 'type' => ['integer', 'null']],
                    'include_global_holidays' => ['required' => false, 'type' => 'boolean'],
                ],
            ],
        ]);
    }

    public function export(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $areaTable = $wpdb->prefix . 'wesanox_areas';
        $holidayTable = $wpdb->prefix . 'wesanox_holidays';
        $this->ensureHolidaySyncSchema($holidayTable);

        $areaId = (int) $request->get_param('wordpress_area_id');
        $includeGlobalHolidays = filter_var($request->get_param('include_global_holidays') ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($areaId <= 0 && !$includeGlobalHolidays) {
            return new WP_REST_Response([
                'errors' => [
                    'wordpress_area_id' => 'wordpress_area_id is required unless include_global_holidays is true',
                ],
            ], 422);
        }

        $area = null;

        if ($areaId > 0) {
            $area = $wpdb->get_row(
                $wpdb->prepare("SELECT id, area_name, area_opening FROM {$areaTable} WHERE id = %d LIMIT 1", $areaId),
                ARRAY_A
            );

            if (!is_array($area)) {
                return new WP_REST_Response(['message' => 'WordPress area not found'], 404);
            }
        }

        if ($areaId > 0 && $includeGlobalHolidays) {
            $holidayRows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$holidayTable}
                     WHERE area_id = %d OR area_id IS NULL
                     ORDER BY opening_date ASC, id ASC",
                    $areaId
                ),
                ARRAY_A
            );
        } elseif ($areaId > 0) {
            $holidayRows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$holidayTable}
                     WHERE area_id = %d
                     ORDER BY opening_date ASC, id ASC",
                    $areaId
                ),
                ARRAY_A
            );
        } else {
            $holidayRows = $wpdb->get_results(
                "SELECT * FROM {$holidayTable}
                 WHERE area_id IS NULL
                 ORDER BY opening_date ASC, id ASC",
                ARRAY_A
            );
        }

        return new WP_REST_Response([
            'ok' => true,
            'wordpress_area_id' => $areaId > 0 ? $areaId : null,
            'area' => is_array($area)
                ? [
                    'id' => (int) $area['id'],
                    'name' => (string) ($area['area_name'] ?? ''),
                    'opening' => $this->decodeJson((string) ($area['area_opening'] ?? '')),
                ]
                : null,
            'holidays' => array_map(
                fn (array $row): array => $this->exportHolidayRow($row),
                is_array($holidayRows) ? $holidayRows : []
            ),
            'include_global_holidays' => $includeGlobalHolidays,
        ], 200);
    }

    public function sync(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $areaTable = $wpdb->prefix . 'wesanox_areas';
        $holidayTable = $wpdb->prefix . 'wesanox_holidays';
        $this->ensureHolidaySyncSchema($holidayTable);

        $payload = $request->get_json_params() ?: [];
        $areaId = (int) ($payload['wordpress_area_id'] ?? 0);
        $source = $this->normalizeSource((string) ($payload['external_source'] ?? 'backend'));
        $externalAreaId = trim((string) ($payload['external_area_id'] ?? ''));
        $replaceHolidays = !array_key_exists('replace_holidays', $payload)
            || filter_var($payload['replace_holidays'], FILTER_VALIDATE_BOOLEAN);

        if ($areaId <= 0) {
            return new WP_REST_Response(['errors' => ['wordpress_area_id' => 'wordpress_area_id is required']], 422);
        }

        if ($source === '') {
            return new WP_REST_Response(['errors' => ['external_source' => 'external_source is invalid']], 422);
        }

        $areaExists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$areaTable} WHERE id = %d",
            $areaId
        ));

        if ($areaExists === 0) {
            return new WP_REST_Response(['message' => 'WordPress area not found'], 404);
        }

        $opening = $this->normalizeOpening((array) ($payload['opening'] ?? []));
        $holidays = $this->normalizeHolidays((array) ($payload['holidays'] ?? []));

        $wpdb->query('START TRANSACTION');

        try {
            $updatedArea = $wpdb->update(
                $areaTable,
                ['area_opening' => $this->jsonEncode($opening)],
                ['id' => $areaId],
                ['%s'],
                ['%d']
            );

            if ($updatedArea === false) {
                $wpdb->query('ROLLBACK');
                return $this->databaseError('Area opening update failed');
            }

            if ($replaceHolidays) {
                $deleted = $wpdb->delete(
                    $holidayTable,
                    [
                        'area_id' => $areaId,
                        'external_source' => $source,
                    ],
                    ['%d', '%s']
                );

                if ($deleted === false) {
                    $wpdb->query('ROLLBACK');
                    return $this->databaseError('Holiday cleanup failed');
                }
            }

            $syncedHolidays = 0;

            foreach ($holidays as $holiday) {
                $ok = $replaceHolidays
                    ? $this->insertHoliday($holidayTable, $areaId, $source, $externalAreaId, $holiday)
                    : $this->upsertHoliday($holidayTable, $areaId, $source, $externalAreaId, $holiday);

                if (!$ok) {
                    $wpdb->query('ROLLBACK');
                    return $this->databaseError('Holiday sync failed');
                }

                $syncedHolidays++;
            }

            $wpdb->query('COMMIT');

            return new WP_REST_Response([
                'ok' => true,
                'wordpress_area_id' => $areaId,
                'external_source' => $source,
                'external_area_id' => $externalAreaId,
                'opening_synced' => true,
                'holidays_synced' => $syncedHolidays,
                'replace_holidays' => $replaceHolidays,
            ], 200);
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');

            return new WP_REST_Response([
                'message' => 'Opening hours sync failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param array<int|string, mixed> $opening
     * @return array<string, array{enabled: bool, from: ?string, to: ?string}>
     */
    private function normalizeOpening(array $opening): array
    {
        $normalized = [];

        foreach (self::WEEKDAYS as $day) {
            $entry = is_array($opening[$day] ?? null) ? $opening[$day] : [];
            $enabled = filter_var($entry['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $normalized[$day] = [
                'enabled' => $enabled,
                'from' => $enabled ? $this->normalizeShortTime($entry['from'] ?? null) : null,
                'to' => $enabled ? $this->normalizeShortTime($entry['to'] ?? null) : null,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int|string, mixed> $holidays
     * @return list<array<string, mixed>>
     */
    private function normalizeHolidays(array $holidays): array
    {
        $normalized = [];

        foreach ($holidays as $holiday) {
            if (!is_array($holiday)) {
                continue;
            }

            $date = trim((string) ($holiday['opening_date'] ?? $holiday['date'] ?? ''));

            if (!$this->isDate($date)) {
                continue;
            }

            $closed = filter_var($holiday['opening_closed'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $externalId = trim((string) ($holiday['external_id'] ?? ''));

            $normalized[$date] = [
                'external_id' => $externalId !== '' ? $externalId : 'backend:' . $date,
                'opening_date' => $date,
                'opening_from' => $closed ? null : $this->normalizeSqlTime($holiday['opening_from'] ?? null),
                'opening_to' => $closed ? null : $this->normalizeSqlTime($holiday['opening_to'] ?? null),
                'opening_closed' => $closed,
                'opening_holiday' => filter_var($holiday['opening_holiday'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        ksort($normalized);

        return array_values($normalized);
    }

    /** @param array<string, mixed> $holiday */
    private function insertHoliday(string $table, int $areaId, string $source, string $externalAreaId, array $holiday): bool
    {
        global $wpdb;

        $now = $this->now();

        $result = $wpdb->insert(
            $table,
            [
                'opening_date' => $holiday['opening_date'],
                'opening_from' => $holiday['opening_from'],
                'opening_to' => $holiday['opening_to'],
                'opening_closed' => (int) $holiday['opening_closed'],
                'opening_holiday' => (int) $holiday['opening_holiday'],
                'area_id' => $areaId,
                'external_source' => $source,
                'external_id' => $holiday['external_id'],
                'external_area_id' => $externalAreaId !== '' ? $externalAreaId : null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        return $result !== false;
    }

    /** @param array<string, mixed> $holiday */
    private function upsertHoliday(string $table, int $areaId, string $source, string $externalAreaId, array $holiday): bool
    {
        global $wpdb;

        $existingId = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE area_id = %d AND external_source = %s AND external_id = %s LIMIT 1",
            $areaId,
            $source,
            $holiday['external_id']
        ));

        if ($existingId <= 0) {
            return $this->insertHoliday($table, $areaId, $source, $externalAreaId, $holiday);
        }

        $result = $wpdb->update(
            $table,
            [
                'opening_date' => $holiday['opening_date'],
                'opening_from' => $holiday['opening_from'],
                'opening_to' => $holiday['opening_to'],
                'opening_closed' => (int) $holiday['opening_closed'],
                'opening_holiday' => (int) $holiday['opening_holiday'],
                'external_area_id' => $externalAreaId !== '' ? $externalAreaId : null,
                'updated_at' => $this->now(),
            ],
            ['id' => $existingId],
            ['%s', '%s', '%s', '%d', '%d', '%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    private function ensureHolidaySyncSchema(string $table): void
    {
        $this->addColumnIfMissing($table, 'external_source', 'VARCHAR(40) NULL');
        $this->addColumnIfMissing($table, 'external_id', 'VARCHAR(191) NULL');
        $this->addColumnIfMissing($table, 'external_area_id', 'VARCHAR(191) NULL');
        $this->addColumnIfMissing($table, 'created_at', 'DATETIME NULL');
        $this->addColumnIfMissing($table, 'updated_at', 'DATETIME NULL');
    }

    private function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        global $wpdb;

        $exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));

        if ($exists !== null) {
            return;
        }

        $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }

    /** @param array<string, mixed> $row */
    private function exportHolidayRow(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);
        $externalId = trim((string) ($row['external_id'] ?? ''));
        $source = $this->normalizeSource((string) ($row['external_source'] ?? 'wordpress'));

        return [
            'id' => $id,
            'external_id' => $externalId !== '' ? $externalId : 'wordpress-holiday:' . $id,
            'source' => $source !== '' ? $source : 'wordpress',
            'name' => $id > 0 ? 'WordPress Feiertag #' . $id : 'WordPress Feiertag',
            'opening_date' => (string) ($row['opening_date'] ?? ''),
            'date' => (string) ($row['opening_date'] ?? ''),
            'opening_from' => $this->normalizeShortTime($row['opening_from'] ?? null),
            'opening_to' => $this->normalizeShortTime($row['opening_to'] ?? null),
            'opening_closed' => (bool) ($row['opening_closed'] ?? false),
            'opening_holiday' => (bool) ($row['opening_holiday'] ?? false),
            'wordpress_area_id' => isset($row['area_id']) && $row['area_id'] !== null ? (int) $row['area_id'] : null,
            'is_global' => !isset($row['area_id']) || $row['area_id'] === null,
        ];
    }

    /** @return array<string, mixed> */
    private function decodeJson(string $json): array
    {
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeSource(string $source): string
    {
        $source = strtolower(trim($source));
        $source = preg_replace('/[^a-z0-9_\-]/', '', $source) ?: '';

        return substr($source, 0, 40);
    }

    private function normalizeShortTime(mixed $time): ?string
    {
        $time = trim((string) $time);

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            return substr($time, 0, 5);
        }

        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time;
        }

        return null;
    }

    private function normalizeSqlTime(mixed $time): ?string
    {
        $time = $this->normalizeShortTime($time);

        return $time !== null ? $time . ':00' : null;
    }

    private function isDate(string $date): bool
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return $dt instanceof \DateTimeImmutable && $dt->format('Y-m-d') === $date;
    }

    private function jsonEncode(array $data): string
    {
        if (function_exists('wp_json_encode')) {
            return (string) wp_json_encode($data);
        }

        return (string) json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    private function now(): string
    {
        return function_exists('current_time')
            ? (string) current_time('mysql')
            : gmdate('Y-m-d H:i:s');
    }

    private function databaseError(string $message): WP_REST_Response
    {
        global $wpdb;

        return new WP_REST_Response([
            'message' => $message,
            'error' => $wpdb->last_error,
        ], 500);
    }
}
