<?php

declare(strict_types=1);

namespace Wesanox\Booking\Domain\Integration;

defined('ABSPATH') || exit;

/**
 * Maps raw API response arrays to ExternalBooking domain objects.
 *
 * The field names follow the convention of the wesanox external API.
 * Adjust the key mappings here if the remote API schema changes.
 */
final class ExternalBookingMapper
{
    /**
     * Map a single raw API payload to an ExternalBooking.
     *
     * Accepted field names (case-insensitive keys, snake_case):
     *   id / external_id       → externalId
     *   area_id / resource_id  → externalAreaId
     *   date / booking_date    → date
     *   start / start_time     → startTime
     *   end / end_time         → endTime
     *   status                 → status
     *   customer / name        → customerName
     *   notes / comment        → notes
     *
     * @param array<string, mixed> $raw
     * @param int    $localAreaId    The local wesanox area ID (not in raw payload)
     * @param string $externalAreaId The remote area/resource ID
     * @throws \InvalidArgumentException When required fields are missing
     */
    public function fromRaw(array $raw, int $localAreaId, string $externalAreaId): ExternalBooking
    {
        $externalId  = (string) ($raw['id'] ?? $raw['external_id'] ?? '');
        $date        = (string) ($raw['date'] ?? $raw['booking_date'] ?? '');
        $startTime   = $this->normalizeTime((string) ($raw['start'] ?? $raw['start_time'] ?? ''));
        $endTime     = $this->normalizeTime((string) ($raw['end']   ?? $raw['end_time']   ?? ''));
        $status      = (string) ($raw['status'] ?? '');
        $name        = (string) ($raw['customer'] ?? $raw['name'] ?? '') ?: null;
        $notes       = (string) ($raw['notes'] ?? $raw['comment'] ?? '') ?: null;

        return new ExternalBooking(
            externalId:     $externalId,
            areaId:         $localAreaId,
            externalAreaId: $externalAreaId,
            date:           $date,
            startTime:      $startTime,
            endTime:        $endTime,
            status:         $status,
            customerName:   $name,
            notes:          $notes,
            rawData:        $raw,
        );
    }

    /**
     * Map an array of raw payloads.
     *
     * @param  array<array<string, mixed>> $items
     * @return ExternalBooking[]
     */
    public function fromRawList(array $items, int $localAreaId, string $externalAreaId): array
    {
        $result = [];
        foreach ($items as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            try {
                $result[] = $this->fromRaw($raw, $localAreaId, $externalAreaId);
            } catch (\Throwable) {
                // Skip malformed entries silently.
            }
        }
        return $result;
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Normalise a time string to H:i — accepts H:i:s, H:i, and timestamps.
     */
    private function normalizeTime(string $raw): string
    {
        if ($raw === '') {
            return '';
        }
        // H:i:s → H:i
        if (preg_match('/^(\d{2}:\d{2}):\d{2}$/', $raw, $m)) {
            return $m[1];
        }
        // Already H:i
        if (preg_match('/^\d{2}:\d{2}$/', $raw)) {
            return $raw;
        }
        // Try strtotime as last resort
        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('H:i', $ts);
        }
        return $raw;
    }
}
