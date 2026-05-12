<?php

declare(strict_types=1);

namespace Wesanox\Booking\Frontend;

defined('ABSPATH') || exit;

use Wesanox\Booking\View\Frontend\ShortCodes\ShortCodeBooking;
use Wesanox\Booking\View\Frontend\ShortCodes\ShortCodeCancle;
use Wesanox\Booking\View\Frontend\ShortCodes\ShortCodeSuite;
use Wesanox\Booking\View\Frontend\ShortCodes\ShortCodeTimeslot;
use Wesanox\Booking\View\Frontend\ShortCodes\ShortCodeUpsell;

final class FrontendService
{
    public function register(): void
    {
        new ShortCodeBooking();
        new ShortCodeCancle();
        new ShortCodeUpsell();
        new ShortCodeSuite();
        new ShortCodeTimeslot();

        add_action(
            'wp_enqueue_scripts',
            [$this, 'enqueueAssets']
        );
    }

    public function enqueueAssets(): void
    {
        $plugin_url = plugin_dir_url(dirname(__DIR__));
        $plugin_dir = plugin_dir_path(dirname(__DIR__));

        $this->enqueueFrameworkAssets($plugin_url);
        $this->enqueueMainAssets($plugin_url, $plugin_dir);
        $this->enqueueElementAssets($plugin_url, $plugin_dir);
        $this->enqueueShortcodeAssets($plugin_url, $plugin_dir);
        $this->localizeScripts($plugin_dir);
    }

    private function enqueueFrameworkAssets(string $plugin_url): void
    {
        wp_enqueue_style('wesanox-booking-swiper-css', $plugin_url . 'styles/frameworks/swiper.min.css');
        wp_enqueue_style('wesanox-booking-bootstrap-css', $plugin_url . 'styles/frameworks/bootstrap.css');
        wp_enqueue_style('wesanox-booking-zabuto-css', $plugin_url . 'styles/frameworks/jquery.zabuto_calendar.css');

        wp_enqueue_script('wesanox-booking-swiper-js', $plugin_url . 'scripts/frameworks/swiper.min.js', ['jquery']);
        wp_enqueue_script('wesanox-booking-bootstrap-js', $plugin_url . 'scripts/frameworks/bootstrap.min.js', ['jquery']);
        wp_enqueue_script('wesanox-booking-zabuto-js', $plugin_url . 'scripts/frameworks/jquery.zabuto_calendar.js', ['jquery']);
    }

    private function enqueueMainAssets(string $plugin_url, string $plugin_dir): void
    {
        wp_enqueue_style(
            'wesanox-booking',
            plugins_url('styles/frontend/styles.css', dirname(__DIR__)),
            [],
            filemtime($plugin_dir . 'styles/frontend/styles.css')
        );

        wp_enqueue_script(
            'wesanox-booking-frontend-function',
            $plugin_url . 'scripts/functions/_functions.js',
            ['jquery'],
            filemtime($plugin_dir . 'scripts/functions/_functions.js'),
            true
        );

        wp_enqueue_script(
            'wesanox-booking-frontend',
            $plugin_url . 'scripts/frontend/scripts.js',
            ['jquery'],
            filemtime($plugin_dir . 'scripts/frontend/scripts.js'),
            true
        );
    }

    private function enqueueElementAssets(string $plugin_url, string $plugin_dir): void
    {
        $elements = [
            'wesanox-element-booking-persons'  => 'scripts/frontend/elements/element-booking-persons.js',
            'wesanox-element-booking-calender' => 'scripts/frontend/elements/element-booking-calender.js',
            'wesanox-element-booking-time'     => 'scripts/frontend/elements/element-booking-time.js',
            'wesanox-element-booking-duration' => 'scripts/frontend/elements/element-booking-duration.js',
            'wesanox-element-booking-item'     => 'scripts/frontend/elements/element-booking-item.js',
            'wesanox-element-booking-extras'   => 'scripts/frontend/elements/element-booking-extras.js',
        ];

        foreach ($elements as $handle => $path) {
            wp_enqueue_script(
                $handle,
                $plugin_url . $path,
                ['jquery'],
                filemtime($plugin_dir . $path),
                true
            );
        }
    }

    private function enqueueShortcodeAssets(string $plugin_url, string $plugin_dir): void
    {
        $shortcodes = [
            'wesanox-shortcode-upsell'    => 'scripts/frontend/shortcodes/shortcode-upsell.js',
            'wesanox-shortcode-suite'     => 'scripts/frontend/shortcodes/shortcode-suite.js',
            'wesanox-shortcode-timeslot'  => 'scripts/frontend/shortcodes/shortcode-timeslot.js',
        ];

        foreach ($shortcodes as $handle => $path) {
            wp_enqueue_script(
                $handle,
                $plugin_url . $path,
                ['jquery'],
                filemtime($plugin_dir . $path),
                true
            );
        }
    }

    private function localizeScripts(string $plugin_dir): void
    {
        $nonce = wp_create_nonce('wesanox_booking_nonce');
        $ajax_url = admin_url('admin-ajax.php');

        wp_localize_script('wesanox-booking-frontend', 'ajax_object', [
            'ajax_url' => $ajax_url,
            'nonce'    => $nonce,
        ]);

        $localize = [
            'wesanox-element-booking-calender' => ['ajax_object_calendar', 'ajax_url_calendar'],
            'wesanox-element-booking-time'     => ['ajax_object_time',     'ajax_url_time'],
            'wesanox-element-booking-duration' => ['ajax_object_duration', 'ajax_url_duration'],
            'wesanox-element-booking-item'     => ['ajax_object_item',     'ajax_url_item'],
            'wesanox-element-booking-extras'   => ['ajax_object_extras',   'ajax_url_extras'],
            'wesanox-shortcode-upsell'         => ['ajax_object_shortcode_upsell', 'ajax_url_shortcode_upsell'],
            'wesanox-shortcode-suite'          => ['ajax_object_suite',            'ajax_url_suite'],
            'wesanox-shortcode-timeslot'       => ['ajax_object_timeslot',         'ajax_url_timeslot'],
        ];

        foreach ($localize as $handle => [$object_name, $url_key]) {
            wp_localize_script($handle, $object_name, [
                $url_key => $ajax_url,
                'nonce'  => $nonce,
            ]);
        }
    }
}
