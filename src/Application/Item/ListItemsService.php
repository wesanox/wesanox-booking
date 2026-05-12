<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Item;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Item\Item;
use Wesanox\Booking\Domain\Item\ItemRepositoryInterface;

final class ListItemsService
{
    public function __construct(
        private ItemRepositoryInterface $repository,
    ) {
    }

    /**
     * @param int|null  $area_id     null = all areas
     * @param int|null  $category_id null = all categories
     * @param bool|null $inactive    null = all, true = only inactive, false = only active
     * @return Item[]
     */
    public function execute(
        ?int  $area_id     = null,
        ?int  $category_id = null,
        ?bool $inactive    = null,
    ): array {
        return $this->repository->findAll($area_id, $category_id, $inactive);
    }
}
