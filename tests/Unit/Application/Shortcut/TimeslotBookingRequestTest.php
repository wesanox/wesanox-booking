<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Application\Shortcut;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Application\Shortcut\TimeslotBookingRequest;

final class TimeslotBookingRequestTest extends TestCase
{
    private function tomorrow(): string
    {
        return (new \DateTimeImmutable('+1 day'))->format('Y-m-d');
    }

    private function yesterday(): string
    {
        return (new \DateTimeImmutable('-1 day'))->format('Y-m-d');
    }

    // --- date ---

    public function testItThrowsWhenDateIsEmpty(): void
    {
        $req = TimeslotBookingRequest::fromArray(['date' => '', 'from' => '09:00', 'to' => '11:00']);
        self::assertStringContainsString('Datum', implode(' ', $req->validate()));
    }

    public function testItThrowsWhenDateIsInvalid(): void
    {
        $req = TimeslotBookingRequest::fromArray(['date' => '31-12-2025', 'from' => '09:00', 'to' => '11:00']);
        self::assertNotEmpty($req->validate());
    }

    public function testItThrowsWhenDateIsInThePast(): void
    {
        $req = TimeslotBookingRequest::fromArray([
            'date' => $this->yesterday(),
            'from' => '09:00',
            'to'   => '11:00',
        ]);
        self::assertStringContainsString('Vergangenheit', implode(' ', $req->validate()));
    }

    // --- from/to times ---

    public function testItThrowsWhenFromIsEmpty(): void
    {
        $req = TimeslotBookingRequest::fromArray(['date' => $this->tomorrow(), 'from' => '', 'to' => '11:00']);
        self::assertStringContainsString('Startzeit', implode(' ', $req->validate()));
    }

    public function testItThrowsWhenToIsEmpty(): void
    {
        $req = TimeslotBookingRequest::fromArray(['date' => $this->tomorrow(), 'from' => '09:00', 'to' => '']);
        self::assertStringContainsString('Endzeit', implode(' ', $req->validate()));
    }

    public function testItThrowsWhenFromIsInvalidFormat(): void
    {
        $req = TimeslotBookingRequest::fromArray(['date' => $this->tomorrow(), 'from' => '9:00', 'to' => '11:00']);
        self::assertNotEmpty($req->validate());
    }

    public function testItThrowsWhenToIsBeforeFrom(): void
    {
        $req = TimeslotBookingRequest::fromArray(['date' => $this->tomorrow(), 'from' => '18:00', 'to' => '09:00']);
        self::assertStringContainsString('Endzeit', implode(' ', $req->validate()));
    }

    public function testItThrowsWhenToEqualsFrom(): void
    {
        $req = TimeslotBookingRequest::fromArray(['date' => $this->tomorrow(), 'from' => '09:00', 'to' => '09:00']);
        self::assertNotEmpty($req->validate());
    }

    public function testItThrowsWhenDurationIsLessThan60Minutes(): void
    {
        $req = TimeslotBookingRequest::fromArray(['date' => $this->tomorrow(), 'from' => '09:00', 'to' => '09:30']);
        self::assertStringContainsString('Minute', implode(' ', $req->validate()));
    }

    // --- midnight ---

    public function testItAcceptsMidnightAsClosingTime(): void
    {
        $req = TimeslotBookingRequest::fromArray([
            'date' => $this->tomorrow(),
            'from' => '22:00',
            'to'   => '00:00',
        ]);

        self::assertEmpty($req->validate(), implode(', ', $req->validate()));
    }

    // --- valid ---

    public function testItIsValidWithCorrectInput(): void
    {
        $req = TimeslotBookingRequest::fromArray([
            'date'    => $this->tomorrow(),
            'from'    => '10:00',
            'to'      => '13:00',
            'area_id' => 2,
        ]);

        self::assertEmpty($req->validate());
        self::assertTrue($req->isValid());
        self::assertSame(2, $req->area_id);
    }

    // --- durationMinutes() ---

    public function testDurationMinutes(): void
    {
        $req = TimeslotBookingRequest::fromArray([
            'date' => $this->tomorrow(),
            'from' => '10:00',
            'to'   => '12:30',
        ]);
        self::assertSame(150, $req->durationMinutes());
    }

    public function testDurationMinutesWithMidnight(): void
    {
        $req = TimeslotBookingRequest::fromArray([
            'date' => $this->tomorrow(),
            'from' => '22:00',
            'to'   => '00:00',
        ]);
        self::assertSame(120, $req->durationMinutes());
    }

    public function testDurationMinutesIsZeroForInvalidTimes(): void
    {
        $req = TimeslotBookingRequest::fromArray(['date' => $this->tomorrow(), 'from' => 'bad', 'to' => 'bad']);
        self::assertSame(0, $req->durationMinutes());
    }

    // --- fromArray defaults ---

    public function testFromArrayAppliesDefaults(): void
    {
        $req = TimeslotBookingRequest::fromArray([]);
        self::assertSame(0,  $req->area_id);
        self::assertSame('', $req->date);
    }
}
