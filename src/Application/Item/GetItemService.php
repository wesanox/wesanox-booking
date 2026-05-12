<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Item;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Item\Item;
use Wesanox\Booking\Domain\Item\ItemRepositoryInterface;

final class GetItemService
{
    public function __construct(
        private ItemRepositoryInterface $repository,
    ) {
    }

    public function execute(int $id): ?Item
    {
        if ($id <= 0) {
            return null;
        }

        return $this->repository->findById($id);
    }
}
