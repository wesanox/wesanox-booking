<?php

declare(strict_types=1);

namespace Wesanox\Booking\Infrastructure\Booking;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Booking\Booking;
use Wesanox\Booking\Domain\Booking\BookingRepositoryInterface;
use Wesanox\Booking\Domain\Booking\BookingStatus;

/**
 * WordPress / $wpdb implementation of BookingRepositoryInterface.
 * All SQL queries live here — no SQL in Application or Domain layers.
 */
final class WordPressBookingRepository implements BookingRepositoryInterface
{
    /** @return Booking[] */
    public function findAll(?string $status = null): array
    {
        global $wpdb;

        $bookings_table = $wpdb->prefix . 'wesanox_bookings';
        $rooms_table    = $wpdb->prefix . 'wesanox_rooms';
        $orders_table   = $wpdb->prefix . 'wc_orders';

        $where  = '';
        $params = [];

        if ($status === BookingStatus::NO_ORDER) {
            $where = 'WHERE b.wc_order_id IS NULL';
        } elseif ($status !== null && $status !== '') {
            $wc_status = 'wc-' . $status;
            $where     = $wpdb->prepare('WHERE o.status = %s', $wc_status);
        }

        $sql = "
            SELECT
                b.id,
                b.booking_date,
                b.booking_from,
                b.booking_to,
                b.room_id,
                b.wc_order_id,
                b.wc_customer_id,
                COALESCE(o.status, '') AS wc_order_status,
                COALESCE(r.room_name, '') AS room_name
            FROM `{$bookings_table}` b
            LEFT JOIN `{$rooms_table}` r ON r.id = b.room_id
            LEFT JOIN `{$orders_table}` o ON o.ID = b.wc_order_id
            {$where}
            ORDER BY b.booking_date DESC, b.booking_from DESC
        ";

        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];

        return array_map([$this, 'hydrateRow'], $rows);
    }

    public function findById(int $id): ?Booking
    {
        global $wpdb;

        $bookings_table = $wpdb->prefix . 'wesanox_bookings';
        $rooms_table    = $wpdb->prefix . 'wesanox_rooms';
        $orders_table   = $wpdb->prefix . 'wc_orders';

        $sql = $wpdb->prepare(
            "
            SELECT
                b.id,
                b.booking_date,
                b.booking_from,
                b.booking_to,
                b.room_id,
                b.wc_order_id,
                b.wc_customer_id,
                COALESCE(o.status, '') AS wc_order_status,
                COALESCE(r.room_name, '') AS room_name
            FROM `{$bookings_table}` b
            LEFT JOIN `{$rooms_table}` r ON r.id = b.room_id
            LEFT JOIN `{$orders_table}` o ON o.ID = b.wc_order_id
            WHERE b.id = %d
            LIMIT 1
            ",
            $id
        );

        $row = $wpdb->get_row($sql, ARRAY_A);

        return $row ? $this->hydrateRow($row) : null;
    }

    public function cancelByWcOrder(int $wc_order_id): bool
    {
        $order = wc_get_order($wc_order_id);

        if (!$order) {
            return false;
        }

        $allowed = ['pending', 'processing', 'on-hold'];

        if (!in_array($order->get_status(), $allowed, true)) {
            return false;
        }

        $order->update_status(
            'cancelled',
            __('Storniert durch Admin.', 'wesanox-booking')
        );

        return true;
    }

    /** @param array<string, mixed> $row */
    private function hydrateRow(array $row): Booking
    {
        $wc_status = BookingStatus::fromWcStatus($row['wc_order_status'] ?: null);

        return new Booking(
            id:              (int) $row['id'],
            booking_date:    (string) $row['booking_date'],
            booking_from:    (string) $row['booking_from'],
            booking_to:      (string) $row['booking_to'],
            room_id:         isset($row['room_id'])        ? (int) $row['room_id']        : null,
            wc_order_id:     isset($row['wc_order_id'])    ? (int) $row['wc_order_id']    : null,
            wc_customer_id:  isset($row['wc_customer_id']) ? (int) $row['wc_customer_id'] : null,
            wc_order_status: $wc_status,
            room_name:       (string) ($row['room_name'] ?? ''),
            customer_name:   $this->resolveCustomerName((int) ($row['wc_order_id'] ?? 0)),
        );
    }

    private function resolveCustomerName(int $wc_order_id): string
    {
        if ($wc_order_id <= 0) {
            return '';
        }

        $order = wc_get_order($wc_order_id);

        if (!$order) {
            return '';
        }

        return trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
    }
}
