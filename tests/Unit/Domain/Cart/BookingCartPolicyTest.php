<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Domain\Cart;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Domain\Cart\BookingCartPolicy;

final class BookingCartPolicyTest extends TestCase
{
    private BookingCartPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new BookingCartPolicy();
    }

    public function testUnrelatedCartNeedsNoBookingValidation(): void
    {
        self::assertNull($this->policy->violation(false, false, false, false));
    }

    public function testExtrasWithoutRateAreRejected(): void
    {
        self::assertSame(
            BookingCartPolicy::MISSING_RATE,
            $this->policy->violation(false, true, true, false),
        );
    }

    public function testRateWithoutCompleteBookingDataIsRejected(): void
    {
        self::assertSame(
            BookingCartPolicy::MISSING_BOOKING_DATA,
            $this->policy->violation(true, false, false, true),
        );
    }

    public function testRateWithoutResolvableRoomartIsRejected(): void
    {
        self::assertSame(
            BookingCartPolicy::MISSING_ROOMART,
            $this->policy->violation(true, true, true, false),
        );
    }

    public function testValidBookingCartIsAccepted(): void
    {
        self::assertNull($this->policy->violation(true, true, true, true));
        self::assertTrue($this->policy->canAddExtra(true, true));
    }

    public function testExtraRequiresRateAndCompleteBookingData(): void
    {
        self::assertFalse($this->policy->canAddExtra(false, true));
        self::assertFalse($this->policy->canAddExtra(true, false));
    }
}
