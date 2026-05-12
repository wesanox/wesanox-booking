<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\ItemCategory;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\ItemCategory\ItemCategoryRepositoryInterface;
use Wesanox\Booking\Support\ValidationException;

final class SaveItemCategoryService
{
    public function __construct(
        private ItemCategoryRepositoryInterface $repository,
    ) {
    }

    /**
     * Create or update an item category.
     *
     * @param ?int   $id   null = create, int = update
     * @param string $name
     * @return int Saved category ID
     * @throws ValidationException
     */
    public function execute(?int $id, string $name): int
    {
        if (trim($name) === '') {
            throw ValidationException::withErrors(['Der Name ist erforderlich.']);
        }

        $data = ['name' => $name];

        if ($id === null) {
            return $this->repository->create($data);
        }

        $this->repository->update($id, $data);

        return $id;
    }
}
