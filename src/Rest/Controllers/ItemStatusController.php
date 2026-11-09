<?php

declare(strict_types=1);

namespace Wesanox\Booking\Rest\Controllers;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use Wesanox\Booking\Service\Api\ServiceAuth;

final class ItemStatusController
{
    private const REST_NAMESPACE = 'wesanox/v1';

    public function registerRoutes(): void
    {
        register_rest_route(self::REST_NAMESPACE, '/items/status-sync', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'permission_callback' => [ServiceAuth::class, 'permission_callback'],
                'callback'            => [$this, 'sync'],
                'args'                => [
                    'wordpress_room_id' => ['required' => true, 'type' => 'integer'],
                    'external_source'   => ['required' => false, 'type' => 'string'],
                    'external_item_id'  => ['required' => false, 'type' => 'string'],
                    'name'              => ['required' => false, 'type' => 'string'],
                    'inactive'          => ['required' => false, 'type' => 'boolean'],
                    'inactive_online'   => ['required' => false, 'type' => 'boolean'],
                    'online_blocked'    => ['required' => false, 'type' => 'boolean'],
                    'inactiv_from'      => ['required' => false, 'type' => ['string', 'null']],
                    'inactiv_to'        => ['required' => false, 'type' => ['string', 'null']],
                    'inactiv_note'      => ['required' => false, 'type' => ['string', 'null']],
                ],
            ],
        ]);
    }

    public function sync(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $roomsTable = $wpdb->prefix . 'wesanox_rooms';
        $itemsTable = $wpdb->prefix . 'wesanox_items';
        $this->ensureRoomStatusSchema($roomsTable);
        $this->ensureItemStatusSchema($itemsTable);

        $payload = $request->get_json_params() ?: [];
        $roomId = (int) ($payload['wordpress_room_id'] ?? 0);

        if ($roomId <= 0) {
            return new WP_REST_Response([
                'errors' => ['wordpress_room_id' => 'wordpress_room_id is required'],
            ], 422);
        }

        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$roomsTable} WHERE id = %d",
            $roomId
        ));

        if ($exists === 0) {
            return new WP_REST_Response(['message' => 'WordPress room not found'], 404);
        }

        $inactive = filter_var($payload['inactive'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $inactiveOnline = filter_var($payload['inactive_online'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $onlineBlocked = filter_var($payload['online_blocked'] ?? ($inactive || $inactiveOnline), FILTER_VALIDATE_BOOLEAN);
        $inactiveFrom = $this->date($payload['inactiv_from'] ?? null);
        $inactiveTo = $this->date($payload['inactiv_to'] ?? null);
        $update = $this->roomStatusUpdate($onlineBlocked, $inactiveFrom, $inactiveTo);

        if ($this->hasColumn($roomsTable, 'room_inactiv_note')) {
            $update['room_inactiv_note'] = $onlineBlocked
                ? trim((string) ($payload['inactiv_note'] ?? ''))
                : null;
        }

        $formats = array_map(
            static fn (string $column): string => $column === 'room_inactive' ? '%d' : '%s',
            array_keys($update)
        );

        $updated = $wpdb->update(
            $roomsTable,
            $update,
            ['id' => $roomId],
            $formats,
            ['%d']
        );

        if ($updated === false) {
            return $this->databaseError('Room status sync failed');
        }

        $itemUpdated = $this->syncItemStatus(
            $itemsTable,
            $roomId,
            $payload,
            $onlineBlocked,
            $inactiveFrom,
            $inactiveTo
        );

        if ($itemUpdated === false) {
            return $this->databaseError('Item status sync failed');
        }

        return new WP_REST_Response([
            'ok' => true,
            'wordpress_room_id' => $roomId,
            'external_source' => $this->normalizeSource((string) ($payload['external_source'] ?? 'backend')),
            'external_item_id' => trim((string) ($payload['external_item_id'] ?? '')),
            'online_blocked' => $onlineBlocked,
            'room_inactive' => (bool) $update['room_inactive'],
            'room_inactiv_from' => $update['room_inactiv_from'] ?? null,
            'room_inactiv_to' => $update['room_inactiv_to'] ?? null,
            'changed' => $updated > 0 || $itemUpdated > 0,
        ], 200);
    }

    /**
     * WordPress' availability SQL treats room_inactive=1 as permanently hidden
     * and date fields as period-based hiding. Keep those two modes separate.
     *
     * @return array<string, mixed>
     */
    private function roomStatusUpdate(bool $onlineBlocked, ?string $inactiveFrom, ?string $inactiveTo): array
    {
        if (! $onlineBlocked) {
            return [
                'room_inactive' => 0,
                'room_inactiv_from' => null,
                'room_inactiv_to' => null,
            ];
        }

        if ($inactiveFrom === null && $inactiveTo === null) {
            return [
                'room_inactive' => 1,
                'room_inactiv_from' => null,
                'room_inactiv_to' => null,
            ];
        }

        return [
            'room_inactive' => 0,
            'room_inactiv_from' => $inactiveFrom ?? '1900-01-01',
            'room_inactiv_to' => $inactiveTo,
        ];
    }

    private function ensureRoomStatusSchema(string $table): void
    {
        $this->addColumnIfMissing($table, 'room_inactive', 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 0');
        $this->addColumnIfMissing($table, 'room_inactiv_from', 'DATE NULL');
        $this->addColumnIfMissing($table, 'room_inactiv_to', 'DATE NULL');
        $this->addColumnIfMissing($table, 'room_inactiv_note', 'LONGTEXT NULL');
    }

    private function ensureItemStatusSchema(string $table): void
    {
        if (! $this->tableExists($table)) {
            return;
        }

        $this->addColumnIfMissing($table, 'inactive', 'TINYINT(1) UNSIGNED DEFAULT NULL');
        $this->addColumnIfMissing($table, 'inactiv_from', 'DATE DEFAULT NULL');
        $this->addColumnIfMissing($table, 'inactiv_to', 'DATE DEFAULT NULL');
        $this->addColumnIfMissing($table, 'inactiv_note', 'LONGTEXT DEFAULT NULL');
    }

    /**
     * Keep the legacy item table in sync as well. Some frontend/admin paths read
     * wesanox_items while room assignment reads wesanox_rooms.
     *
     * @param array<string, mixed> $payload
     * @return int|false Number of changed rows, 0 when no matching item exists, false on DB error.
     */
    private function syncItemStatus(
        string $itemsTable,
        int $roomId,
        array $payload,
        bool $onlineBlocked,
        ?string $inactiveFrom,
        ?string $inactiveTo
    ): int|false {
        global $wpdb;

        if (! $this->tableExists($itemsTable)) {
            return 0;
        }

        $itemExists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$itemsTable} WHERE id = %d",
            $roomId
        ));

        if ($itemExists === 0) {
            return 0;
        }

        $data = [
            'inactive' => $onlineBlocked ? 1 : 0,
            'inactiv_from' => $onlineBlocked ? $inactiveFrom : null,
            'inactiv_to' => $onlineBlocked ? $inactiveTo : null,
            'inactiv_note' => $onlineBlocked ? trim((string) ($payload['inactiv_note'] ?? '')) : null,
        ];

        if (array_key_exists('name', $payload) && trim((string) $payload['name']) !== '') {
            $data['name'] = trim((string) $payload['name']);
        }

        return $wpdb->update(
            $itemsTable,
            $data,
            ['id' => $roomId],
            $this->formatsForItemStatus($data),
            ['%d']
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, string>
     */
    private function formatsForItemStatus(array $data): array
    {
        return array_map(
            static fn (string $column): string => $column === 'inactive' ? '%d' : '%s',
            array_keys($data)
        );
    }

    private function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        global $wpdb;

        if ($this->hasColumn($table, $column)) {
            return;
        }

        $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }

    private function hasColumn(string $table, string $column): bool
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column)) !== null;
    }

    private function tableExists(string $table): bool
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    private function date(mixed $date): ?string
    {
        $date = trim((string) $date);

        if ($date === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
            $date = substr($date, 0, 10);
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return $dt instanceof \DateTimeImmutable && $dt->format('Y-m-d') === $date
            ? $date
            : null;
    }

    private function normalizeSource(string $source): string
    {
        $source = strtolower(trim($source));
        $source = preg_replace('/[^a-z0-9_\-]/', '', $source) ?: 'backend';

        return substr($source, 0, 40);
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
