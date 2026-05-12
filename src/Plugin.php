<?php

declare(strict_types=1);

namespace Wesanox\Booking;

defined('ABSPATH') || exit;

use Wesanox\Booking\Admin\AdminService;
use Wesanox\Booking\Frontend\FrontendService;
use Wesanox\Booking\Rest\RestService;
use Wesanox\Booking\Infrastructure\Activation\Activator;
use Wesanox\Booking\Infrastructure\Activation\Deactivator;
use Wesanox\Booking\Boot\Booking\HandlerBooking;
use Wesanox\Booking\Woocommerce\WoocommerceProductHandler;

final class Plugin
{
    public static function init(): void
    {
        $plugin = new self();
        $plugin->registerServices();
    }

    public static function activate(): void
    {
        Activator::activate();
    }

    public static function deactivate(): void
    {
        Deactivator::deactivate();
    }

    private function registerServices(): void
    {
        // Legacy WooCommerce integration (constructor registers hooks)
        new HandlerBooking();
        new WoocommerceProductHandler();

        $services = [
            new AdminService(),
            new FrontendService(),
            new RestService(),
        ];

        foreach ($services as $service) {
            $service->register();
        }
    }
}
