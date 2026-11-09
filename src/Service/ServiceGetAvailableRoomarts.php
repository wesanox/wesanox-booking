<?php

namespace Wesanox\Booking\Service;

defined( 'ABSPATH' )|| exit;

use Wesanox\Booking\Domain\Booking\BookingOccupancy;
use Wesanox\Booking\Repository\RepositoryBooking;

class ServiceGetAvailableRoomarts
{
    public function wesanox_roomart_available(
        int $roomart_id,
        string $date,
        string $time_start,
        string $time_end,
        ?int $area_id = null
    ): bool {
        global $wpdb;

        $this->cleanup_expired_checkout_holds();

        $rooms_table    = $wpdb->prefix . 'wesanox_rooms';
        $items_table    = $wpdb->prefix . 'wesanox_items';
        $bookings_table = $wpdb->prefix . 'wesanox_bookings';
        $orders_table   = $wpdb->prefix . 'wc_orders';

        $date_ymd = date('Y-m-d', strtotime($date));
        if (!$date_ymd) return false;

        $buffer_start = date('Y-m-d H:i:s', strtotime("$date_ymd $time_start -30 minutes"));

        if($time_end == '00:00:00') {
            $time_end = '23:59:59';
            $buffer_end   = date('Y-m-d H:i:s', strtotime("$date_ymd $time_end" ));
        } else {
            $buffer_end   = date('Y-m-d H:i:s', strtotime("$date_ymd $time_end +30 minutes"));
        }

        $area_condition = '';
        $room_args = [$roomart_id];

        if ($area_id !== null && $area_id > 0) {
            $area_condition = 'AND r.area_id = %d';
            $room_args[] = $area_id;
        }

        array_push($room_args, $date_ymd, $date_ymd, $date_ymd, $date_ymd);

        $rooms = $wpdb->get_results(
            $wpdb->prepare(
                "
            SELECT r.id
            FROM {$rooms_table} r
            LEFT JOIN {$items_table} i ON i.id = r.id
            WHERE r.roomart_id = %d
              {$area_condition}
              AND (r.room_inactive IS NULL OR r.room_inactive = 0)
              AND NOT (
                    r.room_inactiv_from IS NOT NULL
                AND  r.room_inactiv_from <= %s
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
            ",
                $room_args
            )
        );

        if (empty($rooms)) {
            return false;
        }

        $excluded_post_statuses = ['wc-cancelled','wc-refunded','wc-failed','trash'];
        $placeholders = implode(',', array_fill(0, count($excluded_post_statuses), '%s'));
        $not_cancelled = BookingOccupancy::notCancelledSql('b');

        foreach ($rooms as $room) {
            $sql = $wpdb->prepare(
                "
                SELECT COUNT(1)
                FROM {$bookings_table} b
                LEFT JOIN {$orders_table} o ON o.ID = b.wc_order_id
                WHERE b.room_id = %d
                  AND CONCAT(b.booking_date, ' ', b.booking_from) < %s
                  AND CONCAT(b.booking_date, ' ', b.booking_to)   > %s
                  AND (o.ID IS NULL OR o.status NOT IN ($placeholders)) AND {$not_cancelled}
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

    /**
     * Same as wesanox_find_room() but adds FOR UPDATE on the rooms row.
     *
     * Must be called inside an open InnoDB transaction.
     * The lock prevents a second concurrent transaction from selecting the same
     * room until the first transaction commits, eliminating the race condition
     * where two simultaneous checkouts claim the same slot.
     */
    public function wesanox_find_room_locked( int $roomart_id, string $date_ymd, string $time_start, string $time_end, int $buffer_minutes = 30 ): ?int {
        global $wpdb;

        $this->cleanup_expired_checkout_holds();

        $rooms_table    = $wpdb->prefix . 'wesanox_rooms';
        $items_table    = $wpdb->prefix . 'wesanox_items';
        $bookings_table = $wpdb->prefix . 'wesanox_bookings';
        $orders_table   = $wpdb->prefix . 'wc_orders';

        $date = date('Y-m-d', strtotime($date_ymd));
        if (!$date) return null;

        $buffer_start = date('Y-m-d H:i:s', strtotime("$date $time_start -{$buffer_minutes} minutes"));
        $buffer_end   = date('Y-m-d H:i:s', strtotime("$date $time_end +{$buffer_minutes} minutes"));

        $excluded_post_statuses = ['wc-cancelled', 'wc-refunded', 'wc-failed', 'trash'];
        $placeholders = implode(',', array_fill(0, count($excluded_post_statuses), '%s'));
        $not_cancelled = BookingOccupancy::notCancelledSql('b');

        $sql = $wpdb->prepare("
                SELECT r.id
                FROM {$rooms_table} r
                LEFT JOIN {$items_table} i ON i.id = r.id
                WHERE r.roomart_id = %d
                  AND (r.room_inactive IS NULL OR r.room_inactive = 0)
                  AND NOT (
                        r.room_inactiv_from IS NOT NULL
                    AND  r.room_inactiv_from <= %s
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
                      AND CONCAT(b.booking_date,' ',b.booking_from) < %s
                      AND CONCAT(b.booking_date,' ',b.booking_to)   > %s
                      AND (o.ID IS NULL OR o.status NOT IN ($placeholders)) AND {$not_cancelled}
                  )
                ORDER BY r.id ASC
                LIMIT 1
                FOR UPDATE
            ",
            array_merge(
                [$roomart_id, $date, $date, $date, $date, $buffer_end, $buffer_start],
                $excluded_post_statuses
            )
        );

        $room_id = (int) $wpdb->get_var($sql);
        return $room_id ?: null;
    }

    public function wesanox_find_room( int $roomart_id, string $date_ymd, string $time_start, string $time_end, int $buffer_minutes = 30 ): ?int {
        global $wpdb;

        $this->cleanup_expired_checkout_holds();

        $rooms_table    = $wpdb->prefix . 'wesanox_rooms';
        $items_table    = $wpdb->prefix . 'wesanox_items';
        $bookings_table = $wpdb->prefix . 'wesanox_bookings';
        $orders_table   = $wpdb->prefix . 'wc_orders';

        // Normalisieren
        $date = date('Y-m-d', strtotime($date_ymd));
        if (!$date) return null;

        $buffer_start = date('Y-m-d H:i:s', strtotime("$date $time_start -{$buffer_minutes} minutes"));
        $buffer_end   = date('Y-m-d H:i:s', strtotime("$date $time_end +{$buffer_minutes} minutes"));

        $excluded_post_statuses = ['wc-cancelled','wc-refunded','wc-failed','trash'];
        $placeholders = implode(',', array_fill(0, count($excluded_post_statuses), '%s'));
        $not_cancelled = BookingOccupancy::notCancelledSql('b');

        $sql = $wpdb->prepare("
                SELECT r.id
                FROM {$rooms_table} r
                LEFT JOIN {$items_table} i ON i.id = r.id
                WHERE r.roomart_id = %d
                  AND (r.room_inactive IS NULL OR r.room_inactive = 0)
                  AND NOT (
                        r.room_inactiv_from IS NOT NULL
                    AND  r.room_inactiv_from <= %s
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
                      AND CONCAT(b.booking_date,' ',b.booking_from) < %s
                      AND CONCAT(b.booking_date,' ',b.booking_to)   > %s
                      AND (o.ID IS NULL OR o.status NOT IN ($placeholders)) AND {$not_cancelled}
                  )
                ORDER BY r.id ASC
                LIMIT 1
            ",
            array_merge(
                [$roomart_id, $date, $date, $date, $date, $buffer_end, $buffer_start],
                $excluded_post_statuses
            )
        );

        $room_id = (int) $wpdb->get_var($sql);
        return $room_id ?: null;
    }

    private function cleanup_expired_checkout_holds(): void
    {
        try {
            (new RepositoryBooking())->cleanup_expired_checkout_holds();
        } catch (\Throwable $e) {
            error_log('wesanox-booking: Abgelaufene Checkout-Holds konnten nicht bereinigt werden: ' . $e->getMessage());
        }
    }
}
