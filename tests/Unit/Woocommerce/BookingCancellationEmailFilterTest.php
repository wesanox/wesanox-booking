<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Woocommerce;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Woocommerce\BookingCancellationEmailFilter;

final class BookingCancellationEmailFilterTest extends TestCase
{
    public function test_cancellation_mails_are_suppressed_for_booking_orders(): void
    {
        $filter = new BookingCancellationEmailFilter();
        $bookingOrder = new \Wesanox_Test_WC_Order(['_booking_id' => '33958']);

        $this->assertFalse($filter->filterEnabled(true, $bookingOrder));
    }

    public function test_cancellation_mails_stay_enabled_for_other_orders(): void
    {
        $filter = new BookingCancellationEmailFilter();
        $voucherOrder = new \Wesanox_Test_WC_Order();

        $this->assertTrue($filter->filterEnabled(true, $voucherOrder));
        $this->assertTrue($filter->filterEnabled(true, null));
    }

    public function test_a_disabled_mail_stays_disabled(): void
    {
        $filter = new BookingCancellationEmailFilter();

        $this->assertFalse($filter->filterEnabled(false, new \Wesanox_Test_WC_Order()));
    }

    public function test_both_woocommerce_cancellation_mails_are_covered(): void
    {
        $this->assertSame(
            ['cancelled_order', 'customer_cancelled_order'],
            BookingCancellationEmailFilter::SUPPRESSED_EMAIL_IDS,
        );
    }
}
