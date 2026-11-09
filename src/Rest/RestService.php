<?php

declare(strict_types=1);

namespace Wesanox\Booking\Rest;

defined('ABSPATH') || exit;

use Wesanox\Booking\Rest\Controllers\BookingController;
use Wesanox\Booking\Rest\Controllers\ItemStatusController;
use Wesanox\Booking\Rest\Controllers\OpeningHoursController;

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

        $openingHoursController = new OpeningHoursController();
        $openingHoursController->registerRoutes();

        $itemStatusController = new ItemStatusController();
        $itemStatusController->registerRoutes();
    }
}
