<?php

declare(strict_types=1);

namespace Wesanox\Booking\Rest\Controllers;

defined('ABSPATH') || exit;

use DateTime;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use Wesanox\Booking\Service\Api\ServiceAuth;

final class BookingController
{
    private const REST_NAMESPACE = 'wesanox/v1';

    public function registerRoutes(): void
    {
        register_rest_route(self::REST_NAMESPACE, '/bookings/create', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'permission_callback' => [ServiceAuth::class, 'permission_callback'],
                'callback'            => [$this, 'create'],
                'args'                => [
                    'booking_date'   => ['required' => true,  'type' => 'string'],
                    'booking_from'   => ['required' => true,  'type' => 'string'],
                    'booking_to'     => ['required' => true,  'type' => 'string'],
                    'room_id'        => ['required' => false, 'type' => 'integer'],
                    'wc_order_id'    => ['required' => false, 'type' => ['integer', 'null']],
                    'wc_customer_id' => ['required' => false, 'type' => ['integer', 'null']],
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/bookings/update/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::EDITABLE,
            'permission_callback' => [ServiceAuth::class, 'permission_callback'],
            'callback'            => [$this, 'update'],
            'args'                => [
                'id'             => ['required' => true,  'type' => 'integer'],
                'booking_date'   => ['required' => false, 'type' => 'string'],
                'booking_from'   => ['required' => false, 'type' => 'string'],
                'booking_to'     => ['required' => false, 'type' => 'string'],
                'room_id'        => ['required' => false, 'type' => ['integer', 'null']],
                'wc_order_id'    => ['required' => false, 'type' => ['integer', 'null']],
                'wc_customer_id' => ['required' => false, 'type' => ['integer', 'null']],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/bookings/delete/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'permission_callback' => [ServiceAuth::class, 'permission_callback'],
            'callback'            => [$this, 'delete'],
            'args'                => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);
    }

    public function create(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $p = $request->get_json_params() ?: [];

        $booking_date   = isset($p['booking_date']) ? trim((string) $p['booking_date']) : '';
        $booking_from   = self::normalizeTime($p['booking_from'] ?? null);
        $booking_to     = self::normalizeTime($p['booking_to']   ?? null);
        $room_id        = isset($p['room_id'])        ? (int) $p['room_id']        : null;
        $wc_order_id    = array_key_exists('wc_order_id', $p)    ? (is_null($p['wc_order_id'])    ? null : (int) $p['wc_order_id'])    : null;
        $wc_customer_id = array_key_exists('wc_customer_id', $p) ? (is_null($p['wc_customer_id']) ? null : (int) $p['wc_customer_id']) : null;

        $errors = $this->validateCreateParams($booking_date, $booking_from, $booking_to);

        if (!empty($errors)) {
            return new WP_REST_Response(['errors' => $errors], 422);
        }

        $ok = $wpdb->insert(
            $wpdb->prefix . 'wesanox_bookings',
            [
                'booking_date'   => $booking_date,
                'booking_from'   => $booking_from,
                'booking_to'     => $booking_to,
                'room_id'        => $room_id,
                'wc_order_id'    => $wc_order_id,
                'wc_customer_id' => $wc_customer_id,
            ],
            ['%s', '%s', '%s', '%d', '%d', '%d']
        );

        if (!$ok) {
            return new WP_REST_Response([
                'message' => 'Database insert failed',
                'error'   => $wpdb->last_error,
            ], 500);
        }

        return new WP_REST_Response([
            'id'             => (int) $wpdb->insert_id,
            'booking_date'   => $booking_date,
            'booking_from'   => $booking_from,
            'booking_to'     => $booking_to,
            'room_id'        => $room_id,
            'wc_order_id'    => $wc_order_id,
            'wc_customer_id' => $wc_customer_id,
        ], 201);
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wesanox_bookings';
        $id    = (int) $request->get_param('id');

        if ($id <= 0) {
            return new WP_REST_Response(['message' => 'Invalid id'], 400);
        }

        $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE id = %d", $id));
        if ($exists === 0) {
            return new WP_REST_Response(['message' => 'Booking not found'], 404);
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

        $ok = $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);

        if ($ok === false) {
            return new WP_REST_Response([
                'message' => 'Database update failed',
                'error'   => $wpdb->last_error,
            ], 500);
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);

        return new WP_REST_Response($row, 200);
    }

    public function delete(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wesanox_bookings';
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
            return new WP_REST_Response([
                'message' => 'Database delete failed',
                'error'   => $wpdb->last_error,
            ], 500);
        }

        return new WP_REST_Response(null, 204);
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
            $booking_from = self::normalizeTime($p['booking_from']);
            if ($booking_from && !self::isValidTime($booking_from)) {
                return new WP_REST_Response(['errors' => ['booking_from' => 'Invalid time, expected HH:MM or HH:MM:SS']], 422);
            }
            $data['booking_from'] = $booking_from;
            $format[] = '%s';
        }

        if (array_key_exists('booking_to', $p)) {
            $booking_to = self::normalizeTime($p['booking_to']);
            if ($booking_to && !self::isValidTime($booking_to)) {
                return new WP_REST_Response(['errors' => ['booking_to' => 'Invalid time, expected HH:MM or HH:MM:SS']], 422);
            }
            $data['booking_to'] = $booking_to;
            $format[] = '%s';
        }

        if (isset($data['booking_from'], $data['booking_to']) && $data['booking_from'] && $data['booking_to']) {
            $from = DateTime::createFromFormat('H:i:s', $data['booking_from']);
            $to   = DateTime::createFromFormat('H:i:s', $data['booking_to']);

            if ($from && $to && $to <= $from) {
                return new WP_REST_Response(['errors' => ['time_range' => 'booking_to must be after booking_from']], 422);
            }
        }

        foreach (['room_id', 'wc_order_id', 'wc_customer_id'] as $field) {
            if (array_key_exists($field, $p)) {
                $data[$field] = is_null($p[$field]) ? null : (int) $p[$field];
                $format[] = '%d';
            }
        }

        return null;
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
