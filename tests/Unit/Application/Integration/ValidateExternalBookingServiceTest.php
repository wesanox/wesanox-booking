<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Application\Integration;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Application\Integration\ValidateExternalBookingService;
use Wesanox\Booking\Domain\Integration\ExternalBooking;
use Wesanox\Booking\Domain\Integration\ExternalBookingValidator;

final class ValidateExternalBookingServiceTest extends TestCase
{
    private ValidateExternalBookingService $svc;

    protected function setUp(): void
    {
        $this->svc = new ValidateExternalBookingService(new ExternalBookingValidator());
    }

    // ── isValid / validate ────────────────────────────────────────────────────

    public function testValidBookingPassesValidation(): void
    {
        self::assertTrue($this->svc->isValid($this->valid()));
        self::assertEmpty($this->svc->validate($this->valid()));
    }

    public function testInvalidBookingFailsValidation(): void
    {
        $bad = new ExternalBooking('', 0, '', 'not-a-date', '', '', '');
        self::assertFalse($this->svc->isValid($bad));
        self::assertNotEmpty($this->svc->validate($bad));
    }

    // ── filterValid ──────────────────────────────────────────────────────────

    public function testFilterValidKeepsOnlyValidBookings(): void
    {
        $good = $this->valid();
        $bad  = new ExternalBooking('', 0, '', 'bad-date', '', '', '');

        $result = $this->svc->filterValid([$good, $bad, $good]);

        self::assertCount(2, $result);
    }

    public function testFilterValidReturnsSequentialKeys(): void
    {
        $result = $this->svc->filterValid([$this->valid(), $this->valid()]);
        self::assertArrayHasKey(0, $result);
        self::assertArrayHasKey(1, $result);
    }

    public function testFilterValidOnEmptyArrayReturnsEmpty(): void
    {
        self::assertSame([], $this->svc->filterValid([]));
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function valid(): ExternalBooking
    {
        return new ExternalBooking('ext-1', 5, 'suite-1', '2025-06-15', '14:00', '17:00', 'confirmed');
    }
}
