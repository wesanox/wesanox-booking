<?php

declare(strict_types=1);

namespace Wesanox\Booking\Domain\Integration;

defined('ABSPATH') || exit;

/**
 * Validates an ExternalBooking before it is persisted or used to block time.
 * Pure domain logic — no framework dependencies.
 */
final class ExternalBookingValidator
{
    /**
     * @return string[]  List of validation error messages (empty = valid)
     */
    public function validate(ExternalBooking $booking): array
    {
        $errors = [];

        if (trim($booking->externalId) === '') {
            $errors[] = 'externalId darf nicht leer sein.';
        }

        if ($booking->areaId <= 0) {
            $errors[] = 'areaId muss eine positive Ganzzahl sein.';
        }

        if (trim($booking->externalAreaId) === '') {
            $errors[] = 'externalAreaId darf nicht leer sein.';
        }

        if (!$this->isValidDate($booking->date)) {
            $errors[] = "Ungültiges Datum: \"{$booking->date}\". Erwartet: Y-m-d.";
        }

        if (!$this->isValidTime($booking->startTime)) {
            $errors[] = "Ungültige Startzeit: \"{$booking->startTime}\". Erwartet: H:i.";
        }

        if (!$this->isValidTime($booking->endTime)) {
            $errors[] = "Ungültige Endzeit: \"{$booking->endTime}\". Erwartet: H:i.";
        }

        if (empty($errors) && $booking->startTime >= $booking->endTime) {
            $errors[] = 'Startzeit muss vor der Endzeit liegen.';
        }

        if (trim($booking->status) === '') {
            $errors[] = 'Status darf nicht leer sein.';
        }

        return $errors;
    }

    public function isValid(ExternalBooking $booking): bool
    {
        return empty($this->validate($booking));
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function isValidDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        $parts = explode('-', $date);
        return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
    }

    private function isValidTime(string $time): bool
    {
        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time);
    }
}
