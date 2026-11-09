<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Domain\Area;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Domain\Area\Area;

final class AreaTest extends TestCase
{
    // --- openingData() ---

    public function testItReturnsEmptyOpeningForNullJson(): void
    {
        $area   = new Area(1, 'Spa', null, null);
        $result = $area->openingData();

        foreach (Area::WEEKDAYS as $day) {
            self::assertArrayHasKey($day, $result);
            self::assertFalse($result[$day]['enabled']);
            self::assertNull($result[$day]['from']);
            self::assertNull($result[$day]['to']);
        }
    }

    public function testItReturnsEmptyOpeningForMalformedJson(): void
    {
        $area   = new Area(1, 'Spa', 'not-json', null);
        $result = $area->openingData();

        self::assertFalse($result['monday']['enabled']);
    }

    public function testItDecodesEnabledDay(): void
    {
        $json = json_encode([
            'monday' => ['enabled' => true, 'from' => '09:00', 'to' => '18:00'],
        ]);
        $area   = new Area(1, 'Spa', $json, null);
        $result = $area->openingData();

        self::assertTrue($result['monday']['enabled']);
        self::assertSame('09:00', $result['monday']['from']);
        self::assertSame('18:00', $result['monday']['to']);
    }

    public function testItDecodesDisabledDay(): void
    {
        $json = json_encode([
            'saturday' => ['enabled' => false, 'from' => null, 'to' => null],
        ]);
        $area   = new Area(1, 'Spa', $json, null);
        $result = $area->openingData();

        self::assertFalse($result['saturday']['enabled']);
        self::assertNull($result['saturday']['from']);
        self::assertNull($result['saturday']['to']);
    }

    public function testItContainsAllSevenWeekdays(): void
    {
        $area   = new Area(1, 'Spa', null, null);
        $result = $area->openingData();

        self::assertCount(7, $result);
        foreach (Area::WEEKDAYS as $day) {
            self::assertArrayHasKey($day, $result);
        }
    }

    // --- timeSettingsData() ---

    public function testItReturnsDefaultTimeSettings(): void
    {
        $area   = new Area(1, 'Spa', null, null);
        $result = $area->timeSettingsData();

        self::assertSame('1',   $result['area_time_separator']);
        self::assertSame('30',  $result['area_time_sheet']);
        self::assertSame('60',  $result['area_time_min']);
        self::assertSame('240', $result['area_time_max']);
    }

    public function testItMergesStoredTimeSettings(): void
    {
        $json = json_encode(['area_time_separator' => '2', 'area_time_min' => '120']);
        $area   = new Area(1, 'Spa', null, $json);
        $result = $area->timeSettingsData();

        self::assertSame('2',   $result['area_time_separator']);
        self::assertSame('120', $result['area_time_min']);
        // Remaining keys keep defaults.
        self::assertSame('30',  $result['area_time_sheet']);
        self::assertSame('240', $result['area_time_max']);
    }

    public function testItReturnsDefaultsForMalformedTimeSettingsJson(): void
    {
        $area   = new Area(1, 'Spa', null, '{broken}');
        $result = $area->timeSettingsData();

        self::assertSame('1', $result['area_time_separator']);
    }
}
