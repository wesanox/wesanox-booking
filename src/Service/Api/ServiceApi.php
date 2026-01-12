<?php

namespace Wesanox\Booking\Service\Api;

defined('ABSPATH') || exit;

class ServiceApi
{
    public static function boot(): void
    {
        add_action('rest_api_init', [static::class, 'wesanox_register_routes']);
    }

    public static function wesanox_register_routes(): void
    {
        $routes_file = plugin_dir_path(dirname(__FILE__, 3)) . '/routes/api.php';

        if (file_exists($routes_file)) {
            require_once $routes_file;
        }
    }
}