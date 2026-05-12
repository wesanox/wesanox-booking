<?php
defined('ABSPATH') || exit;

function create_wesanox_areas_table()
{
    global $wpdb;

    $table_name      = $wpdb->prefix . 'wesanox_areas';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

        area_name VARCHAR(190) DEFAULT NULL,
        area_opening LONGTEXT DEFAULT NULL,
        area_time_settings LONGTEXT DEFAULT NULL,
        area_booking_settings LONGTEXT DEFAULT NULL,
        wesanox_api_settings LONGTEXT DEFAULT NULL,

        PRIMARY KEY  (id),

        KEY area_name (area_name)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    $column_exists = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s
               AND TABLE_NAME   = %s
               AND COLUMN_NAME  = 'wesanox_api_settings'",
            DB_NAME,
            $table_name
        )
    );

    if (empty($column_exists)) {
        $wpdb->query(
            "ALTER TABLE `{$table_name}`
             ADD COLUMN `wesanox_api_settings` LONGTEXT DEFAULT NULL"
        );
    }
}