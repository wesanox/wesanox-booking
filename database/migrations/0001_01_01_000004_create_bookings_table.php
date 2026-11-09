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
        booking_status TINYINT(3) UNSIGNED NULL,
        wc_order_id BIGINT(20) UNSIGNED NULL,
        wc_customer_id BIGINT(20) UNSIGNED NULL,
        checkout_hold_token VARCHAR(64) NULL,
        hold_expires_at DATETIME NULL,

        external_source VARCHAR(40) NULL,
        external_id VARCHAR(191) NULL,
        room_id_source VARCHAR(40) NULL,
        sync_status VARCHAR(40) NULL,
        last_synced_at DATETIME NULL,
        created_at DATETIME NULL,
        updated_at DATETIME NULL,
        
        PRIMARY KEY (id),
        UNIQUE KEY wesanox_booking_external (external_source, external_id),
        KEY wesanox_booking_room_time (room_id, booking_date, booking_from, booking_to),
        KEY wesanox_booking_checkout_hold (checkout_hold_token),
        KEY wesanox_booking_hold_expires (hold_expires_at),
        
        FOREIGN KEY (room_id) REFERENCES {$wpdb->prefix}wesanox_rooms(ID) ON DELETE SET NULL ON UPDATE CASCADE,
        FOREIGN KEY (wc_customer_id) REFERENCES {$wpdb->prefix}wc_customer_lookup(customer_id) ON DELETE SET NULL ON UPDATE CASCADE,
        FOREIGN KEY (wc_order_id) REFERENCES {$wpdb->prefix}wc_orders(ID) ON DELETE SET NULL ON UPDATE CASCADE
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    wesanox_booking_ensure_sync_schema($table_name);
}

function wesanox_booking_ensure_sync_schema(string $table_name): void
{
    global $wpdb;

    $quoted_table = '`' . str_replace('`', '``', $table_name) . '`';
    $columns = $wpdb->get_col("SHOW COLUMNS FROM {$quoted_table}", 0);

    if (!is_array($columns)) {
        return;
    }

    $column_sql = [
        'external_source' => "ALTER TABLE {$quoted_table} ADD COLUMN external_source VARCHAR(40) NULL AFTER wc_customer_id",
        'booking_status'  => "ALTER TABLE {$quoted_table} ADD COLUMN booking_status TINYINT(3) UNSIGNED NULL AFTER room_id",
        'checkout_hold_token' => "ALTER TABLE {$quoted_table} ADD COLUMN checkout_hold_token VARCHAR(64) NULL AFTER wc_customer_id",
        'hold_expires_at' => "ALTER TABLE {$quoted_table} ADD COLUMN hold_expires_at DATETIME NULL AFTER checkout_hold_token",
        'external_id'     => "ALTER TABLE {$quoted_table} ADD COLUMN external_id VARCHAR(191) NULL AFTER external_source",
        'room_id_source'  => "ALTER TABLE {$quoted_table} ADD COLUMN room_id_source VARCHAR(40) NULL AFTER external_id",
        'sync_status'     => "ALTER TABLE {$quoted_table} ADD COLUMN sync_status VARCHAR(40) NULL AFTER room_id_source",
        'last_synced_at'  => "ALTER TABLE {$quoted_table} ADD COLUMN last_synced_at DATETIME NULL AFTER sync_status",
        'created_at'      => "ALTER TABLE {$quoted_table} ADD COLUMN created_at DATETIME NULL AFTER last_synced_at",
        'updated_at'      => "ALTER TABLE {$quoted_table} ADD COLUMN updated_at DATETIME NULL AFTER created_at",
    ];

    foreach ($column_sql as $column => $sql) {
        if (!in_array($column, $columns, true)) {
            $wpdb->query($sql);
        }
    }

    $indexes = $wpdb->get_col("SHOW INDEX FROM {$quoted_table}", 2);
    $indexes = is_array($indexes) ? array_unique($indexes) : [];

    if (!in_array('wesanox_booking_external', $indexes, true)) {
        $wpdb->query("ALTER TABLE {$quoted_table} ADD UNIQUE KEY wesanox_booking_external (external_source, external_id)");
    }

    if (!in_array('wesanox_booking_room_time', $indexes, true)) {
        $wpdb->query("ALTER TABLE {$quoted_table} ADD KEY wesanox_booking_room_time (room_id, booking_date, booking_from, booking_to)");
    }

    if (!in_array('wesanox_booking_checkout_hold', $indexes, true)) {
        $wpdb->query("ALTER TABLE {$quoted_table} ADD KEY wesanox_booking_checkout_hold (checkout_hold_token)");
    }

    if (!in_array('wesanox_booking_hold_expires', $indexes, true)) {
        $wpdb->query("ALTER TABLE {$quoted_table} ADD KEY wesanox_booking_hold_expires (hold_expires_at)");
    }
}
