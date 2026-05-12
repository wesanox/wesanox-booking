<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Shortcut;

defined('ABSPATH') || exit;

/**
 * Immutable input DTO for a suite (multi-day) booking request.
 * Contains all validation logic — no WordPress dependencies.
 */
final class SuiteBookingRequest
{
    private function __construct(
        public readonly string $checkin,      // Y-m-d
        public readonly string $checkout,     // Y-m-d
        public readonly int    $persons,
        public readonly int    $area_id,      // 0 = any
        public readonly bool   $with_extras,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            checkin:     trim((string) ($data['checkin']     ?? '')),
            checkout:    trim((string) ($data['checkout']    ?? '')),
            persons:     max(0, (int)  ($data['persons']     ?? 0)),
            area_id:     max(0, (int)  ($data['area_id']     ?? 0)),
            with_extras: !empty($data['with_extras']),
        );
    }

    /** @return string[] Validation error messages; empty = valid. */
    public function validate(): array
    {
        $errors = [];

        if ($this->persons < 1) {
            $errors[] = 'Die Personenanzahl muss mindestens 1 sein.';
        }

        $checkin_dt  = \DateTimeImmutable::createFromFormat('Y-m-d', $this->checkin);
        $checkout_dt = \DateTimeImmutable::createFromFormat('Y-m-d', $this->checkout);

        if ($this->checkin === '') {
            $errors[] = 'Bitte wähle ein Anreisedatum.';
        } elseif (!$checkin_dt) {
            $errors[] = 'Das Anreisedatum ist ungültig.';
        }

        if ($this->checkout === '') {
            $errors[] = 'Bitte wähle ein Abreisedatum.';
        } elseif (!$checkout_dt) {
            $errors[] = 'Das Abreisedatum ist ungültig.';
        }

        // Date-range cross-checks (only when both dates are valid).
        if ($checkin_dt && $checkout_dt) {
            $today = new \DateTimeImmutable('today');

            if ($checkin_dt < $today) {
                $errors[] = 'Das Anreisedatum darf nicht in der Vergangenheit liegen.';
            }

            if ($checkout_dt <= $checkin_dt) {
                $errors[] = 'Das Abreisedatum muss nach dem Anreisedatum liegen.';
            }
        }

        return $errors;
    }

    public function isValid(): bool
    {
        return empty($this->validate());
    }

    /** Number of nights between checkin and checkout (0 if dates invalid). */
    public function nights(): int
    {
        $ci = \DateTimeImmutable::createFromFormat('Y-m-d', $this->checkin);
        $co = \DateTimeImmutable::createFromFormat('Y-m-d', $this->checkout);

        if (!$ci || !$co) {
            return 0;
        }

        return (int) $ci->diff($co)->days;
    }
}
