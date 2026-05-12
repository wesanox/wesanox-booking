<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Holiday;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Holiday\HolidayRepositoryInterface;
use Wesanox\Booking\Support\ValidationException;

final class SaveHolidayService
{
    public function __construct(
        private HolidayRepositoryInterface $repository,
    ) {
    }

    /**
     * Create or update a holiday entry.
     *
     * @param ?int    $id             null = create, int = update
     * @param string  $opening_date   Y-m-d
     * @param ?string $opening_from   HH:MM or null
     * @param ?string $opening_to     HH:MM or null
     * @param bool    $opening_closed
     * @param bool    $opening_holiday
     * @param ?int    $area_id        null = global (all areas), int = area-specific
     * @return int Saved record ID
     * @throws ValidationException
     */
    public function execute(
        ?int    $id,
        string  $opening_date,
        ?string $opening_from,
        ?string $opening_to,
        bool    $opening_closed,
        bool    $opening_holiday,
        ?int    $area_id = null,
    ): int {
        $errors = [];

        // --- Date ---
        if (trim($opening_date) === '') {
            $errors[] = 'Das Datum ist erforderlich.';
        } elseif (!\DateTimeImmutable::createFromFormat('Y-m-d', $opening_date)) {
            $errors[] = 'Das Datum muss im Format JJJJ-MM-TT angegeben werden.';
        } else {
            // Unique-date check per area scope (area-specific + global each unique per date).
            $existing = $this->repository->findByDate($opening_date, $area_id);
            if ($existing !== null && $existing->id !== $id) {
                $errors[] = 'Für dieses Datum existiert bereits ein Eintrag.';
            }
        }

        // --- Opening times (only validated when not closed and a value is given) ---
        if (!$opening_closed) {
            if ($opening_from !== null && $opening_from !== '' && !$this->isValidTime($opening_from)) {
                $errors[] = 'Öffnungszeit "Von" ist ungültig (Format HH:MM erwartet).';
            }

            if ($opening_to !== null && $opening_to !== '' && !$this->isValidTime($opening_to)) {
                $errors[] = 'Öffnungszeit "Bis" ist ungültig (Format HH:MM erwartet).';
            }

            if (
                $opening_from !== null && $opening_from !== '' &&
                $opening_to   !== null && $opening_to   !== '' &&
                $this->isValidTime($opening_from) && $this->isValidTime($opening_to)
            ) {
                $to_cmp = ($opening_to === '00:00') ? '24:00' : $opening_to;
                if ($to_cmp <= $opening_from) {
                    $errors[] = 'Öffnungszeit "Bis" muss nach "Von" liegen.';
                }
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withErrors($errors);
        }

        $data = [
            'opening_date'    => $opening_date,
            'opening_from'    => ($opening_closed || $opening_from === '') ? null : $opening_from,
            'opening_to'      => ($opening_closed || $opening_to   === '') ? null : $opening_to,
            'opening_closed'  => $opening_closed  ? 1 : 0,
            'opening_holiday' => $opening_holiday ? 1 : 0,
            'area_id'         => $area_id,
        ];

        if ($id === null) {
            return $this->repository->create($data);
        }

        $this->repository->update($id, $data);

        return $id;
    }

    private function isValidTime(string $time): bool
    {
        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time);
    }
}
