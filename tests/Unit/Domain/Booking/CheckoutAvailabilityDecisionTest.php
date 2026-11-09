<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Domain\Booking;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Domain\Booking\CheckoutAvailabilityDecision;

final class CheckoutAvailabilityDecisionTest extends TestCase
{
    public function test_backend_outside_opening_hours_blocks_the_checkout(): void
    {
        // Regression for 28.09.2026: opening "ab 12 Uhr", booking at 10:00.
        $answer = ['available' => false, 'reason' => 'outside_opening_hours', 'items' => []];

        $this->assertSame(
            CheckoutAvailabilityDecision::OUTSIDE_OPENING_HOURS,
            CheckoutAvailabilityDecision::violation($answer, null, '10:00', '12:00'),
        );
    }

    public function test_backend_unavailable_blocks_the_checkout_even_if_the_local_window_allows_it(): void
    {
        $answer = ['available' => false, 'reason' => 'no_item_available'];
        $window = ['closed' => false, 'opening_from' => '10:00', 'opening_to' => '22:00'];

        $this->assertSame(
            CheckoutAvailabilityDecision::UNAVAILABLE,
            CheckoutAvailabilityDecision::violation($answer, $window, '12:00', '14:00'),
        );
    }

    public function test_backend_available_wins_over_the_local_window(): void
    {
        $answer = ['available' => true, 'reason' => null];
        $window = ['closed' => false, 'opening_from' => '12:00', 'opening_to' => '22:00'];

        $this->assertNull(CheckoutAvailabilityDecision::violation($answer, $window, '10:00', '12:00'));
    }

    public function test_without_backend_the_local_opening_window_is_checked(): void
    {
        $window = ['closed' => false, 'opening_from' => '12:00', 'opening_to' => '22:00'];

        $this->assertSame(
            CheckoutAvailabilityDecision::OUTSIDE_OPENING_HOURS,
            CheckoutAvailabilityDecision::violation(null, $window, '10:00', '12:00'),
        );
        $this->assertNull(CheckoutAvailabilityDecision::violation(null, $window, '12:00', '14:00'));
    }

    public function test_without_backend_a_closed_day_blocks_and_midnight_is_the_end_of_the_day(): void
    {
        $this->assertSame(
            CheckoutAvailabilityDecision::OUTSIDE_OPENING_HOURS,
            CheckoutAvailabilityDecision::violation(null, ['closed' => true], '12:00', '14:00'),
        );
        $this->assertNull(CheckoutAvailabilityDecision::violation(
            null,
            ['closed' => false, 'opening_from' => '10:00', 'opening_to' => '23:59:59'],
            '22:00',
            '00:00',
        ));
    }

    public function test_without_backend_and_local_window_the_checkout_is_not_blocked(): void
    {
        $this->assertNull(CheckoutAvailabilityDecision::violation(null, null, '10:00', '12:00'));
    }
}
