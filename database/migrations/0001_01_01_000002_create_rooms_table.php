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
        room_inactive TINYINT(1) UNSIGNED DEFAULT NULL,
        room_inactiv_from DATE DEFAULT NULL,
        room_inactiv_to DATE DEFAULT NULL,
        room_inactiv_note LONGTEXT DEFAULT NULL,
        
        area_id BIGINT(20) UNSIGNED NULL,
        roomart_id BIGINT(20) UNSIGNED NULL,
        
        PRIMARY KEY (id),
        
        FOREIGN KEY (area_id) REFERENCES {$wpdb->prefix}wesanox_areas(ID) ON DELETE SET NULL ON UPDATE CASCADE,
        FOREIGN KEY (roomart_id) REFERENCES {$wpdb->prefix}wesanox_roomarts(ID) ON DELETE SET NULL ON UPDATE CASCADE
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}