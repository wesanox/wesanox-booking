<?php

declare(strict_types=1);

namespace Wesanox\Booking\Domain\Booking;

/**
 * Finds guests that were entered twice in the checkout (same first + last name,
 * in any order).
 *
 * Two guests with the same name only count as different people when both have
 * an e-mail address and the addresses differ. An empty e-mail never makes a
 * guest unique, because the backend would otherwise merge both into one guest.
 */
final class DuplicateGuestDetector
{
    /**
     * @param array<int, array{firstname?: string, lastname?: string, email?: string}> $guests
     *        Keyed by guest number (1 = booker, 2-4 = companions).
     *
     * @return list<array{0: int, 1: int}> Pairs of guest numbers that are duplicates.
     */
    public function duplicates(array $guests): array
    {
        $normalized = [];

        foreach ($guests as $number => $guest) {
            $name = $this->normalizeName(($guest['firstname'] ?? '') . ' ' . ($guest['lastname'] ?? ''));

            if ($name === '') {
                continue;
            }

            $normalized[(int) $number] = [
                'name'  => $name,
                'email' => $this->normalize($guest['email'] ?? ''),
            ];
        }

        $pairs   = [];
        $numbers = array_keys($normalized);
        $count   = count($numbers);

        for ($a = 0; $a < $count; $a++) {
            for ($b = $a + 1; $b < $count; $b++) {
                $first  = $normalized[$numbers[$a]];
                $second = $normalized[$numbers[$b]];

                if ($first['name'] !== $second['name']) {
                    continue;
                }

                $bothHaveEmail = $first['email'] !== '' && $second['email'] !== '';

                if ($bothHaveEmail && $first['email'] !== $second['email']) {
                    continue;
                }

                $pairs[] = [$numbers[$a], $numbers[$b]];
            }
        }

        return $pairs;
    }

    /**
     * Sorts the name parts so swapped first/last names ("Muster Max") still match.
     */
    private function normalizeName(string $value): string
    {
        $normalized = $this->normalize($value);

        if ($normalized === '') {
            return '';
        }

        $parts = explode(' ', $normalized);
        sort($parts);

        return implode(' ', $parts);
    }

    private function normalize(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return mb_strtolower($value);
    }
}
