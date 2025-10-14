<?php
defined('ABSPATH') || exit;

function create_wesanox_rates_table()
{
    global $wpdb;

    $table_name      = $wpdb->prefix . 'wesanox_rates';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        
        rate_day TINYINT(3) UNSIGNED NULL,
        rate_time_from TIME NULL,
        rate_time_to TIME NULL,

        product_id BIGINT(20) UNSIGNED NULL,
        product_variation_id BIGINT(20) UNSIGNED NULL,
        roomart_id BIGINT(20) UNSIGNED NULL,

        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        
        FOREIGN KEY (roomart_id) REFERENCES {$wpdb->prefix}wesanox_roomarts(ID) ON DELETE SET NULL ON UPDATE CASCADE,

        KEY rate_day (rate_day),
        KEY time_from (rate_time_from),
        KEY time_to (rate_time_to),
        KEY product_id (product_id),
        KEY product_variation_id (product_variation_id),
        KEY roomart_id (roomart_id),

        KEY day_from (rate_day, rate_time_from),
        KEY day_to (rate_day, rate_time_to)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}