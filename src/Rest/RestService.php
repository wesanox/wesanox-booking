<?php

declare(strict_types=1);

namespace Wesanox\Booking\Rest;

defined('ABSPATH') || exit;

use Wesanox\Booking\Rest\Controllers\BookingController;

final class RestService
{
    public function register(): void
    {
        add_action(
            'rest_api_init',
            [$this, 'registerRoutes']
        );
    }

    public function registerRoutes(): void
    {
        $controller = new BookingController();
        $controller->registerRoutes();
    }
}
