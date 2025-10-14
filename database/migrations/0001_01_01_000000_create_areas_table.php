<?php
defined('ABSPATH') || exit;

function create_wesanox_areas_table()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'wesanox_areas';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        
        area_name VARCHAR(190) NULL,
        area_opening LONGTEXT NULL,
        area_time_settings LONGTEXT NULL,
        
        PRIMARY KEY  (id),
        
        KEY area_name (area_name)
    ) {$charset_collate};";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}