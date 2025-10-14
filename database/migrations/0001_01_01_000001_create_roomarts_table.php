<?php
defined('ABSPATH') || exit;

function create_wesanox_roomarts_table()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'wesanox_roomarts';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        
        roomart_name VARCHAR(255) NOT NULL,
        
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}