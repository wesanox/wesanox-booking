<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Domain\Booking;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Domain\Booking\CalendarDayStatus;

final class CalendarDayStatusTest extends TestCase
{
    private const FREE_TIMES = [2 => ['20:30', '20:45', '21:00']];

    public function test_closed_day_is_closed(): void
    {
        $this->assertSame(CalendarDayStatus::CLOSED, CalendarDayStatus::resolve(['closed' => 1], 0));
    }

    public function test_day_without_free_times_is_fully_booked_regardless_of_booking_count(): void
    {
        $this->assertSame(CalendarDayStatus::FULLY_BOOKED, CalendarDayStatus::resolve([], 0));
        $this->assertSame(CalendarDayStatus::FULLY_BOOKED, CalendarDayStatus::resolve([], 40));
    }

    public function test_day_with_free_times_and_few_bookings_is_available(): void
    {
        $this->assertSame(CalendarDayStatus::AVAILABLE, CalendarDayStatus::resolve(self::FREE_TIMES, 10));
    }

    public function test_day_with_free_times_and_many_bookings_is_busy(): void
    {
        $this->assertSame(CalendarDayStatus::BUSY, CalendarDayStatus::resolve(self::FREE_TIMES, 11));
    }

    public function test_threshold_keeps_previous_rule_of_more_than_ten_bookings(): void
    {
        // Regression AEA-33: the count passed in no longer contains cancelled orders,
        // the threshold itself stays "more than 10".
        $this->assertSame(11, CalendarDayStatus::BUSY_THRESHOLD);
    }
}
