<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Wesanox\Booking\Service\ServiceGetAvailableTimes;

/**
 * Tests for ServiceGetAvailableTimes::filterTimeSlots().
 *
 * The method is private/static, so we access it via Reflection.
 */
final class FilterTimeSlotsTest extends TestCase
{
    private static function callFilterTimeSlots(array $times, int $minSlots, string $closingTime): array
    {
        $rm = new ReflectionMethod(ServiceGetAvailableTimes::class, 'filterTimeSlots');
        $rm->setAccessible(true);
        return $rm->invoke(null, $times, $minSlots, $closingTime);
    }

    /**
     * Build a continuous sequence of 15-min slots from $start to just before $end.
     * e.g. slotsFrom('10:00', '12:00') → ['10:00','10:15',…,'11:45']
     */
    private static function slotsFrom(string $start, string $end): array
    {
        $slots   = [];
        $current = strtotime($start);
        $endTs   = strtotime($end);
        while ($current < $endTs) {
            $slots[] = date('H:i', $current);
            $current += 15 * 60;
        }
        return $slots;
    }

    // --- midnight closing (23:59:59) ---

    public function testMidnightClosingAllows2200StartWith2HourMin(): void
    {
        // Slots from 09:00 up to but not including midnight
        $times  = self::slotsFrom('09:00', '23:59:59');
        $minSlots = 8; // 8 × 15 min = 2 hours

        $result = self::callFilterTimeSlots($times, $minSlots, '23:59:59');

        self::assertContains('22:00', $result, '22:00 must be selectable with 2 h min and midnight closing');
    }

    public function testMidnightClosingDoesNotAllow2215StartWith2HourMin(): void
    {
        $times    = self::slotsFrom('09:00', '23:59:59');
        $minSlots = 8;

        $result = self::callFilterTimeSlots($times, $minSlots, '23:59:59');

        self::assertNotContains('22:15', $result, '22:15 + 2 h would exceed midnight');
    }

    // --- 22:00 hard closing ---

    public function testHardClosingAt2200Allows2000StartWith2HourMin(): void
    {
        $times    = self::slotsFrom('09:00', '22:00');
        $minSlots = 8;

        $result = self::callFilterTimeSlots($times, $minSlots, '22:00');

        self::assertContains('20:00', $result);
        self::assertNotContains('20:15', $result, '20:15 + 2 h = 22:15 > 22:00 closing');
    }

    // --- ordinary closing ---

    public function testOrdinaryClosingRespectsLatestStart(): void
    {
        // Closing 18:00, min 1 h (4 slots) → latest start = 17:00
        $times    = self::slotsFrom('09:00', '18:00');
        $minSlots = 4;

        $result = self::callFilterTimeSlots($times, $minSlots, '18:00');

        self::assertContains('17:00', $result);
        self::assertNotContains('17:15', $result);
    }

    // --- existing 23:59:00 normalisation stays intact ---

    public function testLegacyNormalisationFor235900(): void
    {
        $times    = self::slotsFrom('09:00', '23:59:59');
        $minSlots = 8;

        $resultLegacy  = self::callFilterTimeSlots($times, $minSlots, '23:59:00');
        $resultMidnight = self::callFilterTimeSlots($times, $minSlots, '23:59:59');

        // Both must include 22:00
        self::assertContains('22:00', $resultLegacy);
        self::assertContains('22:00', $resultMidnight);
    }

    public function testAvailableTimesUseRoomIdForItemStatusNotCategoryId(): void
    {
        global $wpdb;

        $had_wpdb = array_key_exists('wpdb', $GLOBALS);
        $previous_wpdb = $GLOBALS['wpdb'] ?? null;

        $wpdb = new class {
            public string $prefix = 'wp_';

            public function prepare(string $query, mixed ...$args): string
            {
                return $query;
            }

            public function get_results(string $query): array
            {
                if (str_contains($query, 'FROM wp_wesanox_bookings')) {
                    return [];
                }

                if (!str_contains($query, 'FROM wp_wesanox_rooms')) {
                    return [];
                }

                $rooms = [
                    ['id' => 1, 'roomart_id' => 1],
                    ['id' => 2, 'roomart_id' => 1],
                ];
                $items = [
                    1 => ['inactive' => 1, 'inactiv_from' => null, 'inactiv_to' => null],
                    2 => ['inactive' => 0, 'inactiv_from' => null, 'inactiv_to' => null],
                ];

                $join_uses_room_id = str_contains($query, 'i.id = r.id');

                $available = [];
                foreach ($rooms as $room) {
                    $joined_item_id = $join_uses_room_id ? $room['id'] : $room['roomart_id'];
                    $item = $items[$joined_item_id] ?? null;

                    if ($item !== null && (int) $item['inactive'] === 1 && $item['inactiv_from'] === null && $item['inactiv_to'] === null) {
                        continue;
                    }

                    $available[] = (object) ['id' => $room['id']];
                }

                return $available;
            }
        };

        try {
            $result = ServiceGetAvailableTimes::get_union_available_times('2026-06-16', '12:00', '22:00', 8);
        } finally {
            if ($had_wpdb) {
                $wpdb = $previous_wpdb;
                $GLOBALS['wpdb'] = $previous_wpdb;
            } else {
                unset($GLOBALS['wpdb']);
            }
        }

        self::assertNotEmpty($result);

        $times = reset($result);
        self::assertIsArray($times);
        self::assertContains('12:00', $times);
    }
}
