<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Domain\Integration;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Domain\Integration\ExternalBookingMapper;

final class ExternalBookingMapperTest extends TestCase
{
    private ExternalBookingMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ExternalBookingMapper();
    }

    // ── fromRaw ───────────────────────────────────────────────────────────────

    public function testMapsStandardFieldsCorrectly(): void
    {
        $raw = [
            'id'       => 'ext-7',
            'date'     => '2025-06-15',
            'start'    => '14:00',
            'end'      => '17:00',
            'status'   => 'confirmed',
            'customer' => 'Max Mustermann',
        ];

        $b = $this->mapper->fromRaw($raw, 5, 'suite-1');

        self::assertSame('ext-7', $b->externalId);
        self::assertSame(5, $b->areaId);
        self::assertSame('suite-1', $b->externalAreaId);
        self::assertSame('2025-06-15', $b->date);
        self::assertSame('14:00', $b->startTime);
        self::assertSame('17:00', $b->endTime);
        self::assertSame('confirmed', $b->status);
        self::assertSame('Max Mustermann', $b->customerName);
        self::assertSame($raw, $b->rawData);
    }

    public function testAcceptsAlternativeFieldNames(): void
    {
        $raw = [
            'external_id' => 'ext-8',
            'booking_date' => '2025-07-01',
            'start_time'   => '10:00',
            'end_time'     => '12:00',
            'status'       => 'booked',
        ];

        $b = $this->mapper->fromRaw($raw, 3, 'room-2');

        self::assertSame('ext-8', $b->externalId);
        self::assertSame('2025-07-01', $b->date);
        self::assertSame('10:00', $b->startTime);
        self::assertSame('12:00', $b->endTime);
    }

    public function testNormalisesHisTimeToHi(): void
    {
        $raw = [
            'id' => 'x', 'date' => '2025-06-01',
            'start' => '09:00:00', 'end' => '11:00:00', 'status' => 'ok',
        ];

        $b = $this->mapper->fromRaw($raw, 1, 'r');

        self::assertSame('09:00', $b->startTime);
        self::assertSame('11:00', $b->endTime);
    }

    public function testCustomerNameIsNullWhenAbsent(): void
    {
        $raw = ['id' => 'x', 'date' => '2025-06-01', 'start' => '09:00', 'end' => '10:00', 'status' => 'ok'];
        $b   = $this->mapper->fromRaw($raw, 1, 'r');

        self::assertNull($b->customerName);
    }

    // ── fromRawList ───────────────────────────────────────────────────────────

    public function testMapsListAndSkipsMalformedEntries(): void
    {
        $items = [
            ['id' => 'a', 'date' => '2025-06-15', 'start' => '10:00', 'end' => '12:00', 'status' => 'ok'],
            'not-an-array',
            ['id' => 'b', 'date' => '2025-06-16', 'start' => '14:00', 'end' => '16:00', 'status' => 'ok'],
        ];

        $result = $this->mapper->fromRawList($items, 5, 'suite-1');

        self::assertCount(2, $result);
        self::assertSame('a', $result[0]->externalId);
        self::assertSame('b', $result[1]->externalId);
    }

    public function testEmptyListReturnsEmptyArray(): void
    {
        self::assertSame([], $this->mapper->fromRawList([], 1, 'r'));
    }
}
