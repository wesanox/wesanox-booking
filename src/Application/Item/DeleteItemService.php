<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Item;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Item\ItemRepositoryInterface;

final class DeleteItemService
{
    public function __construct(
        private ItemRepositoryInterface $repository,
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
