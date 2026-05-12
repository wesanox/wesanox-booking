<?php

declare(strict_types=1);

namespace Wesanox\Booking\Infrastructure\Activation;

defined('ABSPATH') || exit;

final class Activator
{
    public static function activate(): void
    {
        self::runMigrations();
        flush_rewrite_rules();
    }

    private static function runMigrations(): void
    {
        $migration_file = plugin_dir_path(dirname(__DIR__, 2)) . 'database/wesanox_migration.php';

        if (file_exists($migration_file)) {
            require_once $migration_file;
        }

        if (function_exists('wesanox_run_migrations')) {
            wesanox_run_migrations();
        }
    }
}
