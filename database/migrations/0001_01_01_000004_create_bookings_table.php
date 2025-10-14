<?php
defined('ABSPATH') || exit;

function create_wesanox_bookings_table()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'wesanox_bookings';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        
        booking_date DATE NOT NULL,
        booking_from TIME NOT NULL,
        booking_to TIME NOT NULL,
        
        room_id BIGINT(20) UNSIGNED NULL,
        wc_order_id BIGINT(20) UNSIGNED NULL,
        wc_customer_id BIGINT(20) UNSIGNED NULL,
        
        PRIMARY KEY (id),
        
        FOREIGN KEY (room_id) REFERENCES {$wpdb->prefix}wesanox_rooms(ID) ON DELETE SET NULL ON UPDATE CASCADE,
        FOREIGN KEY (wc_customer_id) REFERENCES {$wpdb->prefix}wc_customer_lookup(customer_id) ON DELETE SET NULL ON UPDATE CASCADE,
        FOREIGN KEY (wc_order_id) REFERENCES {$wpdb->prefix}wc_orders(ID) ON DELETE SET NULL ON UPDATE CASCADE
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}