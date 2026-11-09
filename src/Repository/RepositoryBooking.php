<?php

namespace Wesanox\Booking\Repository;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Booking\BookingOccupancy;
class RepositoryBooking
{
    public function ensure_checkout_hold_schema(): void
    {
        global $wpdb;

        if (!is_object($wpdb) || !method_exists($wpdb, 'get_col') || !method_exists($wpdb, 'query')) {
            return;
        }

        $table = $wpdb->prefix . 'wesanox_bookings';
        $quoted_table = '`' . str_replace('`', '``', $table) . '`';
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$quoted_table}", 0);

        if (!is_array($columns)) {
            return;
        }

        if (!in_array('checkout_hold_token', $columns, true)) {
            $wpdb->query("ALTER TABLE {$quoted_table} ADD COLUMN checkout_hold_token VARCHAR(64) NULL AFTER wc_customer_id");
        }

        if (!in_array('hold_expires_at', $columns, true)) {
            $wpdb->query("ALTER TABLE {$quoted_table} ADD COLUMN hold_expires_at DATETIME NULL AFTER checkout_hold_token");
        }

        $indexes = $wpdb->get_col("SHOW INDEX FROM {$quoted_table}", 2);
        $indexes = is_array($indexes) ? array_unique($indexes) : [];

        if (!in_array('wesanox_booking_checkout_hold', $indexes, true)) {
            $wpdb->query("ALTER TABLE {$quoted_table} ADD KEY wesanox_booking_checkout_hold (checkout_hold_token)");
        }

        if (!in_array('wesanox_booking_hold_expires', $indexes, true)) {
            $wpdb->query("ALTER TABLE {$quoted_table} ADD KEY wesanox_booking_hold_expires (hold_expires_at)");
        }
    }

    public function cleanup_expired_checkout_holds(?string $now = null): int
    {
        global $wpdb;

        if (!is_object($wpdb) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'query') || !method_exists($wpdb, 'get_col')) {
            return 0;
        }

        $this->ensure_checkout_hold_schema();

        $table = $wpdb->prefix . 'wesanox_bookings';
        $now ??= $this->now();

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table}
                 WHERE wc_order_id IS NULL
                   AND checkout_hold_token IS NOT NULL
                   AND hold_expires_at IS NOT NULL
                   AND hold_expires_at <= %s",
                $now
            )
        );

        return is_int($deleted) ? $deleted : 0;
    }

    /** Insert in wp_wesanox_bookings */
    public function insert_booking( string $date_ymd, string $from_his, string $to_his, int $room_id, ?int $order_id, ?int $customer_id ): ?int {
        global $wpdb;
        $table = $wpdb->prefix . 'wesanox_bookings';

        if ( $order_id !== null && $order_id > 0 ) {
            $existing = $this->find_by_order_id( (int) $order_id );
            if ( is_array( $existing ) && isset( $existing['id'] ) ) {
                return (int) $existing['id'];
            }
        }

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

    public function insert_checkout_hold(
        string $date_ymd,
        string $from_his,
        string $to_his,
        int $room_id,
        ?int $customer_id,
        string $token,
        string $expires_at
    ): ?int {
        global $wpdb;

        $this->ensure_checkout_hold_schema();

        $table = $wpdb->prefix . 'wesanox_bookings';
        $now = $this->now();

        $ok = $wpdb->insert(
            $table,
            [
                'booking_date'        => $date_ymd,
                'booking_from'        => $from_his,
                'booking_to'          => $to_his,
                'room_id'             => $room_id,
                'wc_order_id'         => null,
                'wc_customer_id'      => $customer_id ?: null,
                'checkout_hold_token' => $token,
                'hold_expires_at'     => $expires_at,
                'created_at'          => $now,
                'updated_at'          => $now,
            ],
            [ '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' ]
        );

        return $ok ? (int) $wpdb->insert_id : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function find_active_checkout_hold_by_token( string $token, bool $for_update = false ): ?array {
        global $wpdb;

        if ($token === '') {
            return null;
        }

        $this->ensure_checkout_hold_schema();

        $table = $wpdb->prefix . 'wesanox_bookings';
        $lock = $for_update ? ' FOR UPDATE' : '';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, booking_date, booking_from, booking_to, room_id, wc_order_id, wc_customer_id, checkout_hold_token, hold_expires_at
                 FROM {$table}
                 WHERE checkout_hold_token = %s
                   AND wc_order_id IS NULL
                   AND (hold_expires_at IS NULL OR hold_expires_at > %s)
                 ORDER BY id ASC
                 LIMIT 1{$lock}",
                $token,
                $this->now()
            ),
            ARRAY_A
        );

        return is_array( $row ) ? $row : null;
    }

    public function release_checkout_hold( string $token ): int
    {
        global $wpdb;

        if ($token === '') {
            return 0;
        }

        $this->ensure_checkout_hold_schema();

        $table = $wpdb->prefix . 'wesanox_bookings';
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table}
                 WHERE checkout_hold_token = %s
                   AND wc_order_id IS NULL",
                $token
            )
        );

        return is_int($deleted) ? $deleted : 0;
    }

    public function attach_order_to_booking( int $booking_id, int $order_id, ?int $customer_id ): bool
    {
        global $wpdb;

        if ($booking_id <= 0 || $order_id <= 0) {
            return false;
        }

        $this->ensure_checkout_hold_schema();

        $table = $wpdb->prefix . 'wesanox_bookings';
        $now = $this->now();

        if ($customer_id !== null && $customer_id > 0) {
            $result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table}
                     SET wc_order_id = %d,
                         wc_customer_id = %d,
                         checkout_hold_token = NULL,
                         hold_expires_at = NULL,
                         updated_at = %s
                     WHERE id = %d",
                    $order_id,
                    $customer_id,
                    $now,
                    $booking_id
                )
            );
        } else {
            $result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table}
                     SET wc_order_id = %d,
                         checkout_hold_token = NULL,
                         hold_expires_at = NULL,
                         updated_at = %s
                     WHERE id = %d",
                    $order_id,
                    $now,
                    $booking_id
                )
            );
        }

        return $result !== false;
    }

    /**
     * Find the first booking linked to a WooCommerce order.
     *
     * @return array<string,mixed>|null
     */
    public function find_by_order_id( int $order_id, bool $for_update = false ): ?array {
        global $wpdb;

        if ( $order_id <= 0 ) {
            return null;
        }

        $table = $wpdb->prefix . 'wesanox_bookings';
        $lock = $for_update ? ' FOR UPDATE' : '';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, booking_date, booking_from, booking_to, room_id, wc_order_id, wc_customer_id, checkout_hold_token, hold_expires_at
                 FROM {$table}
                 WHERE wc_order_id = %d
                 ORDER BY id ASC
                 LIMIT 1{$lock}",
                $order_id
            ),
            ARRAY_A
        );

        return is_array( $row ) ? $row : null;
    }

    /**
     * Find a booking by primary key.
     *
     * @return array<string,mixed>|null
     */
    public function find_by_id( int $booking_id, bool $for_update = false ): ?array {
        global $wpdb;

        if ( $booking_id <= 0 ) {
            return null;
        }

        $table = $wpdb->prefix . 'wesanox_bookings';
        $lock = $for_update ? ' FOR UPDATE' : '';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, booking_date, booking_from, booking_to, room_id, wc_order_id, wc_customer_id, checkout_hold_token, hold_expires_at
                 FROM {$table}
                 WHERE id = %d
                 LIMIT 1{$lock}",
                $booking_id
            ),
            ARRAY_A
        );

        return is_array( $row ) ? $row : null;
    }

    /**
     * Count the bookings of a day that still block a room.
     * Bookings of cancelled, refunded or failed orders are ignored (same rule as the availability check).
     */
    public function count_active_bookings_for_date( string $date_ymd ): int
    {
        global $wpdb;

        $bookings_table = $wpdb->prefix . 'wesanox_bookings';
        $orders_table   = $wpdb->prefix . 'wc_orders';

        $excluded_post_statuses = ['wc-cancelled', 'wc-refunded', 'wc-failed', 'trash'];
        $placeholders = implode(',', array_fill(0, count($excluded_post_statuses), '%s'));
        $not_cancelled = BookingOccupancy::notCancelledSql('b');

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$bookings_table} b
                 LEFT JOIN {$orders_table} o ON o.ID = b.wc_order_id
                 WHERE b.booking_date = %s
                   AND (o.ID IS NULL OR o.status NOT IN ($placeholders)) AND {$not_cancelled}",
                array_merge([$date_ymd], $excluded_post_statuses)
            )
        );
    }

    private function now(): string
    {
        return function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s');
    }
}
