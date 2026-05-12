<?php
defined('ABSPATH') || exit;

function create_wesanox_rates_table(): void
{
    global $wpdb;

    $table_name      = $wpdb->prefix . 'wesanox_rates';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = <<<SQL
    CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        area_id BIGINT(20) UNSIGNED NOT NULL,
        item_category_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        name VARCHAR(190) NOT NULL DEFAULT '',
        time_from TIME NOT NULL,
        time_to TIME NOT NULL,
        days VARCHAR(500) NOT NULL DEFAULT '["monday","tuesday","wednesday","thursday","friday","saturday","sunday"]',
        woocommerce_product_id BIGINT(20) UNSIGNED NOT NULL,
        woocommerce_variation_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
        is_active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL DEFAULT NULL,
        PRIMARY KEY (id),
        KEY area_id (area_id),
        KEY area_category (area_id, item_category_id),
        KEY area_active (area_id, is_active),
        KEY area_time (area_id, time_from, time_to),
        KEY sort_order (sort_order)
    ) {$charset_collate};
    SQL;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
