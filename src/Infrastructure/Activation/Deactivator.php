<?php

declare(strict_types=1);

namespace Wesanox\Booking\Infrastructure\Activation;

defined('ABSPATH') || exit;

final class Deactivator
{
    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
