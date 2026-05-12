<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\ItemCategory;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\ItemCategory\ItemCategory;
use Wesanox\Booking\Domain\ItemCategory\ItemCategoryRepositoryInterface;

final class GetItemCategoryService
{
    public function __construct(
        private ItemCategoryRepositoryInterface $repository,
    ) {
    }

    public function execute(int $id): ?ItemCategory
    {
        if ($id <= 0) {
            return null;
        }

        return $this->repository->findById($id);
    }
}
