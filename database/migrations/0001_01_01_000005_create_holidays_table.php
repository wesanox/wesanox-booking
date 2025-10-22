<?php
defined('ABSPATH') || exit;

function create_wesanox_holidays_table()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'wesanox_holidays';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        
        opening_date DATE DEFAULT NULL,
        opening_from TIME DEFAULT NULL,
        opening_to TIME DEFAULT NULL,
        
        opening_closed TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
        opening_holiday TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}