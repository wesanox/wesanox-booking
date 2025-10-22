<?php

namespace Wesanox\Booking;

defined( 'ABSPATH' )|| exit;

use Wesanox\Booking\View\Controller\AdminViewController;
use Wesanox\Booking\View\Admin\Helper\AreaHelper;
use Wesanox\Booking\View\Frontend\ShortCodes\ShortCodeBooking;
use Wesanox\Booking\View\Frontend\ShortCodes\ShortCodeCancle;
use Wesanox\Booking\View\Frontend\ShortCodes\ShortCodeUpsell;
use Wesanox\Booking\Woocommerce\WoocommerceProductHandler;
use Wesanox\Booking\Boot\Booking\HandlerBooking;

class Init
{
    public function __construct()
    {
        new AdminViewController();

        new AreaHelper();

        new HandlerBooking();

        /**
         * Add Shortcodes
         */
        new ShortCodeBooking();
        new ShortCodeCancle();
        new ShortCodeUpsell();

        /**
         * Add Woocommerce Product Handler functions
         */
        new WoocommerceProductHandler();

        /**
         * Register the scss and js for admin and frontend view
         */
        add_action('admin_enqueue_scripts', [$this, 'wesanox_booking_register_admin']);
        add_action('wp_enqueue_scripts', [$this, 'wesanox_booking_register_frontend']);
    }

    /**
     * Register the scss and js for admin view in the WordPress editor.
     *
     * @return void
     */
    public function wesanox_booking_register_admin() : void
    {
        wp_enqueue_style( 'wesanox-booking-admin-css', plugins_url('styles/admin/styles.css', dirname(__FILE__, 1)));

        wp_enqueue_script('wesanox-booking-admin-function', plugins_url('scripts/functions/_functions.js', dirname(__FILE__, 1)), array('jquery'), filemtime(plugin_dir_path(dirname(__FILE__, 1)) . 'scripts/functions/_functions.js'), true);
        wp_enqueue_script('wesanox-booking-admin', plugins_url('scripts/admin/scripts.js', dirname(__FILE__, 1)), array('jquery'), filemtime(plugin_dir_path(dirname(__FILE__, 1)) . 'scripts/admin/scripts.js'), true);

        wp_localize_script('wesanox-booking-admin', 'ajax_object', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wesanox_booking_nonce'),
        ]);
    }

    /**
     * Register the sass and js for the frontend view.
     *
     * @return void
     */
    public function wesanox_booking_register_frontend() : void
    {
        /**
         * Framework Styles
         */
        wp_enqueue_style('wesanox-booking-swiper-css', plugin_dir_url(dirname(__FILE__, 1)) . 'styles/frameworks/swiper.min.css');
        wp_enqueue_style('wesanox-booking-bootstrap-css', plugin_dir_url(dirname(__FILE__, 1)) . 'styles/frameworks/bootstrap.css');
        wp_enqueue_style('wesanox-booking-zabuto-css', plugin_dir_url(dirname(__FILE__, 1)) . 'styles/frameworks/jquery.zabuto_calendar.css');

        /**
         * Main Styles
         */
        wp_enqueue_style('wesanox-booking', plugins_url('styles/frontend/styles.css', dirname(__FILE__, 1)), array(), filemtime(plugin_dir_path(dirname(__FILE__, 1)) . 'styles/frontend/styles.css'));

        /**
         * Framework Scripts
         */
        wp_enqueue_script('wesanox-booking-swiper-js', plugin_dir_url(dirname(__FILE__, 1)) . 'scripts/frameworks/swiper.min.js', array('jquery'));
        wp_enqueue_script('wesanox-booking-bootstrap-js', plugin_dir_url(dirname(__FILE__, 1)) . 'scripts/frameworks/bootstrap.min.js', array('jquery'));
        wp_enqueue_script('wesanox-booking-zabuto-js', plugin_dir_url(dirname(__FILE__, 1)). 'scripts/frameworks/jquery.zabuto_calendar.js', array('jquery'));

        /**
         * Main Scripts
         */
        wp_enqueue_script('wesanox-booking-frontend-function', plugin_dir_url(dirname(__FILE__, 1)) . 'scripts/functions/_functions.js', array('jquery'), filemtime(plugin_dir_path(dirname(__FILE__, 1)) . 'scripts/functions/_functions.js'),true);
        wp_enqueue_script('wesanox-booking-frontend', plugin_dir_url(dirname(__FILE__, 1)) . 'scripts/frontend/scripts.js', array('jquery'), filemtime(plugin_dir_path(dirname(__FILE__, 1)) . 'scripts/frontend/scripts.js'),true);

        /**
         * Element Scripts
         */
        wp_enqueue_script('wesanox-element-booking-persons', plugin_dir_url(dirname(__FILE__, 1)) . 'scripts/frontend/elements/element-booking-persons.js', array('jquery'), filemtime(plugin_dir_path(dirname(__FILE__, 1)) . 'scripts/frontend/elements/element-booking-persons.js'),true);
        wp_enqueue_script('wesanox-element-booking-calender', plugin_dir_url(dirname(__FILE__, 1)) . 'scripts/frontend/elements/element-booking-calender.js', array('jquery'), filemtime(plugin_dir_path(dirname(__FILE__, 1)) . 'scripts/frontend/elements/element-booking-calender.js'),true);
        wp_enqueue_script('wesanox-element-booking-time', plugin_dir_url(dirname(__FILE__, 1)) . 'scripts/frontend/elements/element-booking-time.js', array('jquery'), filemtime(plugin_dir_path(dirname(__FILE__, 1)) . 'scripts/frontend/elements/element-booking-time.js'),true);
        wp_enqueue_script('wesanox-element-booking-duration', plugin_dir_url(dirname(__FILE__, 1)) . 'scripts/frontend/elements/element-booking-duration.js', array('jquery'), filemtime(plugin_dir_path(dirname(__FILE__, 1)) . 'scripts/frontend/elements/element-booking-duration.js'),true);
        wp_enqueue_script('wesanox-element-booking-item', plugin_dir_url(dirname(__FILE__, 1)) . 'scripts/frontend/elements/element-booking-item.js', array('jquery'), filemtime(plugin_dir_path(dirname(__FILE__, 1)) . 'scripts/frontend/elements/element-booking-item.js'),true);
        wp_enqueue_script('wesanox-element-booking-extras', plugin_dir_url(dirname(__FILE__, 1)) . 'scripts/frontend/elements/element-booking-extras.js', array('jquery'), filemtime(plugin_dir_path(dirname(__FILE__, 1)) . 'scripts/frontend/elements/element-booking-extras.js'),true);

        wp_localize_script('wesanox-booking-frontend', 'ajax_object', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wesanox_booking_nonce')
        ]);

        wp_localize_script('wesanox-element-booking-calender', 'ajax_object_calendar', [
            'ajax_url_calendar' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wesanox_booking_nonce')
        ]);

        wp_localize_script('wesanox-element-booking-time', 'ajax_object_time', [
            'ajax_url_time' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wesanox_booking_nonce')
        ]);

        wp_localize_script('wesanox-element-booking-duration', 'ajax_object_duration', [
            'ajax_url_duration' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wesanox_booking_nonce')
        ]);

        wp_localize_script('wesanox-element-booking-item', 'ajax_object_item', [
            'ajax_url_item' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wesanox_booking_nonce')
        ]);

        wp_localize_script('wesanox-element-booking-extras', 'ajax_object_extras', [
            'ajax_url_extras' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wesanox_booking_nonce')
        ]);

        /**
         * Shortcode Scripts
         */
        wp_enqueue_script('wesanox-shortcode-upsell', plugin_dir_url(dirname(__FILE__, 1)) . 'scripts/frontend/shortcodes/shortcode-upsell.js', array('jquery'), filemtime(plugin_dir_path(dirname(__FILE__, 1)) . 'scripts/frontend/shortcodes/shortcode-upsell.js'),true);

        wp_localize_script('wesanox-shortcode-upsell', 'ajax_object_shortcode_upsell', [
            'ajax_url_shortcode_upsell' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wesanox_booking_nonce')
        ]);
    }
}