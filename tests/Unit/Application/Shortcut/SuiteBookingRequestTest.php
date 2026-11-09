<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Application\Shortcut;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Application\Shortcut\SuiteBookingRequest;

final class SuiteBookingRequestTest extends TestCase
{
    private function tomorrow(): string
    {
        return (new \DateTimeImmutable('+1 day'))->format('Y-m-d');
    }

    private function dayAfterTomorrow(): string
    {
        return (new \DateTimeImmutable('+2 days'))->format('Y-m-d');
    }

    private function yesterday(): string
    {
        return (new \DateTimeImmutable('-1 day'))->format('Y-m-d');
    }

    // --- persons ---

    public function testItThrowsWhenPersonsIsZero(): void
    {
        $req = SuiteBookingRequest::fromArray([
            'checkin'  => $this->tomorrow(),
            'checkout' => $this->dayAfterTomorrow(),
            'persons'  => 0,
        ]);

        self::assertNotEmpty($req->validate());
        self::assertStringContainsString('Personenanzahl', implode(' ', $req->validate()));
    }

    public function testItAcceptsOneOrMorePersons(): void
    {
        $req = SuiteBookingRequest::fromArray([
            'checkin'  => $this->tomorrow(),
            'checkout' => $this->dayAfterTomorrow(),
            'persons'  => 1,
        ]);

        self::assertEmpty($req->validate());
    }

    // --- dates ---

    public function testItThrowsWhenCheckinIsEmpty(): void
    {
        $req = SuiteBookingRequest::fromArray([
            'checkin'  => '',
            'checkout' => $this->dayAfterTomorrow(),
            'persons'  => 2,
        ]);

        self::assertStringContainsString('Anreisedatum', implode(' ', $req->validate()));
    }

    public function testItThrowsWhenCheckoutIsEmpty(): void
    {
        $req = SuiteBookingRequest::fromArray([
            'checkin'  => $this->tomorrow(),
            'checkout' => '',
            'persons'  => 2,
        ]);

        self::assertStringContainsString('Abreisedatum', implode(' ', $req->validate()));
    }

    public function testItThrowsWhenCheckinIsInvalidFormat(): void
    {
        $req = SuiteBookingRequest::fromArray([
            'checkin'  => '31.12.2025',
            'checkout' => $this->dayAfterTomorrow(),
            'persons'  => 2,
        ]);

        self::assertNotEmpty($req->validate());
    }

    public function testItThrowsWhenCheckoutIsBeforeCheckin(): void
    {
        $req = SuiteBookingRequest::fromArray([
            'checkin'  => $this->dayAfterTomorrow(),
            'checkout' => $this->tomorrow(),
            'persons'  => 2,
        ]);

        self::assertStringContainsString('Abreisedatum', implode(' ', $req->validate()));
    }

    public function testItThrowsWhenCheckoutEqualsCheckin(): void
    {
        $req = SuiteBookingRequest::fromArray([
            'checkin'  => $this->tomorrow(),
            'checkout' => $this->tomorrow(),
            'persons'  => 2,
        ]);

        self::assertNotEmpty($req->validate());
    }

    public function testItThrowsWhenCheckinIsInThePast(): void
    {
        $req = SuiteBookingRequest::fromArray([
            'checkin'  => $this->yesterday(),
            'checkout' => $this->tomorrow(),
            'persons'  => 2,
        ]);

        self::assertStringContainsString('Vergangenheit', implode(' ', $req->validate()));
    }

    // --- valid request ---

    public function testItIsValidWithCorrectInput(): void
    {
        $req = SuiteBookingRequest::fromArray([
            'checkin'     => $this->tomorrow(),
            'checkout'    => $this->dayAfterTomorrow(),
            'persons'     => 3,
            'area_id'     => 1,
            'with_extras' => true,
        ]);

        self::assertEmpty($req->validate());
        self::assertTrue($req->isValid());
        self::assertTrue($req->with_extras);
        self::assertSame(1, $req->area_id);
    }

    // --- nights() ---

    public function testNightsCalculation(): void
    {
        $req = SuiteBookingRequest::fromArray([
            'checkin'  => $this->tomorrow(),
            'checkout' => $this->dayAfterTomorrow(),
            'persons'  => 2,
        ]);

        self::assertSame(1, $req->nights());
    }

    public function testNightsIsZeroForInvalidDates(): void
    {
        $req = SuiteBookingRequest::fromArray([
            'checkin'  => 'bad',
            'checkout' => 'bad',
            'persons'  => 2,
        ]);

        self::assertSame(0, $req->nights());
    }

    // --- fromArray defaults ---

    public function testFromArrayAppliesDefaults(): void
    {
        $req = SuiteBookingRequest::fromArray([]);

        self::assertSame(0,     $req->persons);
        self::assertSame(0,     $req->area_id);
        self::assertFalse($req->with_extras);
    }
}
