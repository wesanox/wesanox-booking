<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Booking;

use Wesanox\Booking\Service\ServiceGetNextAvailableRoomarts;

/**
 * Application-layer facade around ServiceGetNextAvailableRoomarts.
 *
 * Returns the next N available time slots for a given room type
 * starting from a desired date/time.
 */
final class FindNextAvailableSlotService
{
    public function __construct(
        private readonly ServiceGetNextAvailableRoomarts $service,
    ) {}

    /**
     * Find next available slots for a room type.
     *
     * @param int    $roomartId        Room-type ID (e.g. 1 or 2)
     * @param string $fromYmdHis       Search from this date/time (Y-m-d H:i:s)
     * @param int    $durationMinutes  Desired duration in minutes
     * @param int    $limit            Maximum number of results (default 3)
     * @return array<array{date: string, start: string, stop: string, roomart_id: int}>
     */
    public function execute(
        int $roomartId,
        string $fromYmdHis,
        int $durationMinutes,
        int $limit = 3,
    ): array {
        return $this->service->wesanox_get_next_available_for_roomart(
            $roomartId,
            $fromYmdHis,
            $durationMinutes,
            $limit,
        );
    }
}
