<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Application\Booking;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Application\Booking\CalculateAvailableDurationsService;
use Wesanox\Booking\Service\ServiceGetAvailableRoomarts;

final class CalculateAvailableDurationsServiceTest extends TestCase
{
    // ── helpers ────────────────────────────────────────────────────────────

    /** Returns a mock that always returns $available for any room/time. */
    private function makeRoomartService(bool $available): ServiceGetAvailableRoomarts
    {
        $mock = $this->createMock(ServiceGetAvailableRoomarts::class);
        $mock->method('wesanox_roomart_available')->willReturn($available);
        return $mock;
    }

    /**
     * Returns a mock where availability depends on the end time:
     * available when end time <= $blockedFrom, unavailable otherwise.
     *
     * @param string $blockedFrom 'H:i:s' — first end time that causes a conflict
     */
    private function makeRoomartServiceBlockedFrom(string $blockedFrom): ServiceGetAvailableRoomarts
    {
        $mock = $this->createMock(ServiceGetAvailableRoomarts::class);
        $mock->method('wesanox_roomart_available')
             ->willReturnCallback(function (int $roomartId, string $date, string $start, string $end) use ($blockedFrom): bool {
                 return strtotime($end) <= strtotime($blockedFrom);
             });
        return $mock;
    }

    // ── all rooms available ─────────────────────────────────────────────

    public function testReturnsAllOptionsWhenAllRoomsAvailableAndTimePermits(): void
    {
        $svc = new CalculateAvailableDurationsService($this->makeRoomartService(true));

        $options = $svc->execute('2025-06-15', '14:00', '23:59:59');

        // minHours=2, maxHours=5 → options for 2,3,4,5 = 4 entries
        self::assertCount(4, $options);
    }

    public function testReturnsOptionsUpToClosingTime(): void
    {
        $svc = new CalculateAvailableDurationsService($this->makeRoomartService(true));

        // Start 20:00, closing 22:00 → only 2h fits (20:00+2h = 22:00 ≤ 22:00)
        $options = $svc->execute('2025-06-15', '20:00', '22:00:00');

        self::assertCount(1, $options);
        self::assertSame(2, $options[0]['hours']);
        self::assertSame('22:00', $options[0]['end_time']);
    }

    public function testReturnsEmptyWhenStartAtOrAfterClosing(): void
    {
        $svc = new CalculateAvailableDurationsService($this->makeRoomartService(true));

        $options = $svc->execute('2025-06-15', '22:00', '22:00:00');

        self::assertEmpty($options);
    }

    // ── Szenario 1: room blocked from 18:00 onwards ────────────────────

    public function testScenario1MaxDurationTwoHoursWhenBlockedAt1800(): void
    {
        // Booking blocks from 18:00 (buffer_end = 18:30), so 2h (14:00-16:00, buf=16:30) is fine
        // but 3h (14:00-17:00, buf=17:30) is blocked by a booking starting at 17:30–... let's
        // simulate it as: blocked if end_time > 16:00:00

        $mock = $this->createMock(ServiceGetAvailableRoomarts::class);
        // Available only when end time ≤ 16:00:00 (i.e. 2h from 14:00)
        $mock->method('wesanox_roomart_available')
             ->willReturnCallback(fn ($rId, $date, $start, $end): bool => strtotime($end) <= strtotime('16:00:00'));

        $svc = new CalculateAvailableDurationsService($mock);
        $options = $svc->execute('2025-06-15', '14:00', '22:00:00');

        self::assertCount(1, $options);
        self::assertSame(2, $options[0]['hours']);
        self::assertSame('2 Stunden', $options[0]['label']);
    }

    // ── no rooms available ──────────────────────────────────────────────

    public function testReturnsEmptyWhenNoRoomAvailableForAnyDuration(): void
    {
        $svc = new CalculateAvailableDurationsService($this->makeRoomartService(false));

        $options = $svc->execute('2025-06-15', '14:00', '22:00:00');

        self::assertEmpty($options);
    }

    // ── room 1 unavailable but room 2 available ─────────────────────────

    public function testUsesSecondRoomartWhenFirstIsFullyBooked(): void
    {
        $mock = $this->createMock(ServiceGetAvailableRoomarts::class);
        $mock->method('wesanox_roomart_available')
             ->willReturnCallback(fn (int $roomartId): bool => $roomartId === 2);

        $svc = new CalculateAvailableDurationsService($mock, [1, 2]);
        $options = $svc->execute('2025-06-15', '14:00', '22:00:00');

        self::assertNotEmpty($options, 'Room 2 is available → options must be returned');
    }

    // ── midnight closing (23:59:59) ─────────────────────────────────────

    public function testMidnightClosingAllows2200StartFor2Hours(): void
    {
        $svc = new CalculateAvailableDurationsService($this->makeRoomartService(true));

        $options = $svc->execute('2025-06-15', '22:00', '23:59:59');

        // 22:00 + 2h = 24:00 ≤ midnight → should appear
        $hours = array_column($options, 'hours');
        self::assertContains(2, $hours);
        // 22:00 + 3h = 25:00 → exceeds midnight → must not appear
        self::assertNotContains(3, $hours);
    }

    // ── labels ──────────────────────────────────────────────────────────

    public function testLabelsAreCorrect(): void
    {
        $svc = new CalculateAvailableDurationsService($this->makeRoomartService(true));

        $options = $svc->execute('2025-06-15', '14:00', '22:00:00');

        $map = array_column($options, 'label', 'hours');

        // minHours default is 2
        self::assertSame('2 Stunden', $map[2]);
        self::assertSame('3 Stunden', $map[3]);
        self::assertSame('4 Stunden', $map[4]);
        self::assertSame('5 Stunden', $map[5]);
    }

    // ── partial availability (monotonicity) ─────────────────────────────

    public function testStopsAddingOptionsAfterFirstUnavailableDuration(): void
    {
        // 2h available, 3h not → 4h and 5h must also be absent (monotonicity)
        $mock = $this->createMock(ServiceGetAvailableRoomarts::class);
        $mock->method('wesanox_roomart_available')
             ->willReturnCallback(fn ($rId, $date, $start, $end): bool =>
                 strtotime($end) <= strtotime('16:00:00')
             );

        $svc = new CalculateAvailableDurationsService($mock);
        // Start 14:00; 2h=16:00 OK; 3h=17:00 blocked → stop
        $options = $svc->execute('2025-06-15', '14:00', '22:00:00');

        $hours = array_column($options, 'hours');
        self::assertContains(2, $hours);
        self::assertNotContains(3, $hours);
        self::assertNotContains(4, $hours);
        self::assertNotContains(5, $hours);
    }
}
