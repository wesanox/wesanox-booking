<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Booking;

use Wesanox\Booking\Service\ServiceGetAvailableRoomarts;

/**
 * Calculates which duration options are available for a given start time.
 *
 * Monotonicity rule: for a fixed start time with a 30-minute buffer applied at both ends,
 * if duration D is unavailable (a next booking falls within the buffer window), then
 * D+1, D+2, … are also unavailable — so we can break on first failure.
 */
final class CalculateAvailableDurationsService
{
    /** @param int[] $roomartIds Room-type IDs to check (any one available is enough). */
    public function __construct(
        private readonly ServiceGetAvailableRoomarts $roomartService,
        private readonly array $roomartIds = [1, 2],
        private readonly int $minHours = 2,
        private readonly int $maxHours = 5,
    ) {}

    /**
     * Return duration options that have at least one available room.
     *
     * @param string $date        Y-m-d
     * @param string $startHi     HH:MM start time
     * @param string $closingHis  Closing time, e.g. '22:00:00' or '23:59:59' (treated as 24:00)
     * @return array<array{hours: int, label: string, end_time: string}>
     */
    public function execute(string $date, string $startHi, string $closingHis): array
    {
        $startTs = strtotime($startHi);

        $closingTs = strtotime($closingHis);
        if ($closingHis === '23:59:59') {
            $closingTs += 1; // treat as midnight / 24:00:00
        }

        $options = [];

        for ($hours = $this->minHours; $hours <= $this->maxHours; $hours++) {
            $endTs = $startTs + $hours * 3600;

            if ($endTs > $closingTs) {
                break; // would exceed closing time
            }

            $startHis = date('H:i:s', $startTs);
            $endHis   = date('H:i:s', $endTs);

            $anyAvailable = false;
            foreach ($this->roomartIds as $roomartId) {
                if ($this->roomartService->wesanox_roomart_available($roomartId, $date, $startHis, $endHis)) {
                    $anyAvailable = true;
                    break;
                }
            }

            if (!$anyAvailable) {
                // Monotonicity: a next booking already blocks this end time + buffer,
                // so larger durations (with later buffer ends) are blocked too.
                break;
            }

            $endHi  = date('H:i', $endTs);
            $label  = $hours === 1 ? '1 Stunde' : "{$hours} Stunden";

            $options[] = [
                'hours'    => $hours,
                'label'    => $label,
                'end_time' => $endHi,
            ];
        }

        return $options;
    }
}
