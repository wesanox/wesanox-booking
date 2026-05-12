<?php
defined('ABSPATH') || exit;

function create_wesanox_items_table()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'wesanox_items';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

        name VARCHAR(255) NOT NULL,
        inactive TINYINT(1) UNSIGNED DEFAULT NULL,
        inactiv_from DATE DEFAULT NULL,
        inactiv_to DATE DEFAULT NULL,
        inactiv_note LONGTEXT DEFAULT NULL,

        area_id BIGINT(20) UNSIGNED NULL,
        item_category_id BIGINT(20) UNSIGNED NULL,

        PRIMARY KEY (id),

        FOREIGN KEY (area_id) REFERENCES {$wpdb->prefix}wesanox_areas(id) ON DELETE SET NULL ON UPDATE CASCADE,
        FOREIGN KEY (item_category_id) REFERENCES {$wpdb->prefix}wesanox_item_categories(id) ON DELETE SET NULL ON UPDATE CASCADE
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}