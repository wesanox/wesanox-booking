<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Application\Booking;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Application\Booking\FindNextAvailableSlotService;
use Wesanox\Booking\Service\ServiceGetNextAvailableRoomarts;

final class FindNextAvailableSlotServiceTest extends TestCase
{
    public function testItLimitsSuggestionsToTheSelectedDayAndForwardsTheArea(): void
    {
        $expected = [[
            'date'       => '2028-01-01',
            'start'      => '10:00',
            'stop'       => '12:00',
            'roomart_id' => 1,
        ]];

        $roomartService = $this->createMock(ServiceGetNextAvailableRoomarts::class);
        $roomartService
            ->expects(self::once())
            ->method('wesanox_get_next_available_for_roomart')
            ->with(1, '2028-01-01 10:00:00', 120, 3, 0, 7)
            ->willReturn($expected);

        $service = new FindNextAvailableSlotService($roomartService);

        self::assertSame(
            $expected,
            $service->execute(1, '2028-01-01 10:00:00', 120, 3, 7)
        );
    }
}
