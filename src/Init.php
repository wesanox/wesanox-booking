<?php

namespace Wesanox\Booking;

defined( 'ABSPATH' )|| exit;

use Wesanox\Booking\Views\Controller\AdminViewController;
use Wesanox\Booking\Views\Admin\Helper\AreaHelper;

class Init
{
    public function __construct()
    {
        new AdminViewController();

        new AreaHelper();

        add_action('admin_enqueue_scripts', [$this, 'wesanox_booking_register_admin']);
    }

    /**
     * Register the scss and js for admin view in the WordPress editor.
     *
     * @return void
     */
    public function wesanox_booking_register_admin() : void
    {
        wp_enqueue_style( 'wesanox-booking-admin-css', plugins_url('styles/admin/styles.css', dirname(__FILE__, 1)));

        wp_enqueue_script('wesanox-booking-admin-function', plugins_url('scripts/main/_functions.js', dirname(__FILE__, 1)), array('jquery'), filemtime(plugin_dir_path(dirname(__FILE__, 1)) . 'scripts/main/_functions.js'), true);
        wp_enqueue_script('wesanox-booking-admin', plugins_url('scripts/admin/scripts.js', dirname(__FILE__, 1)), array('jquery'), filemtime(plugin_dir_path(dirname(__FILE__, 1)) . 'scripts/admin/scripts.js'), true);

        // Übergib die AJAX-URL und Nonce an JS
        wp_localize_script('wesanox-booking-admin', 'ajax_object', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wesanox_booking_nonce'),
        ]);
    }
}