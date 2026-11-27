<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Service\ServiceGetAvailableTimes;

/**
 * Regression AEA-33 / AEA-93: a booking ending after 23:30 (23:45, 23:59, 24:00) did not
 * block its room in the calendar. End + 30 min buffer wrapped to "00:xx", so no slot was
 * removed and the day showed "Hohes Aufkommen" instead of "Ausgebucht"
 * (e.g. 24.10.2026: Suites 6, 7, 8 each booked 22:00–23:59).
 */
final class LateBookingsBlockCalendarTest extends TestCase
{
    private bool $hadWpdb = false;
    private mixed $previousWpdb = null;

    protected function setUp(): void
    {
        $this->hadWpdb = array_key_exists('wpdb', $GLOBALS);
        $this->previousWpdb = $GLOBALS['wpdb'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->hadWpdb) {
            $GLOBALS['wpdb'] = $this->previousWpdb;
        } else {
            unset($GLOBALS['wpdb']);
        }
    }

    /** @return array<string, array{string}> */
    public static function lateBookingEnds(): array
    {
        return [
            '23:30' => ['23:30:00'],
            '23:45' => ['23:45:00'],
            '23:59' => ['23:59:00'],
            '23:59:59' => ['23:59:59'],
            '24:00' => ['24:00:00'],
            '00:00' => ['00:00:00'],
        ];
    }

    #[DataProvider('lateBookingEnds')]
    public function test_fully_booked_room_with_late_booking_has_no_start_times(string $bookingTo): void
    {
        $this->fakeDatabase([
            ['room_id' => 6, 'booking_from' => '10:00:00', 'booking_to' => '21:30:00'],
            ['room_id' => 6, 'booking_from' => '22:00:00', 'booking_to' => $bookingTo],
        ]);

        $result = ServiceGetAvailableTimes::get_union_available_times('2026-11-14', '10:00', '23:59:59', 8);

        self::assertSame([], $result);
    }

    #[DataProvider('lateBookingEnds')]
    public function test_late_booking_blocks_evening_including_buffer(string $bookingTo): void
    {
        $this->fakeDatabase([
            ['room_id' => 6, 'booking_from' => '22:00:00', 'booking_to' => $bookingTo],
        ]);

        $result = ServiceGetAvailableTimes::get_union_available_times('2026-11-14', '10:00', '23:59:59', 8);
        $times  = reset($result) ?: [];

        // 19:30 + 2 h = 21:30, plus 30 min buffer = 22:00 → still fits.
        self::assertContains('19:30', $times);
        // 19:45 would run into the buffer before the 22:00 booking.
        self::assertNotContains('19:45', $times);
        self::assertNotContains('22:00', $times);
    }

    public function test_booking_ending_at_closing_on_short_day_blocks_until_closing(): void
    {
        $this->fakeDatabase([
            ['room_id' => 6, 'booking_from' => '19:00:00', 'booking_to' => '22:00:00'],
        ]);

        $result = ServiceGetAvailableTimes::get_union_available_times('2026-11-16', '10:00', '22:00', 8);
        $times  = reset($result) ?: [];

        self::assertContains('16:30', $times);
        self::assertNotContains('16:45', $times);
        self::assertSame('16:30', end($times));
    }

    /**
     * Minimal $wpdb fake with a single room (Suite 6).
     *
     * @param list<array{room_id: int, booking_from: string, booking_to: string}> $bookings
     */
    private function fakeDatabase(array $bookings): void
    {
        $GLOBALS['wpdb'] = new class($bookings) {
            public string $prefix = 'wp_';

            /** @param list<array{room_id: int, booking_from: string, booking_to: string}> $bookings */
            public function __construct(private array $bookings) {}

            public function prepare(string $query, mixed ...$args): string
            {
                return $query;
            }

            public function query(string $query): int
            {
                return 0;
            }

            public function get_results(string $query): array
            {
                if (str_contains($query, 'FROM wp_wesanox_bookings')) {
                    return array_map(static fn (array $booking): object => (object) $booking, $this->bookings);
                }

                if (str_contains($query, 'FROM wp_wesanox_rooms')) {
                    return [(object) ['id' => 6]];
                }

                return [];
            }

            public function get_var(string $query): int
            {
                return 0;
            }
        };
    }
}
