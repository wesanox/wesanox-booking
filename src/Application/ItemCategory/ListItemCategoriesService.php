<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\ItemCategory;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\ItemCategory\ItemCategory;
use Wesanox\Booking\Domain\ItemCategory\ItemCategoryRepositoryInterface;

final class ListItemCategoriesService
{
    public function __construct(
        private ItemCategoryRepositoryInterface $repository,
    ) {
    }

    /** @return ItemCategory[] */
    public function execute(): array
    {
        return $this->repository->findAll();
    }
}
