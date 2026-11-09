<?php

defined('ABSPATH') || exit;

use Wesanox\Booking\Rest\Controllers\BookingController;
use Wesanox\Booking\Service\Api\ServiceAuthFixed;

if (!function_exists('wesanox_booking_rest_controller')) {
    function wesanox_booking_rest_controller(): BookingController
    {
        return new BookingController();
    }
}

if (!function_exists('wesanox_create_booking')) {
    function wesanox_create_booking(\WP_REST_Request $request)
    {
        return wesanox_booking_rest_controller()->create($request);
    }
}

if (!function_exists('wesanox_sync_booking')) {
    function wesanox_sync_booking(\WP_REST_Request $request)
    {
        return wesanox_booking_rest_controller()->sync($request);
    }
}

if (!function_exists('wesanox_update_booking')) {
    function wesanox_update_booking(\WP_REST_Request $request)
    {
        return wesanox_booking_rest_controller()->update($request);
    }
}

if (!function_exists('wesanox_delete_booking')) {
    function wesanox_delete_booking(\WP_REST_Request $request)
    {
        return wesanox_booking_rest_controller()->delete($request);
    }
}

if (!function_exists('wesanox_delete_booking_by_external')) {
    function wesanox_delete_booking_by_external(\WP_REST_Request $request)
    {
        return wesanox_booking_rest_controller()->deleteByExternal($request);
    }
}

$booking_args = [
    'booking_date'     => ['required' => true,  'type' => 'string'],
    'booking_from'     => ['required' => true,  'type' => 'string'],
    'booking_to'       => ['required' => true,  'type' => 'string'],
    'room_id'          => ['required' => false, 'type' => ['integer', 'null']],
    'suite_id'         => ['required' => false, 'type' => ['integer', 'null']],
    'roomart_id'       => ['required' => false, 'type' => ['integer', 'null']],
    'item_category_id' => ['required' => false, 'type' => ['integer', 'null']],
    'assign_room'      => ['required' => false, 'type' => 'boolean'],
    'reassign_room'    => ['required' => false, 'type' => 'boolean'],
    'room_id_source'   => ['required' => false, 'type' => ['string', 'null']],
    'external_source'  => ['required' => false, 'type' => ['string', 'null']],
    'source'           => ['required' => false, 'type' => ['string', 'null']],
    'external_id'      => ['required' => false, 'type' => ['string', 'null']],
    'wc_order_id'      => ['required' => false, 'type' => ['integer', 'null']],
    'wc_customer_id'   => ['required' => false, 'type' => ['integer', 'null']],
];

register_rest_route('wesanox/v1', '/bookings/create', [
    [
        'methods'             => WP_REST_Server::CREATABLE,
        'permission_callback' => [ServiceAuthFixed::class, 'wesanox_permission'],
        'callback'            => 'wesanox_create_booking',
        'args'                => $booking_args,
    ],
]);

register_rest_route('wesanox/v1', '/bookings/sync', [
    [
        'methods'             => WP_REST_Server::CREATABLE,
        'permission_callback' => [ServiceAuthFixed::class, 'wesanox_permission'],
        'callback'            => 'wesanox_sync_booking',
        'args'                => array_merge(
            $booking_args,
            [
                'source'          => ['required' => false, 'type' => 'string'],
                'external_source' => ['required' => false, 'type' => 'string'],
                'external_id'     => ['required' => true,  'type' => 'string'],
            ]
        ),
    ],
]);

register_rest_route('wesanox/v1', '/bookings/update/(?P<id>\d+)', [
    'methods'             => WP_REST_Server::EDITABLE,
    'permission_callback' => [ServiceAuthFixed::class, 'wesanox_permission'],
    'callback'            => 'wesanox_update_booking',
    'args'                => array_merge(
        ['id' => ['required' => true, 'type' => 'integer']],
        array_map(
            static function (array $arg): array {
                $arg['required'] = false;
                return $arg;
            },
            $booking_args
        )
    ),
]);

register_rest_route('wesanox/v1', '/bookings/delete/(?P<id>\d+)', [
    'methods'             => WP_REST_Server::DELETABLE,
    'permission_callback' => [ServiceAuthFixed::class, 'wesanox_permission'],
    'callback'            => 'wesanox_delete_booking',
    'args'                => [
        'id' => ['required' => true, 'type' => 'integer'],
    ],
]);

register_rest_route('wesanox/v1', '/bookings/delete-by-external', [
    'methods'             => WP_REST_Server::DELETABLE,
    'permission_callback' => [ServiceAuthFixed::class, 'wesanox_permission'],
    'callback'            => 'wesanox_delete_booking_by_external',
    'args'                => [
        'source'          => ['required' => false, 'type' => ['string', 'null']],
        'external_source' => ['required' => false, 'type' => ['string', 'null']],
        'external_id'     => ['required' => true,  'type' => 'string'],
    ],
]);
