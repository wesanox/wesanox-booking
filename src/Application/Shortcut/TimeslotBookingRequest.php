<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Shortcut;

defined('ABSPATH') || exit;

/**
 * Immutable input DTO for a timeslot (same-day, hourly) booking request.
 * Contains all validation logic — no WordPress dependencies.
 */
final class TimeslotBookingRequest
{
    private const MIN_DURATION_MINUTES = 60;

    private function __construct(
        public readonly string $date,     // Y-m-d
        public readonly string $from,     // HH:MM
        public readonly string $to,       // HH:MM  (00:00 = midnight / end-of-day)
        public readonly int    $area_id,  // 0 = any
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            date:    trim((string) ($data['date']    ?? '')),
            from:    trim((string) ($data['from']    ?? '')),
            to:      trim((string) ($data['to']      ?? '')),
            area_id: max(0, (int)  ($data['area_id'] ?? 0)),
        );
    }

    /** @return string[] Validation error messages; empty = valid. */
    public function validate(): array
    {
        $errors = [];

        // --- Date ---
        if ($this->date === '') {
            $errors[] = 'Bitte wähle ein Datum.';
        } elseif (!\DateTimeImmutable::createFromFormat('Y-m-d', $this->date)) {
            $errors[] = 'Das Datum ist ungültig.';
        } else {
            $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
            if ($this->date < $today) {
                $errors[] = 'Das Datum darf nicht in der Vergangenheit liegen.';
            }
        }

        // --- Times ---
        $from_valid = $this->isValidTime($this->from);
        $to_valid   = $this->isValidTime($this->to);

        if ($this->from === '') {
            $errors[] = 'Bitte wähle eine Startzeit.';
        } elseif (!$from_valid) {
            $errors[] = 'Die Startzeit ist ungültig (Format HH:MM erwartet).';
        }

        if ($this->to === '') {
            $errors[] = 'Bitte wähle eine Endzeit.';
        } elseif (!$to_valid) {
            $errors[] = 'Die Endzeit ist ungültig (Format HH:MM erwartet).';
        }

        // Cross-checks (only when both times are valid).
        if ($from_valid && $to_valid) {
            // 00:00 = midnight / end-of-day → treat as 24:00 for comparison.
            $to_cmp = ($this->to === '00:00') ? '24:00' : $this->to;

            if ($to_cmp <= $this->from) {
                $errors[] = 'Die Endzeit muss nach der Startzeit liegen.';
            } else {
                $diff = $this->durationMinutes();
                if ($diff < self::MIN_DURATION_MINUTES) {
                    $errors[] = sprintf(
                        'Der Zeitraum muss mindestens %d Minute(n) betragen.',
                        self::MIN_DURATION_MINUTES
                    );
                }
            }
        }

        return $errors;
    }

    public function isValid(): bool
    {
        return empty($this->validate());
    }

    /** Duration between from and to in minutes (midnight = 24:00). */
    public function durationMinutes(): int
    {
        if (!$this->isValidTime($this->from) || !$this->isValidTime($this->to)) {
            return 0;
        }

        [$fh, $fm] = array_map('intval', explode(':', $this->from));
        $to_str     = ($this->to === '00:00') ? '24:00' : $this->to;
        [$th, $tm] = array_map('intval', explode(':', $to_str));

        return ($th * 60 + $tm) - ($fh * 60 + $fm);
    }

    private function isValidTime(string $time): bool
    {
        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time);
    }
}
