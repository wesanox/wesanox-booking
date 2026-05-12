<?php

declare(strict_types=1);

namespace Wesanox\Booking\Domain\ItemCategory;

defined('ABSPATH') || exit;

/**
 * ItemCategory domain entity (immutable DTO).
 * Maps to the wp_wesanox_item_categories table.
 */
final class ItemCategory
{
    public function __construct(
        public readonly int    $id,
        public readonly string $name,
    ) {
    }
}
