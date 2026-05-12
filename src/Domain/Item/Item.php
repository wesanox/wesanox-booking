<?php

declare(strict_types=1);

namespace Wesanox\Booking\Domain\Item;

defined('ABSPATH') || exit;

/**
 * Item domain entity (immutable DTO).
 * Maps to the wp_wesanox_items table.
 */
final class Item
{
    public function __construct(
        public readonly int     $id,
        public readonly string  $name,
        public readonly ?int    $area_id,
        public readonly ?int    $item_category_id,
        public readonly bool    $inactive,
        public readonly ?string $inactiv_from,
        public readonly ?string $inactiv_to,
        public readonly ?string $inactiv_note,
        public readonly string  $area_name          = '',
        public readonly string  $category_name      = '',
    ) {
    }

    public function isActive(): bool
    {
        return !$this->inactive;
    }

    /**
     * Whether the item is currently within an inactive period.
     * Requires both dates to be set and inactive flag to be true.
     */
    public function isCurrentlyInactive(): bool
    {
        if (!$this->inactive) {
            return false;
        }

        if ($this->inactiv_from === null || $this->inactiv_to === null) {
            return true; // inactive without period = always inactive
        }

        $today = date('Y-m-d');

        return $today >= $this->inactiv_from && $today <= $this->inactiv_to;
    }
}
