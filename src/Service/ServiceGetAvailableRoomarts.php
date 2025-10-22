<?php

namespace Wesanox\Booking\Service;

defined( 'ABSPATH' )|| exit;

class ServiceGetAvailableRoomarts
{
    public function wesanox_roomart_available( int $roomart_id, string $date, string $time_start, string $time_end ): bool {
        global $wpdb;

        $rooms_table    = $wpdb->prefix . 'wesanox_rooms';
        $bookings_table = $wpdb->prefix . 'wesanox_bookings';

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
            return false; // kein aktiver Raum
        }

        foreach ($rooms as $room) {
            // Prüfe, ob *keine* kollidierende Buchung existiert
            $has_overlap = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "
                SELECT COUNT(1)
                FROM {$bookings_table} b
                WHERE b.room_id = %d
                  AND CONCAT(b.booking_date, ' ', b.booking_from) < %s
                  AND CONCAT(b.booking_date, ' ', b.booking_to)   > %s
                ",
                    $room->id,
                    $buffer_end,
                    $buffer_start
                )
            );

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

        // Normalisieren
        $date = date('Y-m-d', strtotime($date_ymd));
        if (!$date) return null;

        $buffer_start = date('Y-m-d H:i:s', strtotime("$date $time_start -{$buffer_minutes} minutes"));
        $buffer_end   = date('Y-m-d H:i:s', strtotime("$date $time_end +{$buffer_minutes} minutes"));

        // Ein freier Raum = aktiver Raum ohne überlappende Buchung
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
                WHERE b.room_id = r.id
                  AND CONCAT(b.booking_date,' ',b.booking_from) < %s
                  AND CONCAT(b.booking_date,' ',b.booking_to)   > %s
              )
            ORDER BY r.id ASC
            LIMIT 1
        ", $roomart_id, $date, $date, $buffer_end, $buffer_start);

        $room_id = (int) $wpdb->get_var($sql);
        return $room_id ?: null;
    }
}