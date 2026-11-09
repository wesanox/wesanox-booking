<?php

declare(strict_types=1);

namespace Wesanox\Booking\Domain\Booking;

/**
 * Decides whether a booking may be checked out. The Laravel backend is authoritative: its
 * availability answer (including the opening hours of the area) decides. Only when the backend
 * cannot be reached, the locally synchronised opening window of the day is checked, so sales
 * are not blocked by a short backend outage.
 */
final class CheckoutAvailabilityDecision
{
    public const OUTSIDE_OPENING_HOURS = 'outside_opening_hours';

    public const UNAVAILABLE = 'unavailable';

    /**
     * @param mixed $backendAnswer data of the Laravel availability endpoint, null when not reachable
     * @param array<string, mixed>|null $localOpeningWindow result of the local opening window lookup
     */
    public static function violation(
        mixed $backendAnswer,
        ?array $localOpeningWindow,
        string $startTime,
        string $stopTime,
    ): ?string {
        if (is_array($backendAnswer) && array_key_exists('available', $backendAnswer)) {
            if ($backendAnswer['available'] === true) {
                return null;
            }

            return ($backendAnswer['reason'] ?? null) === self::OUTSIDE_OPENING_HOURS
                ? self::OUTSIDE_OPENING_HOURS
                : self::UNAVAILABLE;
        }

        return self::isOutsideLocalWindow($localOpeningWindow, $startTime, $stopTime)
            ? self::OUTSIDE_OPENING_HOURS
            : null;
    }

    /** @param array<string, mixed>|null $window */
    private static function isOutsideLocalWindow(?array $window, string $startTime, string $stopTime): bool
    {
        if ($window === null) {
            return false;
        }

        if (($window['closed'] ?? false) === true) {
            return true;
        }

        $openFrom = self::minutes((string) ($window['opening_from'] ?? ''), false);
        $openTo = self::minutes((string) ($window['opening_to'] ?? ''), true);
        $start = self::minutes($startTime, false);
        $stop = self::minutes($stopTime, true);

        if ($openFrom === null || $openTo === null || $start === null || $stop === null) {
            return false;
        }

        return $start < $openFrom || $stop > $openTo;
    }

    /** Midnight as an upper bound means the end of the selected day. */
    private static function minutes(string $time, bool $upperBound): ?int
    {
        $time = trim($time);

        if ($upperBound && in_array($time, ['00:00', '00:00:00', '24:00', '24:00:00', '23:59:59'], true)) {
            return 1440;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $time, $matches) !== 1) {
            return null;
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
    }
}
