<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Shortcut;

defined('ABSPATH') || exit;

final class SuiteAvailabilityResult
{
    public function __construct(
        public readonly bool $available,
        public readonly int  $available_rooms,
        public readonly int  $nights,
    ) {
    }

    public function toArray(): array
    {
        return [
            'available'       => $this->available,
            'available_rooms' => $this->available_rooms,
            'nights'          => $this->nights,
        ];
    }
}
