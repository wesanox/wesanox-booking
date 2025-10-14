<?php
defined( 'ABSPATH' )|| exit;

/**
 * Include all migration files
 */
require_once __DIR__ . '/migrations/0001_01_01_000000_create_areas_table.php';
require_once __DIR__ . '/migrations/0001_01_01_000001_create_roomarts_table.php';
require_once __DIR__ . '/migrations/0001_01_01_000002_create_rooms_table.php';
require_once __DIR__ . '/migrations/0001_01_01_000003_create_rates_table.php';
require_once __DIR__ . '/migrations/0001_01_01_000004_create_bookings_table.php';
require_once __DIR__ . '/migrations/0001_01_01_000005_create_holidays_table.php';

/**
 * Create a new table in the database when the plugin is activated
 */
function wesanox_run_migrations()
{
    $migrations = [
        'create_wesanox_areas_table',
        'create_wesanox_roomarts_table',
        'create_wesanox_rooms_table',
        'create_wesanox_rates_table',
        'create_wesanox_bookings_table',
        'create_wesanox_holidays_table',
    ];

    foreach ($migrations as $fn) {
        if (function_exists($fn)) {
            call_user_func($fn);
        } else {
            error_log('Function ' . $fn . ' not found');
        }
    }
}