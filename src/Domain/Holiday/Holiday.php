<?php

declare(strict_types=1);

namespace Wesanox\Booking\Domain\Holiday;

defined('ABSPATH') || exit;

final class Holiday
{
    public function __construct(
        public readonly int     $id,
        public readonly ?string $opening_date,    // Y-m-d
        public readonly ?string $opening_from,    // HH:MM
        public readonly ?string $opening_to,      // HH:MM
        public readonly bool    $opening_closed,
        public readonly bool    $opening_holiday,
        public readonly ?int    $area_id = null,  // null = global (all areas)
    ) {
    }

    public function isClosed(): bool
    {
        return $this->opening_closed;
    }

    public function isHolidayPricing(): bool
    {
        return $this->opening_holiday;
    }

    /** Human-readable date label (d.m.Y) */
    public function dateLabel(): string
    {
        if ($this->opening_date === null) {
            return '—';
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $this->opening_date);

        return $dt ? $dt->format('d.m.Y') : $this->opening_date;
    }
}
