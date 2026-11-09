<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Domain\Integration;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Domain\Integration\ExternalBooking;
use Wesanox\Booking\Domain\Integration\ExternalBookingValidator;

final class ExternalBookingValidatorTest extends TestCase
{
    private ExternalBookingValidator $v;

    protected function setUp(): void
    {
        $this->v = new ExternalBookingValidator();
    }

    // ── valid booking ─────────────────────────────────────────────────────────

    public function testValidBookingPassesWithNoErrors(): void
    {
        self::assertEmpty($this->v->validate($this->validBooking()));
        self::assertTrue($this->v->isValid($this->validBooking()));
    }

    // ── externalId ───────────────────────────────────────────────────────────

    public function testEmptyExternalIdIsInvalid(): void
    {
        $b = $this->with(['externalId' => '']);
        self::assertNotEmpty($this->v->validate($b));
    }

    // ── areaId ───────────────────────────────────────────────────────────────

    public function testZeroAreaIdIsInvalid(): void
    {
        $b = $this->with(['areaId' => 0]);
        self::assertNotEmpty($this->v->validate($b));
    }

    public function testNegativeAreaIdIsInvalid(): void
    {
        $b = $this->with(['areaId' => -1]);
        self::assertNotEmpty($this->v->validate($b));
    }

    // ── date ─────────────────────────────────────────────────────────────────

    public function testInvalidDateFormatIsRejected(): void
    {
        $b = $this->with(['date' => '15.06.2025']);
        self::assertNotEmpty($this->v->validate($b));
    }

    public function testImpossibleDateIsRejected(): void
    {
        $b = $this->with(['date' => '2025-02-30']);
        self::assertNotEmpty($this->v->validate($b));
    }

    // ── times ─────────────────────────────────────────────────────────────────

    public function testInvalidStartTimeFormatIsRejected(): void
    {
        $b = $this->with(['startTime' => '14:0']);
        self::assertNotEmpty($this->v->validate($b));
    }

    public function testStartTimeAfterEndTimeIsRejected(): void
    {
        $b = $this->with(['startTime' => '18:00', 'endTime' => '14:00']);
        self::assertNotEmpty($this->v->validate($b));
    }

    public function testEqualStartAndEndTimeIsRejected(): void
    {
        $b = $this->with(['startTime' => '14:00', 'endTime' => '14:00']);
        self::assertNotEmpty($this->v->validate($b));
    }

    // ── status ────────────────────────────────────────────────────────────────

    public function testEmptyStatusIsInvalid(): void
    {
        $b = $this->with(['status' => '']);
        self::assertNotEmpty($this->v->validate($b));
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function validBooking(): ExternalBooking
    {
        return new ExternalBooking(
            externalId:     'ext-123',
            areaId:         5,
            externalAreaId: 'suite-1',
            date:           '2025-06-15',
            startTime:      '14:00',
            endTime:        '17:00',
            status:         'confirmed',
        );
    }

    /** @param array<string, mixed> $overrides */
    private function with(array $overrides): ExternalBooking
    {
        return new ExternalBooking(
            externalId:     $overrides['externalId']     ?? 'ext-123',
            areaId:         $overrides['areaId']         ?? 5,
            externalAreaId: $overrides['externalAreaId'] ?? 'suite-1',
            date:           $overrides['date']           ?? '2025-06-15',
            startTime:      $overrides['startTime']      ?? '14:00',
            endTime:        $overrides['endTime']        ?? '17:00',
            status:         $overrides['status']         ?? 'confirmed',
        );
    }
}
