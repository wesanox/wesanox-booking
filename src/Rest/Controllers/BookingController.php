<?php

declare(strict_types=1);

namespace Wesanox\Booking\Rest\Controllers;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Booking\BookingOccupancy;
use DateTime;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use Wesanox\Booking\Application\Integration\SyncFrontendRoomAssignmentService;
use Wesanox\Booking\Infrastructure\WesanoxApi\WesanoxApiBridge;
use Wesanox\Booking\Repository\RepositoryBooking;
use Wesanox\Booking\Service\Api\ServiceAuth;

final class BookingController
{
    private const REST_NAMESPACE = 'wesanox/v1';
    private const EXCLUDED_STATUSES = ['wc-cancelled', 'wc-refunded', 'wc-failed', 'trash'];
    private const ROOM_ID_MASTER_SOURCES = ['wordpress', 'frontend', 'backend'];
    private const BUFFER_MINUTES = 30;

    public function registerRoutes(): void
    {
        register_rest_route(self::REST_NAMESPACE, '/bookings/create', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'permission_callback' => [ServiceAuth::class, 'permission_callback'],
                'callback'            => [$this, 'create'],
                'args'                => $this->bookingArgs(required: true),
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/bookings/sync', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'permission_callback' => [ServiceAuth::class, 'permission_callback'],
                'callback'            => [$this, 'sync'],
                'args'                => array_merge(
                    $this->bookingArgs(required: true),
                    [
                        'source'          => ['required' => false, 'type' => 'string'],
                        'external_source' => ['required' => false, 'type' => 'string'],
                        'external_id'     => ['required' => true,  'type' => 'string'],
                    ]
                ),
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/bookings/update/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::EDITABLE,
            'permission_callback' => [ServiceAuth::class, 'permission_callback'],
            'callback'            => [$this, 'update'],
            'args'                => array_merge(
                ['id' => ['required' => true, 'type' => 'integer']],
                $this->bookingArgs(required: false)
            ),
        ]);

        register_rest_route(self::REST_NAMESPACE, '/bookings/delete/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'permission_callback' => [ServiceAuth::class, 'permission_callback'],
            'callback'            => [$this, 'delete'],
            'args'                => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/bookings/delete-by-external', [
            'methods'             => WP_REST_Server::DELETABLE,
            'permission_callback' => [ServiceAuth::class, 'permission_callback'],
            'callback'            => [$this, 'deleteByExternal'],
            'args'                => [
                'source'          => ['required' => false, 'type' => ['string', 'null']],
                'external_source' => ['required' => false, 'type' => ['string', 'null']],
                'external_id'     => ['required' => true,  'type' => 'string'],
            ],
        ]);
    }

    public function create(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $table = $this->bookingsTable();
        $this->ensureSyncSchema($table);
        $this->cleanupExpiredCheckoutHolds();

        $p = $request->get_json_params() ?: [];

        $booking_date   = isset($p['booking_date']) ? trim((string) $p['booking_date']) : '';
        $booking_from   = self::normalizeTime(isset($p['booking_from']) ? (string) $p['booking_from'] : null);
        $booking_to     = self::normalizeTime(isset($p['booking_to']) ? (string) $p['booking_to'] : null);
        $booking_status = $this->nullableNonNegativeInt($p, ['booking_status', 'status']);
        $wc_order_id    = $this->nullableInt($p, ['wc_order_id']);
        $wc_customer_id = $this->nullableExistingWooCustomerId($p);

        $errors = $this->validateCreateParams($booking_date, $booking_from, $booking_to);

        if (!empty($errors)) {
            return new WP_REST_Response(['errors' => $errors], 422);
        }

        $wpdb->query('START TRANSACTION');

        try {
            $existing = $wc_order_id !== null
                ? $this->findByWooOrderId($table, $wc_order_id, true)
                : null;
            $existing_id = $existing ? (int) $existing['id'] : null;

            $room = $this->resolveRoomForWrite(
                $p,
                $booking_date,
                $booking_from,
                $booking_to,
                $existing_id
            );

            if ($room instanceof WP_REST_Response) {
                $wpdb->query('ROLLBACK');
                return $room;
            }

            $now  = $this->now();
            $data = [
                'booking_date'   => $booking_date,
                'booking_from'   => $booking_from,
                'booking_to'     => $booking_to,
                'room_id'        => $room['room_id'],
                'booking_status' => $booking_status,
                'wc_order_id'    => $wc_order_id,
                'wc_customer_id' => $wc_customer_id,
                'updated_at'     => $now,
            ];

            $this->appendOptionalSyncFields($p, $data, $room);

            if ($existing_id !== null) {
                $ok = $wpdb->update($table, $data, ['id' => $existing_id], $this->formatsFor($data), ['%d']);
                $id = $existing_id;
            } else {
                $data['created_at'] = $now;
                $ok = $wpdb->insert($table, $data, $this->formatsFor($data));
                $id = (int) $wpdb->insert_id;
            }

            if ($ok === false || (!$ok && $id <= 0)) {
                $wpdb->query('ROLLBACK');
                return $this->databaseError('Database write failed');
            }

            $wpdb->query('COMMIT');

            $row = $this->findById($table, $id);
            $row['created'] = $existing_id === null;
            $this->syncWooOrderBookingMeta($row);
            $this->syncRoomAssignmentToBackend($row);

            return new WP_REST_Response($row, $existing_id === null ? 201 : 200);
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            return new WP_REST_Response([
                'message' => 'Database write failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function sync(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $table = $this->bookingsTable();
        $this->ensureSyncSchema($table);
        $this->cleanupExpiredCheckoutHolds();

        $p = $request->get_json_params() ?: [];

        $external_source = $this->normalizeSource((string) ($p['external_source'] ?? $p['source'] ?? 'backend'));
        $external_id     = isset($p['external_id']) ? trim((string) $p['external_id']) : '';
        $booking_date    = isset($p['booking_date']) ? trim((string) $p['booking_date']) : '';
        $booking_from    = self::normalizeTime(isset($p['booking_from']) ? (string) $p['booking_from'] : null);
        $booking_to      = self::normalizeTime(isset($p['booking_to']) ? (string) $p['booking_to'] : null);

        $errors = $this->validateCreateParams($booking_date, $booking_from, $booking_to);

        if ($external_id === '') {
            $errors['external_id'] = 'external_id is required for idempotent sync';
        }

        if ($external_source === '') {
            $errors['external_source'] = 'external_source is required for idempotent sync';
        }

        if (!empty($errors)) {
            return new WP_REST_Response(['errors' => $errors], 422);
        }

        $wpdb->query('START TRANSACTION');

        try {
            $existing = $this->findByExternalId($table, $external_source, $external_id, true);

            if (!$existing) {
                $wc_order_id = $this->nullableInt($p, ['wc_order_id']);
                $existing = $wc_order_id !== null
                    ? $this->findByWooOrderId($table, $wc_order_id, true)
                    : null;
            }

            $existing_id = $existing ? (int) $existing['id'] : null;

            $room = $this->resolveRoomForSync($p, $external_source, $booking_date, $booking_from, $booking_to, $existing);

            if ($room instanceof WP_REST_Response) {
                $wpdb->query('ROLLBACK');
                return $room;
            }

            $now = $this->now();
            $data = [
                'booking_date'     => $booking_date,
                'booking_from'     => $booking_from,
                'booking_to'       => $booking_to,
                'room_id'          => $room['room_id'],
                'external_source'  => $external_source,
                'external_id'      => $external_id,
                'room_id_source'   => $room['room_id_source'],
                'sync_status'      => $room['room_id'] ? 'assigned_room' : 'pending_room',
                'last_synced_at'   => $now,
                'updated_at'       => $now,
            ];

            if (array_key_exists('booking_status', $p) || array_key_exists('status', $p)) {
                $data['booking_status'] = $this->nullableNonNegativeInt($p, ['booking_status', 'status']);
            }

            if (array_key_exists('wc_order_id', $p)) {
                $data['wc_order_id'] = $this->nullableInt($p, ['wc_order_id']);
            }

            if (array_key_exists('wc_customer_id', $p)) {
                $data['wc_customer_id'] = $this->nullableExistingWooCustomerId($p);
            }

            if ($existing_id !== null) {
                $ok = $wpdb->update($table, $data, ['id' => $existing_id], $this->formatsFor($data), ['%d']);
                $id = $existing_id;
            } else {
                $data['created_at'] = $now;
                $ok = $wpdb->insert($table, $data, $this->formatsFor($data));
                $id = (int) $wpdb->insert_id;
            }

            if ($ok === false || (!$ok && $id <= 0)) {
                $wpdb->query('ROLLBACK');
                return $this->databaseError('Database sync failed');
            }

            $wpdb->query('COMMIT');

            $row = $this->findById($table, $id);
            $row['created'] = $existing_id === null;
            $row['room_assigned_by_wordpress'] = $room['assigned_by_wordpress'];

            $this->syncWooOrderBookingMeta($row);
            $this->syncRoomAssignmentToBackend($row);

            return new WP_REST_Response($row, $existing_id === null ? 201 : 200);
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            return new WP_REST_Response([
                'message' => 'Database sync failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $table = $this->bookingsTable();
        $this->ensureSyncSchema($table);
        $this->cleanupExpiredCheckoutHolds();

        $id = (int) $request->get_param('id');

        if ($id <= 0) {
            return new WP_REST_Response(['message' => 'Invalid id'], 400);
        }

        $p      = $request->get_json_params() ?: [];
        $data   = [];
        $format = [];

        $time_error = $this->buildUpdateData($p, $data, $format);

        if ($time_error instanceof WP_REST_Response) {
            return $time_error;
        }

        if (empty($data)) {
            return new WP_REST_Response(['message' => 'No fields to update'], 400);
        }

        $wpdb->query('START TRANSACTION');

        try {
            $current = $this->findById($table, $id, true);

            if (!$current) {
                $wpdb->query('ROLLBACK');
                return new WP_REST_Response(['message' => 'Booking not found'], 404);
            }

            $final_date = (string) ($data['booking_date'] ?? $current['booking_date']);
            $final_from = (string) ($data['booking_from'] ?? $current['booking_from']);
            $final_to   = (string) ($data['booking_to'] ?? $current['booking_to']);

            $range_error = $this->validateFinalTimeRange($final_from, $final_to);

            if ($range_error instanceof WP_REST_Response) {
                $wpdb->query('ROLLBACK');
                return $range_error;
            }

            $room_changed = array_key_exists('room_id', $data);
            $time_changed = array_key_exists('booking_date', $data)
                || array_key_exists('booking_from', $data)
                || array_key_exists('booking_to', $data);

            if ($room_changed || ($time_changed && !empty($current['room_id']))) {
                $room_id = array_key_exists('room_id', $data)
                    ? $data['room_id']
                    : ($current['room_id'] === null ? null : (int) $current['room_id']);

                if ($room_id !== null) {
                    $conflict = $this->assertRoomAvailable((int) $room_id, $final_date, $final_from, $final_to, $id);

                    if ($conflict instanceof WP_REST_Response) {
                        $wpdb->query('ROLLBACK');
                        return $conflict;
                    }
                }
            }

            $data['updated_at'] = $this->now();
            $format[] = '%s';

            $ok = $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);

            if ($ok === false) {
                $wpdb->query('ROLLBACK');
                return $this->databaseError('Database update failed');
            }

            $wpdb->query('COMMIT');

            $row = $this->findById($table, $id);
            $this->syncWooOrderBookingMeta($row);
            $this->syncRoomAssignmentToBackend($row);

            return new WP_REST_Response($row, 200);
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            return new WP_REST_Response([
                'message' => 'Database update failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function delete(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $table = $this->bookingsTable();
        $id    = (int) $request->get_param('id');

        if ($id <= 0) {
            return new WP_REST_Response(['message' => 'Invalid id'], 400);
        }

        $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE id = %d", $id));
        if ($exists === 0) {
            return new WP_REST_Response(['message' => 'Booking not found'], 404);
        }

        $deleted = $wpdb->delete($table, ['id' => $id], ['%d']);

        if ($deleted === false) {
            return $this->databaseError('Database delete failed');
        }

        return new WP_REST_Response(null, 204);
    }

    public function deleteByExternal(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $table = $this->bookingsTable();
        $this->ensureSyncSchema($table);

        $p = $request->get_json_params() ?: [];
        $external_source = $this->normalizeSource((string) ($p['external_source'] ?? $p['source'] ?? 'backend'));
        $external_id = isset($p['external_id']) ? trim((string) $p['external_id']) : '';

        if ($external_source === '') {
            return new WP_REST_Response(['errors' => ['external_source' => 'external_source is required']], 422);
        }

        if ($external_id === '') {
            return new WP_REST_Response(['errors' => ['external_id' => 'external_id is required']], 422);
        }

        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE external_source = %s AND external_id = %s",
            $external_source,
            $external_id
        ));

        if ($exists === 0) {
            return new WP_REST_Response(['message' => 'Booking not found'], 404);
        }

        $deleted = $wpdb->delete(
            $table,
            [
                'external_source' => $external_source,
                'external_id'     => $external_id,
            ],
            ['%s', '%s']
        );

        if ($deleted === false) {
            return $this->databaseError('Database delete failed');
        }

        return new WP_REST_Response(null, 204);
    }

    /**
     * @param array<string, mixed>|null $booking
     */
    private function syncWooOrderBookingMeta(?array $booking): void
    {
        if (!$booking) {
            return;
        }

        $wc_order_id = isset($booking['wc_order_id']) ? (int) $booking['wc_order_id'] : 0;
        $room_id = isset($booking['room_id']) ? (int) $booking['room_id'] : 0;

        if ($wc_order_id <= 0 || $room_id <= 0 || !function_exists('wc_get_order')) {
            return;
        }

        try {
            $order = wc_get_order($wc_order_id);

            if (
                !is_object($order)
                || !method_exists($order, 'get_meta')
                || !method_exists($order, 'update_meta_data')
                || !method_exists($order, 'save')
            ) {
                return;
            }

            $changed = false;
            $changed = $this->updateWooOrderMetaIfChanged($order, '_booking_room_id', $room_id) || $changed;
            $changed = $this->updateWooOrderMetaIfChanged($order, '_booking_item_id', $room_id) || $changed;
            $changed = $this->updateWooOrderMetaIfChanged($order, '_booking_id', (int) ($booking['id'] ?? 0)) || $changed;
            $changed = $this->updateWooOrderMetaIfChanged($order, '_booking_date', (string) ($booking['booking_date'] ?? '')) || $changed;
            $changed = $this->updateWooOrderMetaIfChanged($order, '_booking_from', (string) ($booking['booking_from'] ?? '')) || $changed;
            $changed = $this->updateWooOrderMetaIfChanged($order, '_booking_to', (string) ($booking['booking_to'] ?? '')) || $changed;
            $changed = $this->updateWooOrderMetaIfChanged(
                $order,
                'Startzeit',
                $this->formatBookingDateTimeMeta(
                    (string) ($booking['booking_date'] ?? ''),
                    (string) ($booking['booking_from'] ?? '')
                )
            ) || $changed;
            $changed = $this->updateWooOrderMetaIfChanged(
                $order,
                'Endzeit',
                $this->formatBookingDateTimeMeta(
                    (string) ($booking['booking_date'] ?? ''),
                    (string) ($booking['booking_to'] ?? '')
                )
            ) || $changed;

            if ($changed) {
                $order->save();
            }
        } catch (Throwable $e) {
            error_log(sprintf(
                'wesanox-booking room-sync: Woo-Order-Meta konnte fuer order_id=%d nicht aktualisiert werden: %s',
                $wc_order_id,
                $e->getMessage()
            ));
        }
    }

    private function updateWooOrderMetaIfChanged(object $order, string $key, mixed $value): bool
    {
        if ($value === null || $value === '' || $value === 0) {
            return false;
        }

        if ((string) $order->get_meta($key, true) === (string) $value) {
            return false;
        }

        $order->update_meta_data($key, $value);

        return true;
    }

    private function formatBookingDateTimeMeta(string $booking_date, string $booking_time): ?string
    {
        $booking_date = trim($booking_date);
        $booking_time = self::normalizeTime($booking_time);

        if (!self::isValidDate($booking_date) || !$booking_time || !self::isValidTime($booking_time)) {
            return null;
        }

        $timestamp = strtotime($booking_date . ' ' . $booking_time);

        if (!$timestamp) {
            return null;
        }

        return date('d.m.Y H:i', $timestamp);
    }

    /**
     * @param array<string, mixed>|null $booking
     */
    private function syncRoomAssignmentToBackend(?array $booking): void
    {
        if (!$booking) {
            return;
        }

        $source = strtolower(trim((string) ($booking['external_source'] ?? '')));

        if (
            $source !== 'backend'
            || empty($booking['external_id'])
            || empty($booking['room_id'])
            || (int) $booking['room_id'] <= 0
        ) {
            return;
        }

        try {
            $service = new SyncFrontendRoomAssignmentService(new WesanoxApiBridge());
            $response = $service->execute($booking);

            if (!$response->isOk()) {
                error_log(sprintf(
                    'wesanox-booking room-sync: Backend-Sync fehlgeschlagen fuer external_id=%s room_id=%s: %s (HTTP %d)',
                    (string) $booking['external_id'],
                    (string) $booking['room_id'],
                    $response->message,
                    $response->statusCode
                ));
            }
        } catch (Throwable $e) {
            error_log(sprintf(
                'wesanox-booking room-sync: Unerwarteter Fehler fuer external_id=%s: %s',
                (string) ($booking['external_id'] ?? ''),
                $e->getMessage()
            ));
        }
    }

    /**
     * @return array<string, string>
     */
    private function validateCreateParams(string $booking_date, ?string $booking_from, ?string $booking_to): array
    {
        $errors = [];

        if (!self::isValidDate($booking_date)) {
            $errors['booking_date'] = 'Invalid date, expected YYYY-MM-DD';
        }

        if (!$booking_from || !self::isValidTime($booking_from)) {
            $errors['booking_from'] = 'Invalid time, expected HH:MM or HH:MM:SS';
        }

        if (!$booking_to || !self::isValidTime($booking_to)) {
            $errors['booking_to'] = 'Invalid time, expected HH:MM or HH:MM:SS';
        }

        if (empty($errors) && $booking_from && $booking_to) {
            $from = DateTime::createFromFormat('H:i:s', $booking_from);
            $to   = DateTime::createFromFormat('H:i:s', $booking_to);

            if ($from && $to && $to <= $from) {
                $errors['time_range'] = 'booking_to must be after booking_from';
            }
        }

        return $errors;
    }

    /**
     * Builds the update data array, returns a WP_REST_Response on validation error.
     *
     * @param array<string, mixed> $p
     * @param array<string, mixed> $data
     * @param array<int, string>   $format
     */
    private function buildUpdateData(array $p, array &$data, array &$format): ?WP_REST_Response
    {
        if (array_key_exists('booking_date', $p)) {
            $booking_date = trim((string) $p['booking_date']);
            if ($booking_date !== '' && !self::isValidDate($booking_date)) {
                return new WP_REST_Response(['errors' => ['booking_date' => 'Invalid date, expected YYYY-MM-DD']], 422);
            }
            $data['booking_date'] = $booking_date;
            $format[] = '%s';
        }

        if (array_key_exists('booking_from', $p)) {
            $booking_from = self::normalizeTime((string) $p['booking_from']);
            if ($booking_from && !self::isValidTime($booking_from)) {
                return new WP_REST_Response(['errors' => ['booking_from' => 'Invalid time, expected HH:MM or HH:MM:SS']], 422);
            }
            $data['booking_from'] = $booking_from;
            $format[] = '%s';
        }

        if (array_key_exists('booking_to', $p)) {
            $booking_to = self::normalizeTime((string) $p['booking_to']);
            if ($booking_to && !self::isValidTime($booking_to)) {
                return new WP_REST_Response(['errors' => ['booking_to' => 'Invalid time, expected HH:MM or HH:MM:SS']], 422);
            }
            $data['booking_to'] = $booking_to;
            $format[] = '%s';
        }

        foreach (['room_id', 'wc_order_id'] as $field) {
            if (array_key_exists($field, $p)) {
                $data[$field] = is_null($p[$field]) || $p[$field] === '' ? null : (int) $p[$field];
                $format[] = '%d';
            }
        }

        if (array_key_exists('wc_customer_id', $p)) {
            $data['wc_customer_id'] = $this->nullableExistingWooCustomerId($p);
            $format[] = '%d';
        }

        if (array_key_exists('booking_status', $p) || array_key_exists('status', $p)) {
            $data['booking_status'] = $this->nullableNonNegativeInt($p, ['booking_status', 'status']);
            $format[] = '%d';
        }

        if (array_key_exists('suite_id', $p) && !array_key_exists('room_id', $data)) {
            $data['room_id'] = is_null($p['suite_id']) || $p['suite_id'] === '' ? null : (int) $p['suite_id'];
            $format[] = '%d';
        }

        if (array_key_exists('external_source', $p) || array_key_exists('source', $p)) {
            $data['external_source'] = $this->normalizeSource((string) ($p['external_source'] ?? $p['source'] ?? ''));
            $format[] = '%s';
        }

        foreach (['external_id', 'room_id_source', 'sync_status'] as $field) {
            if (array_key_exists($field, $p)) {
                $data[$field] = $p[$field] === null ? null : trim((string) $p[$field]);
                $format[] = '%s';
            }
        }

        if (isset($data['booking_from'], $data['booking_to']) && $data['booking_from'] && $data['booking_to']) {
            return $this->validateFinalTimeRange((string) $data['booking_from'], (string) $data['booking_to']);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $p
     * @return array<string, mixed>|WP_REST_Response
     */
    private function resolveRoomForWrite(
        array $p,
        string $booking_date,
        string $booking_from,
        string $booking_to,
        ?int $exclude_booking_id = null
    ): array|WP_REST_Response {
        $room_id = $this->nullableInt($p, ['room_id', 'suite_id']);
        $room_id_source = $this->normalizeRoomIdSource((string) ($p['room_id_source'] ?? 'wordpress'));

        if ($room_id === null) {
            $roomart_id = $this->nullableInt($p, ['roomart_id', 'room_art_id', 'item_category_id']);

            if ($roomart_id !== null && $this->shouldAssignRoom($p)) {
                $room_id = $this->findAvailableRoomByRoomart(
                    $roomart_id,
                    $booking_date,
                    $booking_from,
                    $booking_to,
                    $exclude_booking_id
                );

                if ($room_id === null) {
                    return $this->roomConflictResponse('No room available for this roomart and time range');
                }

                $room_id_source = 'wordpress';
            }
        }

        if ($room_id !== null) {
            $conflict = $this->assertRoomAvailable(
                $room_id,
                $booking_date,
                $booking_from,
                $booking_to,
                $exclude_booking_id
            );

            if ($conflict instanceof WP_REST_Response) {
                return $conflict;
            }
        }

        return [
            'room_id' => $room_id,
            'room_id_source' => $room_id_source,
            'assigned_by_wordpress' => $room_id !== null,
        ];
    }

    /**
     * @param array<string, mixed>      $p
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>|WP_REST_Response
     */
    private function resolveRoomForSync(
        array $p,
        string $external_source,
        string $booking_date,
        string $booking_from,
        string $booking_to,
        ?array $existing
    ): array|WP_REST_Response {
        $existing_id = $existing ? (int) $existing['id'] : null;
        $existing_room_id = $existing && $existing['room_id'] !== null ? (int) $existing['room_id'] : null;
        $room_id_source = $this->normalizeRoomIdSource((string) ($p['room_id_source'] ?? $external_source));
        $explicit_room_id = $this->nullableInt($p, ['room_id', 'suite_id']);
        $roomart_id = $this->nullableInt($p, ['roomart_id', 'room_art_id', 'item_category_id']);
        $explicit_is_master = in_array($room_id_source, self::ROOM_ID_MASTER_SOURCES, true)
            || in_array($external_source, self::ROOM_ID_MASTER_SOURCES, true);

        if ($explicit_room_id !== null && $explicit_is_master) {
            $conflict = $this->assertRoomAvailable(
                $explicit_room_id,
                $booking_date,
                $booking_from,
                $booking_to,
                $existing_id
            );

            if ($conflict instanceof WP_REST_Response) {
                return $conflict;
            }

            return [
                'room_id' => $explicit_room_id,
                'room_id_source' => $room_id_source ?: 'wordpress',
                'assigned_by_wordpress' => true,
            ];
        }

        if ($existing_room_id !== null && !$this->shouldReassignRoom($p)) {
            $conflict = $this->assertRoomAvailable(
                $existing_room_id,
                $booking_date,
                $booking_from,
                $booking_to,
                $existing_id
            );

            if ($conflict instanceof WP_REST_Response) {
                return $conflict;
            }

            return [
                'room_id' => $existing_room_id,
                'room_id_source' => (string) ($existing['room_id_source'] ?? 'wordpress'),
                'assigned_by_wordpress' => false,
            ];
        }

        if ($roomart_id !== null && $this->shouldAssignRoom($p)) {
            $room_id = $this->findAvailableRoomByRoomart(
                $roomart_id,
                $booking_date,
                $booking_from,
                $booking_to,
                $existing_id
            );

            if ($room_id === null) {
                return $this->roomConflictResponse('No room available for this roomart and time range');
            }

            return [
                'room_id' => $room_id,
                'room_id_source' => 'wordpress',
                'assigned_by_wordpress' => true,
            ];
        }

        return [
            'room_id' => $existing_room_id,
            'room_id_source' => $existing_room_id !== null ? (string) ($existing['room_id_source'] ?? 'wordpress') : null,
            'assigned_by_wordpress' => false,
        ];
    }

    private function assertRoomAvailable(
        int $room_id,
        string $booking_date,
        string $booking_from,
        string $booking_to,
        ?int $exclude_booking_id = null
    ): ?WP_REST_Response {
        if (!$this->lockRoom($room_id)) {
            return new WP_REST_Response(['errors' => ['room_id' => 'Room not found']], 422);
        }

        if ($this->roomHasOverlap($room_id, $booking_date, $booking_from, $booking_to, $exclude_booking_id)) {
            return $this->roomConflictResponse('Room is already booked for this time range');
        }

        return null;
    }

    private function lockRoom(int $room_id): bool
    {
        global $wpdb;

        $rooms_table = $wpdb->prefix . 'wesanox_rooms';
        $room = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$rooms_table} WHERE id = %d FOR UPDATE",
            $room_id
        ));

        return $room !== null;
    }

    private function roomHasOverlap(
        int $room_id,
        string $booking_date,
        string $booking_from,
        string $booking_to,
        ?int $exclude_booking_id = null
    ): bool {
        global $wpdb;

        $bookings_table = $this->bookingsTable();
        $orders_table   = $wpdb->prefix . 'wc_orders';
        [$buffer_start, $buffer_end] = $this->bufferedRange($booking_date, $booking_from, $booking_to);
        $placeholders = implode(',', array_fill(0, count(self::EXCLUDED_STATUSES), '%s'));
        $not_cancelled = BookingOccupancy::notCancelledSql('b');
        $exclude_sql = $exclude_booking_id !== null ? 'AND b.id <> %d' : '';

        $args = [
            $room_id,
            $buffer_end,
            $buffer_start,
        ];

        if ($exclude_booking_id !== null) {
            $args[] = $exclude_booking_id;
        }

        $args = array_merge($args, self::EXCLUDED_STATUSES);

        $sql = $wpdb->prepare(
            "SELECT COUNT(1)
             FROM {$bookings_table} b
             LEFT JOIN {$orders_table} o ON o.ID = b.wc_order_id
             WHERE b.room_id = %d
               AND CONCAT(b.booking_date, ' ', b.booking_from) < %s
               AND CONCAT(b.booking_date, ' ', b.booking_to) > %s
               {$exclude_sql}
               AND (o.ID IS NULL OR o.status NOT IN ({$placeholders})) AND {$not_cancelled}",
            $args
        );

        return (int) $wpdb->get_var($sql) > 0;
    }

    private function findAvailableRoomByRoomart(
        int $roomart_id,
        string $booking_date,
        string $booking_from,
        string $booking_to,
        ?int $exclude_booking_id = null
    ): ?int {
        global $wpdb;

        $rooms_table    = $wpdb->prefix . 'wesanox_rooms';
        $items_table    = $wpdb->prefix . 'wesanox_items';
        $bookings_table = $this->bookingsTable();
        $orders_table   = $wpdb->prefix . 'wc_orders';
        [$buffer_start, $buffer_end] = $this->bufferedRange($booking_date, $booking_from, $booking_to);
        $placeholders = implode(',', array_fill(0, count(self::EXCLUDED_STATUSES), '%s'));
        $not_cancelled = BookingOccupancy::notCancelledSql('b');
        $exclude_sql = $exclude_booking_id !== null ? 'AND b.id <> %d' : '';

        $args = [
            $roomart_id,
            $booking_date,
            $booking_date,
            $booking_date,
            $booking_date,
            $buffer_end,
            $buffer_start,
        ];

        if ($exclude_booking_id !== null) {
            $args[] = $exclude_booking_id;
        }

        $args = array_merge($args, self::EXCLUDED_STATUSES);

        $sql = $wpdb->prepare(
            "SELECT r.id
             FROM {$rooms_table} r
             LEFT JOIN {$items_table} i ON i.id = r.id
             WHERE r.roomart_id = %d
               AND (r.room_inactive IS NULL OR r.room_inactive = 0)
               AND NOT (
                    r.room_inactiv_from IS NOT NULL
                AND r.room_inactiv_from <= %s
                AND (r.room_inactiv_to IS NULL OR r.room_inactiv_to >= %s)
               )
               AND NOT (
                    i.id IS NOT NULL
                AND i.inactive = 1
                AND (
                        (i.inactiv_from IS NULL AND i.inactiv_to IS NULL)
                    OR  (i.inactiv_from IS NOT NULL AND i.inactiv_from <= %s AND (i.inactiv_to IS NULL OR i.inactiv_to >= %s))
                )
               )
               AND NOT EXISTS (
                    SELECT 1
                    FROM {$bookings_table} b
                    LEFT JOIN {$orders_table} o ON o.ID = b.wc_order_id
                    WHERE b.room_id = r.id
                      AND CONCAT(b.booking_date, ' ', b.booking_from) < %s
                      AND CONCAT(b.booking_date, ' ', b.booking_to) > %s
                      {$exclude_sql}
                      AND (o.ID IS NULL OR o.status NOT IN ({$placeholders})) AND {$not_cancelled}
               )
             ORDER BY r.id ASC
             LIMIT 1
             FOR UPDATE",
            $args
        );

        $room_id = (int) $wpdb->get_var($sql);

        return $room_id > 0 ? $room_id : null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function bufferedRange(string $booking_date, string $booking_from, string $booking_to): array
    {
        $buffer_start = date('Y-m-d H:i:s', strtotime("{$booking_date} {$booking_from} -" . self::BUFFER_MINUTES . ' minutes'));

        if ($booking_to === '00:00:00') {
            $buffer_end = "{$booking_date} 23:59:59";
        } else {
            $buffer_end = date('Y-m-d H:i:s', strtotime("{$booking_date} {$booking_to} +" . self::BUFFER_MINUTES . ' minutes'));
        }

        return [$buffer_start, $buffer_end];
    }

    private function validateFinalTimeRange(string $booking_from, string $booking_to): ?WP_REST_Response
    {
        $from = DateTime::createFromFormat('H:i:s', $booking_from);
        $to   = DateTime::createFromFormat('H:i:s', $booking_to);

        if ($from && $to && $to <= $from) {
            return new WP_REST_Response(['errors' => ['time_range' => 'booking_to must be after booking_from']], 422);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findById(string $table, int $id, bool $for_update = false): ?array
    {
        global $wpdb;

        $lock = $for_update ? ' FOR UPDATE' : '';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d{$lock}", $id), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findByExternalId(string $table, string $source, string $external_id, bool $for_update = false): ?array
    {
        global $wpdb;

        $lock = $for_update ? ' FOR UPDATE' : '';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE external_source = %s AND external_id = %s LIMIT 1{$lock}",
                $source,
                $external_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findByWooOrderId(string $table, int $wc_order_id, bool $for_update = false): ?array
    {
        global $wpdb;

        if ($wc_order_id <= 0) {
            return null;
        }

        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE wc_order_id = %d",
            $wc_order_id
        ));

        if ($count === 0) {
            return null;
        }

        $lock = $for_update ? ' FOR UPDATE' : '';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM {$table}
                 WHERE wc_order_id = %d
                 ORDER BY
                    CASE
                        WHEN external_source = 'backend' AND external_id IS NOT NULL THEN 0
                        WHEN external_id IS NOT NULL THEN 1
                        ELSE 2
                    END,
                    id ASC
                 LIMIT 1{$lock}",
                $wc_order_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    private function appendOptionalSyncFields(array $p, array &$data, array $room): void
    {
        if (array_key_exists('external_id', $p)) {
            $data['external_id'] = trim((string) $p['external_id']);
            $data['external_source'] = $this->normalizeSource((string) ($p['external_source'] ?? $p['source'] ?? 'wordpress'));
            $data['room_id_source'] = $room['room_id_source'];
            $data['sync_status'] = $room['room_id'] ? 'assigned_room' : 'pending_room';
            $data['last_synced_at'] = $this->now();
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, string>
     */
    private function formatsFor(array $data): array
    {
        $formats = [];

        foreach ($data as $field => $_value) {
            $formats[] = in_array($field, ['room_id', 'booking_status', 'wc_order_id', 'wc_customer_id'], true) ? '%d' : '%s';
        }

        return $formats;
    }

    private function nullableInt(array $payload, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            if ($payload[$key] === null || $payload[$key] === '') {
                return null;
            }

            $value = (int) $payload[$key];
            return $value > 0 ? $value : null;
        }

        return null;
    }

    private function nullableExistingWooCustomerId(array $payload): ?int
    {
        $customer_id = $this->nullableInt($payload, ['wc_customer_id']);

        if ($customer_id === null) {
            return null;
        }

        return $this->wooCustomerExists($customer_id) ? $customer_id : null;
    }

    private function wooCustomerExists(int $customer_id): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wc_customer_lookup';
        $existing_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        if ($existing_table !== $table) {
            return false;
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE customer_id = %d",
            $customer_id
        )) > 0;
    }

    private function nullableNonNegativeInt(array $payload, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            if ($payload[$key] === null || $payload[$key] === '') {
                return null;
            }

            $value = (int) $payload[$key];
            return $value >= 0 ? $value : null;
        }

        return null;
    }

    private function shouldAssignRoom(array $payload): bool
    {
        if (!array_key_exists('assign_room', $payload)) {
            return true;
        }

        return filter_var($payload['assign_room'], FILTER_VALIDATE_BOOLEAN);
    }

    private function shouldReassignRoom(array $payload): bool
    {
        if (!array_key_exists('reassign_room', $payload)) {
            return false;
        }

        return filter_var($payload['reassign_room'], FILTER_VALIDATE_BOOLEAN);
    }

    private function normalizeSource(string $source): string
    {
        $source = strtolower(trim($source));
        $source = preg_replace('/[^a-z0-9_\-]/', '', $source) ?: '';

        return substr($source, 0, 40);
    }

    private function normalizeRoomIdSource(string $source): ?string
    {
        $source = $this->normalizeSource($source);

        return $source !== '' ? $source : null;
    }

    private function now(): string
    {
        return function_exists('current_time')
            ? (string) current_time('mysql')
            : gmdate('Y-m-d H:i:s');
    }

    private function roomConflictResponse(string $message): WP_REST_Response
    {
        return new WP_REST_Response([
            'message' => $message,
            'errors'  => ['room_id' => $message],
        ], 409);
    }

    private function databaseError(string $message): WP_REST_Response
    {
        global $wpdb;

        return new WP_REST_Response([
            'message' => $message,
            'error'   => $wpdb->last_error,
        ], 500);
    }

    private function bookingsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'wesanox_bookings';
    }

    private function ensureSyncSchema(string $table): void
    {
        global $wpdb;

        $quoted_table = '`' . str_replace('`', '``', $table) . '`';
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$quoted_table}", 0);

        if (!is_array($columns)) {
            return;
        }

        $column_sql = [
            'booking_status'  => "ALTER TABLE {$quoted_table} ADD COLUMN booking_status TINYINT(3) UNSIGNED NULL AFTER room_id",
            'external_source' => "ALTER TABLE {$quoted_table} ADD COLUMN external_source VARCHAR(40) NULL AFTER wc_customer_id",
            'checkout_hold_token' => "ALTER TABLE {$quoted_table} ADD COLUMN checkout_hold_token VARCHAR(64) NULL AFTER wc_customer_id",
            'hold_expires_at' => "ALTER TABLE {$quoted_table} ADD COLUMN hold_expires_at DATETIME NULL AFTER checkout_hold_token",
            'external_id'     => "ALTER TABLE {$quoted_table} ADD COLUMN external_id VARCHAR(191) NULL AFTER external_source",
            'room_id_source'  => "ALTER TABLE {$quoted_table} ADD COLUMN room_id_source VARCHAR(40) NULL AFTER external_id",
            'sync_status'     => "ALTER TABLE {$quoted_table} ADD COLUMN sync_status VARCHAR(40) NULL AFTER room_id_source",
            'last_synced_at'  => "ALTER TABLE {$quoted_table} ADD COLUMN last_synced_at DATETIME NULL AFTER sync_status",
            'created_at'      => "ALTER TABLE {$quoted_table} ADD COLUMN created_at DATETIME NULL AFTER last_synced_at",
            'updated_at'      => "ALTER TABLE {$quoted_table} ADD COLUMN updated_at DATETIME NULL AFTER created_at",
        ];

        foreach ($column_sql as $column => $sql) {
            if (!in_array($column, $columns, true)) {
                $wpdb->query($sql);
            }
        }

        $indexes = $wpdb->get_col("SHOW INDEX FROM {$quoted_table}", 2);
        $indexes = is_array($indexes) ? array_unique($indexes) : [];

        if (!in_array('wesanox_booking_external', $indexes, true)) {
            $wpdb->query("ALTER TABLE {$quoted_table} ADD UNIQUE KEY wesanox_booking_external (external_source, external_id)");
        }

        if (!in_array('wesanox_booking_room_time', $indexes, true)) {
            $wpdb->query("ALTER TABLE {$quoted_table} ADD KEY wesanox_booking_room_time (room_id, booking_date, booking_from, booking_to)");
        }

        if (!in_array('wesanox_booking_checkout_hold', $indexes, true)) {
            $wpdb->query("ALTER TABLE {$quoted_table} ADD KEY wesanox_booking_checkout_hold (checkout_hold_token)");
        }

        if (!in_array('wesanox_booking_hold_expires', $indexes, true)) {
            $wpdb->query("ALTER TABLE {$quoted_table} ADD KEY wesanox_booking_hold_expires (hold_expires_at)");
        }
    }

    private function cleanupExpiredCheckoutHolds(): void
    {
        try {
            (new RepositoryBooking())->cleanup_expired_checkout_holds();
        } catch (Throwable $e) {
            error_log('wesanox-booking: Abgelaufene Checkout-Holds konnten nicht bereinigt werden: ' . $e->getMessage());
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function bookingArgs(bool $required): array
    {
        return [
            'booking_date'   => ['required' => $required, 'type' => 'string'],
            'booking_from'   => ['required' => $required, 'type' => 'string'],
            'booking_to'     => ['required' => $required, 'type' => 'string'],
            'room_id'        => ['required' => false,     'type' => ['integer', 'null']],
            'booking_status' => ['required' => false,     'type' => ['integer', 'null']],
            'status'         => ['required' => false,     'type' => ['integer', 'null']],
            'suite_id'       => ['required' => false,     'type' => ['integer', 'null']],
            'roomart_id'     => ['required' => false,     'type' => ['integer', 'null']],
            'item_category_id' => ['required' => false,   'type' => ['integer', 'null']],
            'assign_room'    => ['required' => false,     'type' => 'boolean'],
            'reassign_room'  => ['required' => false,     'type' => 'boolean'],
            'room_id_source' => ['required' => false,     'type' => ['string', 'null']],
            'external_source' => ['required' => false,    'type' => ['string', 'null']],
            'source'         => ['required' => false,     'type' => ['string', 'null']],
            'external_id'    => ['required' => false,     'type' => ['string', 'null']],
            'wc_order_id'    => ['required' => false,     'type' => ['integer', 'null']],
            'wc_customer_id' => ['required' => false,     'type' => ['integer', 'null']],
        ];
    }

    private static function isValidDate(string $date): bool
    {
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        return $dt && $dt->format('Y-m-d') === $date;
    }

    private static function isValidTime(string $time): bool
    {
        $dt = DateTime::createFromFormat('H:i:s', $time);
        return $dt && $dt->format('H:i:s') === $time;
    }

    private static function normalizeTime(?string $t): ?string
    {
        if ($t === null || $t === '') {
            return null;
        }

        $t = trim($t);

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $t)) {
            return $t;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $t)) {
            return $t . ':00';
        }

        return null;
    }
}
