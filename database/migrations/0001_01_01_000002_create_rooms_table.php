<?php
defined('ABSPATH') || exit;

function create_wesanox_rooms_table()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'wesanox_rooms';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        
        room_name VARCHAR(255) NOT NULL,
        room_inactive TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
        room_inactiv_from TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
        room_inactiv_to TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
        room_inactiv_note TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
        
        area_id BIGINT(20) UNSIGNED NULL,
        roomart_id BIGINT(20) UNSIGNED NULL,
        
        PRIMARY KEY (id),
        
        FOREIGN KEY (area_id) REFERENCES {$wpdb->prefix}wesanox_areas(ID) ON DELETE SET NULL ON UPDATE CASCADE,
        FOREIGN KEY (roomart_id) REFERENCES {$wpdb->prefix}wesanox_roomarts(ID) ON DELETE SET NULL ON UPDATE CASCADE
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}