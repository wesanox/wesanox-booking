<?php

namespace Wesanox\Booking\Service;

defined( 'ABSPATH' )|| exit;

class ServiceGetAvailableRoomarts
{
    public function wesanox_roomart_available( int $roomart_id, string $date, string $time_start, string $time_end ): bool {
        global $wpdb;

        $rooms_table    = $wpdb->prefix . 'wesanox_rooms';
        $bookings_table = $wpdb->prefix . 'wesanox_bookings';
        $orders_table   = $wpdb->prefix . 'wc_orders';

        $date_ymd = date('Y-m-d', strtotime($date));
        if (!$date_ymd) return false;

        $buffer_start = date('Y-m-d H:i:s', strtotime("$date_ymd $time_start -30 minutes"));
        $buffer_end   = date('Y-m-d H:i:s', strtotime("$date_ymd $time_end +30 minutes"));

        $rooms = $wpdb->get_results(
            $wpdb->prepare(
                "
            SELECT r.id
            FROM {$rooms_table} r
            WHERE r.roomart_id = %d
              AND (r.room_inactive IS NULL OR r.room_inactive = 0)
              AND NOT (
                    r.room_inactiv_from IS NOT NULL
                AND  r.room_inactiv_from <= %s
                AND (r.room_inactiv_to IS NULL OR r.room_inactiv_to >= %s)
              )
            ",
                $roomart_id, $date_ymd, $date_ymd
            )
        );

        if (empty($rooms)) {
            return false;
        }

        $excluded_post_statuses = ['wc-cancelled','wc-refunded','wc-failed','trash'];
        $placeholders = implode(',', array_fill(0, count($excluded_post_statuses), '%s'));

        foreach ($rooms as $room) {
            $sql = $wpdb->prepare(
                "
                SELECT COUNT(1)
                FROM {$bookings_table} b
                LEFT JOIN {$orders_table} o ON o.ID = b.wc_order_id
                WHERE b.room_id = %d
                  AND CONCAT(b.booking_date, ' ', b.booking_from) < %s
                  AND CONCAT(b.booking_date, ' ', b.booking_to)   > %s
                  AND (o.ID IS NULL OR o.status NOT IN ($placeholders))
                ",
                array_merge(
                    [$room->id, $buffer_end, $buffer_start],
                    $excluded_post_statuses
                )
            );

            $has_overlap = (int) $wpdb->get_var($sql);

            if ($has_overlap === 0) {
                // Diesen Raum können wir online anbieten
                return true;
            }
        }

        return false;
    }

    public function wesanox_find_room( int $roomart_id, string $date_ymd, string $time_start, string $time_end, int $buffer_minutes = 30 ): ?int {
        global $wpdb;

        $rooms_table    = $wpdb->prefix . 'wesanox_rooms';
        $bookings_table = $wpdb->prefix . 'wesanox_bookings';
        $orders_table   = $wpdb->prefix . 'wc_orders';

        // Normalisieren
        $date = date('Y-m-d', strtotime($date_ymd));
        if (!$date) return null;

        $buffer_start = date('Y-m-d H:i:s', strtotime("$date $time_start -{$buffer_minutes} minutes"));
        $buffer_end   = date('Y-m-d H:i:s', strtotime("$date $time_end +{$buffer_minutes} minutes"));

        $excluded_post_statuses = ['wc-cancelled','wc-refunded','wc-failed','trash'];
        $placeholders = implode(',', array_fill(0, count($excluded_post_statuses), '%s'));

        $sql = $wpdb->prepare("
                SELECT r.id
                FROM {$rooms_table} r
                WHERE r.roomart_id = %d
                  AND (r.room_inactive IS NULL OR r.room_inactive = 0)
                  AND NOT (
                        r.room_inactiv_from IS NOT NULL
                    AND  r.room_inactiv_from <= %s
                    AND (r.room_inactiv_to IS NULL OR r.room_inactiv_to >= %s)
                  )
                  AND NOT EXISTS (
                    SELECT 1
                    FROM {$bookings_table} b
                    LEFT JOIN {$orders_table} o ON o.ID = b.wc_order_id
                    WHERE b.room_id = r.id
                      AND CONCAT(b.booking_date,' ',b.booking_from) < %s
                      AND CONCAT(b.booking_date,' ',b.booking_to)   > %s
                      AND (o.ID IS NULL OR o.status NOT IN ($placeholders))
                  )
                ORDER BY r.id ASC
                LIMIT 1
            ",
            array_merge(
                [$roomart_id, $date, $date, $buffer_end, $buffer_start],
                $excluded_post_statuses
            )
        );

        $room_id = (int) $wpdb->get_var($sql);
        return $room_id ?: null;
    }
}