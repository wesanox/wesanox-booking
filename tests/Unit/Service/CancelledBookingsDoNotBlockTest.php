<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Service\ServiceGetAvailableRoomarts;
use Wesanox\Booking\Service\ServiceGetAvailableTimes;

/**
 * Regression 01.11.2026: bookings cancelled in the backend (booking_status 3) kept
 * blocking Suite 6 in the frontend, because only the WooCommerce order status was checked.
 */
final class CancelledBookingsDoNotBlockTest extends TestCase
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

    public function test_cancelled_booking_without_order_does_not_block_start_times(): void
    {
        $this->fakeDatabase([
            ['room_id' => 6, 'booking_from' => '10:00:00', 'booking_to' => '22:00:00', 'booking_status' => 3],
        ]);

        $result = ServiceGetAvailableTimes::get_union_available_times('2026-11-01', '10:00', '22:00', 8);

        self::assertContains('10:00', reset($result));
        self::assertContains('20:00', reset($result));
    }

    public function test_active_booking_still_blocks_start_times(): void
    {
        $this->fakeDatabase([
            ['room_id' => 6, 'booking_from' => '10:00:00', 'booking_to' => '22:00:00', 'booking_status' => 0],
        ]);

        $result = ServiceGetAvailableTimes::get_union_available_times('2026-11-01', '10:00', '22:00', 8);

        self::assertSame([], $result);
    }

    public function test_booking_without_status_still_blocks_start_times(): void
    {
        $this->fakeDatabase([
            ['room_id' => 6, 'booking_from' => '10:00:00', 'booking_to' => '22:00:00', 'booking_status' => null],
        ]);

        $result = ServiceGetAvailableTimes::get_union_available_times('2026-11-01', '10:00', '22:00', 8);

        self::assertSame([], $result);
    }

    public function test_cancelled_booking_does_not_block_room_category(): void
    {
        $this->fakeDatabase([
            ['room_id' => 6, 'booking_from' => '15:30:00', 'booking_to' => '18:30:00', 'booking_status' => 3],
        ]);

        self::assertTrue(
            (new ServiceGetAvailableRoomarts())->wesanox_roomart_available(1, '2026-11-01', '16:30:00', '18:30:00')
        );
        self::assertSame(
            6,
            (new ServiceGetAvailableRoomarts())->wesanox_find_room(1, '2026-11-01', '16:30:00', '18:30:00')
        );
    }

    public function test_active_booking_still_blocks_room_category(): void
    {
        $this->fakeDatabase([
            ['room_id' => 6, 'booking_from' => '15:30:00', 'booking_to' => '18:30:00', 'booking_status' => 0],
        ]);

        self::assertFalse(
            (new ServiceGetAvailableRoomarts())->wesanox_roomart_available(1, '2026-11-01', '16:30:00', '18:30:00')
        );
        self::assertNull(
            (new ServiceGetAvailableRoomarts())->wesanox_find_room(1, '2026-11-01', '16:30:00', '18:30:00')
        );
    }

    /**
     * Minimal $wpdb fake with a single room (Suite 6). It applies the cancelled-status
     * filter only when the query contains it, so the test fails without the fix.
     *
     * @param list<array{room_id: int, booking_from: string, booking_to: string, booking_status: int|null}> $bookings
     */
    private function fakeDatabase(array $bookings): void
    {
        $GLOBALS['wpdb'] = new class($bookings) {
            public string $prefix = 'wp_';

            /** @param list<array{room_id: int, booking_from: string, booking_to: string, booking_status: int|null}> $bookings */
            public function __construct(private array $bookings) {}

            public function prepare(string $query, mixed ...$args): string
            {
                return $query;
            }

            public function get_results(string $query): array
            {
                if (str_contains($query, 'FROM wp_wesanox_bookings')) {
                    return array_map(
                        static fn (array $booking): object => (object) $booking,
                        $this->visibleBookings($query)
                    );
                }

                if (str_contains($query, 'FROM wp_wesanox_rooms')) {
                    return [(object) ['id' => 6]];
                }

                return [];
            }

            public function get_var(string $query): int
            {
                $blocking = count($this->visibleBookings($query));

                if (str_contains($query, 'SELECT r.id')) {
                    return $blocking === 0 ? 6 : 0;
                }

                return $blocking;
            }

            /** @return list<array{room_id: int, booking_from: string, booking_to: string, booking_status: int|null}> */
            private function visibleBookings(string $query): array
            {
                if (!str_contains($query, 'booking_status <> 3')) {
                    return $this->bookings;
                }

                return array_values(array_filter(
                    $this->bookings,
                    static fn (array $booking): bool => $booking['booking_status'] !== 3
                ));
            }
        };
    }
}
