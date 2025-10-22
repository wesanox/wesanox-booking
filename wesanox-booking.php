<?php
/**
 * Wesanox Booking Plugin.
 *
 * @copyright Copyright (C) 2025, Wessanox
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 *
 * @wordpress-plugin
 * Plugin Name: Wesanox Booking Plugin
 * Version:     0.2
 * Plugin URI:  https://wesanox.de
 * Description: Booking tool for Spa and Wellness bookings
 * Author:      Frittenfritze
 * Author URI:  https://wesanox.de
 * License:     GPL v3
 * Requires at least: 6.4
 * Requires PHP: 8.0.0
 * Text Domain: my-basics-plugin
 * Domain Path: /languages
 */
defined( 'ABSPATH' )|| exit;

/**
 * Include all files
 */
require_once __DIR__ . '/database/wesanox_migration.php';

/**
 * Create a new table in the database when the plugin is activated
 */
register_activation_hook(__FILE__, 'wesanox_run_migrations');

/**
 * Autoload class
 *
 * @param $class_name
 * @return void
 */
function wesanox_autoload ( $class_name ) : void
{
    $prefix = 'Wesanox\\Booking\\';
    $base_dir = plugin_dir_path(__FILE__) . 'src/';

    if (strpos($class_name, $prefix) !== 0) {
        return;
    }

    $relative_class = substr($class_name, strlen($prefix));
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
}

spl_autoload_register('wesanox_autoload');

/**
 * Initialize the plugin
 */
new \Wesanox\Booking\Init();