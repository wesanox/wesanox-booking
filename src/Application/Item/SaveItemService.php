<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Item;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Item\ItemRepositoryInterface;
use Wesanox\Booking\Support\ValidationException;

final class SaveItemService
{
    public function __construct(
        private ItemRepositoryInterface $repository,
    ) {
    }

    /**
     * Create or update an item.
     *
     * @param ?int    $id               null = create, int = update
     * @param string  $name
     * @param ?int    $area_id          optional area assignment
     * @param ?int    $item_category_id required category assignment
     * @param bool    $inactive
     * @param ?string $inactiv_from     Y-m-d or null
     * @param ?string $inactiv_to       Y-m-d or null
     * @param ?string $inactiv_note
     * @return int Saved item ID
     * @throws ValidationException
     */
    public function execute(
        ?int    $id,
        string  $name,
        ?int    $area_id,
        ?int    $item_category_id,
        bool    $inactive,
        ?string $inactiv_from,
        ?string $inactiv_to,
        ?string $inactiv_note,
    ): int {
        $errors = [];

        if (trim($name) === '') {
            $errors[] = 'Der Name ist erforderlich.';
        }

        if ($item_category_id === null || $item_category_id <= 0) {
            $errors[] = 'Eine Kategorie ist erforderlich.';
        }

        // Validate date format if provided
        if ($inactiv_from !== null && !$this->isValidDate($inactiv_from)) {
            $errors[] = '"Inaktiv von" enthält kein gültiges Datum (erwartet: JJJJ-MM-TT).';
        }

        if ($inactiv_to !== null && !$this->isValidDate($inactiv_to)) {
            $errors[] = '"Inaktiv bis" enthält kein gültiges Datum (erwartet: JJJJ-MM-TT).';
        }

        // Validate period order
        if ($inactiv_from !== null && $inactiv_to !== null
            && $this->isValidDate($inactiv_from) && $this->isValidDate($inactiv_to)
            && $inactiv_to < $inactiv_from
        ) {
            $errors[] = '"Inaktiv bis" darf nicht vor "Inaktiv von" liegen.';
        }

        if (!empty($errors)) {
            throw ValidationException::withErrors($errors);
        }

        $data = [
            'name'             => $name,
            'area_id'          => $area_id,
            'item_category_id' => $item_category_id,
            'inactive'         => $inactive,
            'inactiv_from'     => $inactiv_from,
            'inactiv_to'       => $inactiv_to,
            'inactiv_note'     => $inactiv_note,
        ];

        if ($id === null) {
            return $this->repository->create($data);
        }

        $this->repository->update($id, $data);

        return $id;
    }

    private function isValidDate(string $date): bool
    {
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return $d !== false && $d->format('Y-m-d') === $date;
    }
}
