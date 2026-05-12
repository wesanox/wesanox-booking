<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\ItemCategory;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\ItemCategory\ItemCategoryRepositoryInterface;

final class DeleteItemCategoryService
{
    public function __construct(
        private ItemCategoryRepositoryInterface $repository,
    ) {
    }

    public function execute(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        return $this->repository->delete($id);
    }
}
