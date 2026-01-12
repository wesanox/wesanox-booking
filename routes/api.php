<?php

defined('ABSPATH') || exit;

use Wesanox\Booking\Service\Api\ServiceAuthFixed;
use WP_REST_Request;
use WP_REST_Response;

/**
 * ===== Helpers =====
 */
if (!function_exists('valid_date')) {
    function valid_date(string $date): bool {
        $dt = \DateTime::createFromFormat('Y-m-d', $date);
        return $dt && $dt->format('Y-m-d') === $date;
    }
}

if (!function_exists('valid_time')) {
    function valid_time(string $time): bool {
        $dt = \DateTime::createFromFormat('H:i:s', $time);
        return $dt && $dt->format('H:i:s') === $time;
    }
}

if (!function_exists('normalize_time')) {
    function normalize_time(?string $t): ?string {
        if ($t === null || $t === '') return null;
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

function wesanox_create_booking(WP_REST_Request $request) {
    global $wpdb;
    $table = $wpdb->prefix . 'wesanox_bookings';

    $p = $request->get_json_params() ?: [];

    $booking_date = isset($p['booking_date']) ? trim((string)$p['booking_date']) : '';
    $booking_from = normalize_time($p['booking_from'] ?? null);
    $booking_to   = normalize_time($p['booking_to']   ?? null);

    $room_id        = isset($p['room_id'])        ? (int)$p['room_id']        : null;
    $wc_order_id    = array_key_exists('wc_order_id', $p)    ? (is_null($p['wc_order_id']) ? null : (int)$p['wc_order_id']) : null;
    $wc_customer_id = array_key_exists('wc_customer_id', $p) ? (is_null($p['wc_customer_id']) ? null : (int)$p['wc_customer_id']) : null;

    $errors = [];

    if (!valid_date($booking_date))   $errors['booking_date'] = 'Invalid date, expected YYYY-MM-DD';
    if (!$booking_from || !valid_time($booking_from)) $errors['booking_from'] = 'Invalid time, expected HH:MM or HH:MM:SS';
    if (!$booking_to   || !valid_time($booking_to))   $errors['booking_to']   = 'Invalid time, expected HH:MM or HH:MM:SS';

    if (empty($errors)) {
        $from = \DateTime::createFromFormat('H:i:s', $booking_from);
        $to   = \DateTime::createFromFormat('H:i:s', $booking_to);
        if ($from && $to && $to <= $from) {
            $errors['time_range'] = 'booking_to must be after booking_from';
        }
    }
    if (!empty($errors)) {
        return new WP_REST_Response(['errors' => $errors], 422);
    }

    // Insert
    $ok = $wpdb->insert($table, [
        'booking_date'   => $booking_date,
        'booking_from'   => $booking_from,
        'booking_to'     => $booking_to,
        'room_id'        => $room_id,
        'wc_order_id'    => $wc_order_id,
        'wc_customer_id' => $wc_customer_id,
    ], ['%s','%s','%s','%d','%d','%d']);

    if (!$ok) {
        return new WP_REST_Response([
            'message' => 'Database insert failed',
            'error'   => $wpdb->last_error,
        ], 500);
    }

    $id = (int)$wpdb->insert_id;

    return new WP_REST_Response([
        'id'             => $id,
        'booking_date'   => $booking_date,
        'booking_from'   => $booking_from,
        'booking_to'     => $booking_to,
        'room_id'        => $room_id,
        'wc_order_id'    => $wc_order_id,
        'wc_customer_id' => $wc_customer_id,
    ], 201);
}

function wesanox_update_booking(WP_REST_Request $request) {
    global $wpdb;
    $table = $wpdb->prefix . 'wesanox_bookings';

    $id = (int)$request->get_param('id');
    if ($id <= 0) {
        return new WP_REST_Response(['message' => 'Invalid id'], 400);
    }

    $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE id = %d", $id));
    if ($exists === 0) {
        return new WP_REST_Response(['message' => 'Booking not found'], 404);
    }

    $p = $request->get_json_params() ?: [];

    $data   = [];
    $format = [];

    if (array_key_exists('booking_date', $p)) {
        $booking_date = trim((string)$p['booking_date']);
        if ($booking_date !== '' && !valid_date($booking_date)) {
            return new WP_REST_Response(['errors' => ['booking_date' => 'Invalid date, expected YYYY-MM-DD']], 422);
        }
        $data['booking_date'] = $booking_date;
        $format[] = '%s';
    }

    if (array_key_exists('booking_from', $p)) {
        $booking_from = normalize_time($p['booking_from']);
        if ($booking_from && !valid_time($booking_from)) {
            return new WP_REST_Response(['errors' => ['booking_from' => 'Invalid time, expected HH:MM or HH:MM:SS']], 422);
        }
        $data['booking_from'] = $booking_from;
        $format[] = '%s';
    }

    if (array_key_exists('booking_to', $p)) {
        $booking_to = normalize_time($p['booking_to']);
        if ($booking_to && !valid_time($booking_to)) {
            return new WP_REST_Response(['errors' => ['booking_to' => 'Invalid time, expected HH:MM or HH:MM:SS']], 422);
        }
        $data['booking_to'] = $booking_to;
        $format[] = '%s';
    }

    foreach (['room_id','wc_order_id','wc_customer_id'] as $fld) {
        if (array_key_exists($fld, $p)) {
            $val = $p[$fld];
            $data[$fld] = is_null($val) ? null : (int)$val;
            $format[] = '%d';
        }
    }

    if (empty($data)) {
        return new WP_REST_Response(['message' => 'No fields to update'], 400);
    }

    if (isset($data['booking_from']) && isset($data['booking_to']) && $data['booking_from'] && $data['booking_to']) {
        $from = \DateTime::createFromFormat('H:i:s', $data['booking_from']);
        $to   = \DateTime::createFromFormat('H:i:s', $data['booking_to']);
        if ($from && $to && $to <= $from) {
            return new WP_REST_Response(['errors' => ['time_range' => 'booking_to must be after booking_from']], 422);
        }
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

function wesanox_delete_booking(WP_REST_Request $request) {
    global $wpdb;
    $table = $wpdb->prefix . 'wesanox_bookings';

    $id = (int) $request->get_param('id');
    if ($id <= 0) {
        return new WP_REST_Response(['message' => 'Invalid id'], 400);
    }

    $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE id = %d", $id));
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
 * Routes registrieren
 */
register_rest_route('wesanox/v1', '/bookings/create', [
    [
        'methods'             => WP_REST_Server::CREATABLE,
        'permission_callback' => [ServiceAuthFixed::class, 'wesanox_permission'],
        'callback'            => 'wesanox_create_booking',
        'args' => [
            'booking_date' => ['required' => true, 'type' => 'string'],
            'booking_from' => ['required' => true, 'type' => 'string'],
            'booking_to'   => ['required' => true, 'type' => 'string'],
            'room_id'      => ['required' => false, 'type' => 'integer'],
            'wc_order_id'  => ['required' => false, 'type' => ['integer','null']],
            'wc_customer_id' => ['required' => false, 'type' => ['integer','null']],
        ],
    ],
]);

register_rest_route('wesanox/v1', '/bookings/update/(?P<id>\d+)', [
    'methods'             => WP_REST_Server::EDITABLE,
    'permission_callback' => [ServiceAuthFixed::class, 'wesanox_permission'],
    'callback'            => 'wesanox_update_booking',
    'args' => [
        'id' => ['required' => true, 'type' => 'integer'],
        'booking_date'   => ['required' => false, 'type' => 'string'],
        'booking_from'   => ['required' => false, 'type' => 'string'],
        'booking_to'     => ['required' => false, 'type' => 'string'],
        'room_id'        => ['required' => false, 'type' => ['integer','null']],
        'wc_order_id'    => ['required' => false, 'type' => ['integer','null']],
        'wc_customer_id' => ['required' => false, 'type' => ['integer','null']],
    ],
]);

register_rest_route('wesanox/v1', '/bookings/delete/(?P<id>\d+)', [
    'methods'             => WP_REST_Server::DELETABLE,
    'permission_callback' => [ServiceAuthFixed::class, 'wesanox_permission'],
    'callback'            => 'wesanox_delete_booking',
    'args' => [
        'id' => ['required' => true, 'type' => 'integer'],
    ],
]);
