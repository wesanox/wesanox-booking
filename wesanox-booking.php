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
 * Requires PHP: 8.2
 * Text Domain: wesanox-booking
 * Domain Path: /languages
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

require_once __DIR__ . '/vendor/autoload.php';

use Wesanox\Booking\Plugin;

register_activation_hook(
    __FILE__,
    [Plugin::class, 'activate']
);

register_deactivation_hook(
    __FILE__,
    [Plugin::class, 'deactivate']
);

add_action(
    'plugins_loaded',
    static function (): void {
        Plugin::init();
    }
);
