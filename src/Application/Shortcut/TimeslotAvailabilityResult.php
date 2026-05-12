<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Shortcut;

defined('ABSPATH') || exit;

final class TimeslotAvailabilityResult
{
    public function __construct(
        public readonly bool   $available,
        public readonly bool   $area_open,
        public readonly int    $available_rooms,
        public readonly string $opening_from,  // HH:MM or ''
        public readonly string $opening_to,    // HH:MM or ''
    ) {
    }

    public function toArray(): array
    {
        return [
            'available'       => $this->available,
            'area_open'       => $this->area_open,
            'available_rooms' => $this->available_rooms,
            'opening_from'    => $this->opening_from,
            'opening_to'      => $this->opening_to,
        ];
    }
}
