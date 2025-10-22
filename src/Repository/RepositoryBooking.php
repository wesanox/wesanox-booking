<?php

namespace Wesanox\Booking\Repository;

defined('ABSPATH') || exit;

class RepositoryBooking
{
    /** Insert in wp_wesanox_bookings */
    public function insert_booking( string $date_ymd, string $from_his, string $to_his, int $room_id, ?int $order_id, ?int $customer_id ): ?int {
        global $wpdb;
        $table = $wpdb->prefix . 'wesanox_bookings';

        $ok = $wpdb->insert(
            $table,
            [
                'booking_date'  => $date_ymd,
                'booking_from'  => $from_his,
                'booking_to'    => $to_his,
                'room_id'       => $room_id,
                'wc_order_id'   => $order_id ?: null,
                'wc_customer_id'=> $customer_id ?: null,
            ],
            [ '%s', '%s', '%s', '%d', '%d', '%d' ]
        );

        return $ok ? (int) $wpdb->insert_id : null;
    }
}
