<?php

namespace Wesanox\Booking\Service;

defined('ABSPATH') || exit;

use DateTime;

class ServiceGetAvailableTimes
{
    /** ISO weekday number (1=Monday … 7=Sunday) → area_opening JSON key */
    private const WEEKDAY_MAP = [
        1 => 'monday',
        2 => 'tuesday',
        3 => 'wednesday',
        4 => 'thursday',
        5 => 'friday',
        6 => 'saturday',
        7 => 'sunday',
    ];

    /**
     * Return the opening window for a given date.
     *
     * Priority:
     *   1. Holiday override   (wesanox_holidays table, specific date)
     *   2. Area opening       (area_opening JSON, weekday-based)
     *   3. Rates fallback     (wesanox_rates table, weekday-based)
     *   4. Hardcoded defaults
     *
     * @param string   $inputDate Y-m-d
     * @param int|null $area_id   Specific area, or null to use the first area in DB.
     * @return array{closed: bool, opening_from?: string, opening_to?: string, start_time?: string}
     * @throws \DateMalformedStringException
     */
    public static function get_opening_window(string $inputDate, ?int $area_id = null): array
    {
        global $wpdb;

        $tz    = wp_timezone();
        $now   = new DateTime('now', $tz);
        $today = (new DateTime('now', $tz))->format('Y-m-d');

        $holidays_table = $wpdb->prefix . 'wesanox_holidays';
        $rates_table    = $wpdb->prefix . 'wesanox_rates';

        // ------------------------------------------------------------------ //
        // Priority 1: Holiday override — area-specific first, global (area_id IS NULL) as fallback.
        // ------------------------------------------------------------------ //
        $holiday = null;
        if ($area_id !== null) {
            $holiday = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT opening_from, opening_to, opening_closed
                     FROM {$holidays_table}
                     WHERE opening_date = %s AND area_id = %d
                     LIMIT 1",
                    $inputDate,
                    $area_id
                )
            );
        }
        if (!$holiday) {
            $holiday = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT opening_from, opening_to, opening_closed
                     FROM {$holidays_table}
                     WHERE opening_date = %s AND area_id IS NULL
                     LIMIT 1",
                    $inputDate
                )
            );
        }

        if ($holiday) {
            if ((int) $holiday->opening_closed === 1) {
                return ['closed' => true];
            }
            $openingFrom = $holiday->opening_from ?: '10:00';
            $openingTo   = $holiday->opening_to   ?: '24:00';
        } else {
            // ------------------------------------------------------------------ //
            // Priority 2: Area opening (weekday-based from area_opening JSON)
            // ------------------------------------------------------------------ //
            $area_result = self::resolveAreaOpening($inputDate, $area_id);

            if ($area_result !== null) {
                if ($area_result['closed'] ?? false) {
                    return ['closed' => true];
                }
                $openingFrom = $area_result['from'];
                $openingTo   = $area_result['to'];
            } else {
                // ------------------------------------------------------------------ //
                // Priority 3: Rates table / hardcoded fallback
                // ------------------------------------------------------------------ //
                $currentDay = (int) wp_date('N', strtotime($inputDate), $tz);

                $rates_row = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT MIN(rate_time_from) AS from_min, MAX(rate_time_to) AS to_max
                         FROM {$rates_table}
                         WHERE rate_day = %d",
                        $currentDay
                    )
                );

                if ($rates_row && ($rates_row->from_min || $rates_row->to_max)) {
                    $openingFrom = $rates_row->from_min ?: '10:00';
                    $openingTo   = $rates_row->to_max   ?: '24:00';
                } else {
                    // Priority 4: Hardcoded defaults
                    if ($currentDay >= 1 && $currentDay <= 4) {
                        $openingFrom = '12:00';
                        $openingTo   = '22:00';
                    } elseif ($currentDay == 5) {
                        $openingFrom = '12:00';
                        $openingTo   = '24:00';
                    } elseif ($currentDay == 7) {
                        $openingFrom = '10:00';
                        $openingTo   = '22:00';
                    } else {
                        $openingFrom = '10:00';
                        $openingTo   = '24:00';
                    }
                }
            }
        }

        $openingToNorm = self::normalize_upper_time($openingTo);

        if ($inputDate === $today) {
            if ($now->format('H:i') < $openingFrom) {
                $startTime = $openingFrom;
            } else {
                $startTime = self::roundToNextQuarterHour($now);
            }
        } else {
            $startTime = $openingFrom;
        }

        if ($startTime === false || $startTime >= $openingToNorm) {
            return ['closed' => true];
        }

        return [
            'closed'       => false,
            'opening_from' => $openingFrom,
            'opening_to'   => $openingToNorm,
            'start_time'   => $startTime,
        ];
    }

    /**
     * Resolve the area opening hours for a given date from the area_opening JSON.
     *
     * Returns:
     *   - ['closed' => true]              — area explicitly closed that day
     *   - ['from' => 'HH:MM', 'to' => 'HH:MM'] — area opening window
     *   - null                            — no area / no opening configured → caller falls through
     *
     * @param string   $ymd     Y-m-d
     * @param int|null $area_id Specific area ID, or null for the first area in DB.
     * @return array{closed: bool}|array{from: string, to: string}|null
     */
    private static function resolveAreaOpening(string $ymd, ?int $area_id): ?array
    {
        global $wpdb;

        $areas_table = $wpdb->prefix . 'wesanox_areas';

        if ($area_id !== null) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT area_opening FROM `{$areas_table}` WHERE id = %d LIMIT 1",
                    $area_id
                ),
                ARRAY_A
            );
        } else {
            $row = $wpdb->get_row(
                "SELECT area_opening FROM `{$areas_table}` ORDER BY id ASC LIMIT 1",
                ARRAY_A
            );
        }

        if (!is_array($row) || empty($row['area_opening'])) {
            return null; // no area or opening not configured
        }

        $opening = json_decode((string) $row['area_opening'], true);

        if (!is_array($opening)) {
            return null; // malformed JSON
        }

        $tz        = wp_timezone();
        $dayNumber = (int) wp_date('N', strtotime($ymd), $tz); // 1=Mo … 7=So
        $dayKey    = self::WEEKDAY_MAP[$dayNumber] ?? null;

        if ($dayKey === null || !isset($opening[$dayKey])) {
            return null; // unknown weekday
        }

        $dayData = $opening[$dayKey];
        $enabled = (bool) ($dayData['enabled'] ?? false);

        if (!$enabled) {
            return ['closed' => true]; // day disabled in area config
        }

        $from = isset($dayData['from']) && $dayData['from'] !== '' ? (string) $dayData['from'] : null;
        $to   = isset($dayData['to'])   && $dayData['to']   !== '' ? (string) $dayData['to']   : null;

        if ($from === null || $to === null) {
            return null; // enabled but times missing → fall through to rates
        }

        // <input type="time"> cannot represent 24:00; users enter 00:00 for midnight.
        // Normalise to 23:59:59 so it is treated as end-of-day, consistent with
        // how normalize_upper_time() handles the literal "24:00" value.
        if ($to === '00:00') {
            $to = '23:59:59';
        }

        return ['from' => $from, 'to' => $to];
    }

    /**
     * @param string   $inputDate Y-m-d
     * @param int|null $area_id
     * @return array|int[]
     * @throws \DateMalformedStringException
     */
    public static function wesanox_get_available_times(string $inputDate, ?int $area_id = null): array
    {
        $ow = self::get_opening_window($inputDate, $area_id);
        if ($ow['closed'] ?? false) return ['closed' => 1];

        return self::get_union_available_times(
            $inputDate,
            $ow['start_time'],
            $ow['opening_to']
        );
    }

    /**
     * @param string $inputDate
     * @param string $openingFrom
     * @param string $openingToNorm
     * @param int $minSlots
     * @return array
     * @throws \DateMalformedStringException
     */
    public static function get_union_available_times(string $inputDate, string $openingFrom, string $openingToNorm, int $minSlots = 8): array
    {
        global $wpdb;

        $tz             = wp_timezone();
        $rooms_table    = $wpdb->prefix . 'wesanox_rooms';
        $bookings_table = $wpdb->prefix . 'wesanox_bookings';
        $orders_table   = $wpdb->prefix . 'wc_orders';

        $rooms = $wpdb->get_results("
            SELECT id
            FROM {$rooms_table}
            WHERE room_inactive = 0
        ");

        if (empty($rooms)) return [];

        $excluded_post_statuses = ['wc-cancelled','wc-refunded','wc-failed','trash'];
        $placeholders = implode(',', array_fill(0, count($excluded_post_statuses), '%s'));

        $bookings = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT b.room_id, b.booking_from, b.booking_to
                 FROM {$bookings_table} b
                 LEFT JOIN {$orders_table} p ON p.ID = b.wc_order_id
                 WHERE b.booking_date = %s
                   AND (p.ID IS NULL OR p.status NOT IN ($placeholders))",
                array_merge([$inputDate], $excluded_post_statuses)
            )
        );

        $timesBase = self::generateTimeSlots($openingFrom, $openingToNorm);

        $availableTimes = [];
        foreach ($rooms as $room) {
            $times = $timesBase;

            foreach ($bookings as $b) {
                if ((int)$b->room_id !== (int)$room->id) continue;

                $startDT   = new DateTime($b->booking_from, $tz);
                $closingTS = strtotime($openingToNorm);

                $adjustedStartTime = $startDT->modify('-30 minutes')->format('H:i:s');

                $tmpEnd = new DateTime($b->booking_to, $tz);
                if (strtotime($tmpEnd->format('H:i:s')) < $closingTS) {
                    $tmpEnd->modify('+30 minutes');
                }
                $endOnly = $tmpEnd->format('H:i:s');
                if (strtotime($endOnly) > $closingTS || $endOnly === '00:00:00') {
                    $endOnly = $openingToNorm;
                }

                $times = self::removeBookedTimes($times, $adjustedStartTime, $endOnly);
            }

            $availableTimes[$room->id] = self::filterTimeSlots($times, $minSlots, $openingToNorm);
        }

        $unionSet = [];
        foreach ($availableTimes as $times) {
            foreach ($times as $t) $unionSet[$t] = true;
        }
        $unionTimes = array_keys($unionSet);
        sort($unionTimes, SORT_STRING);

        uasort($availableTimes, function ($a, $b) {
            return count($b) - count($a);
        });
        if (!empty($unionTimes)) {
            $roomIds = array_keys($availableTimes);
            $firstRoomId = reset($roomIds);
            return [$firstRoomId => $unionTimes];
        }
        return [];
    }

    private static function normalize_upper_time($time): string
    {
        $t = trim($time);
        if ($t === '24:00' || $t === '24:00:00') return '23:59:59';
        return $t;
    }

    private static function roundToNextQuarterHour(DateTime $time): false|string
    {
        $minutes = (int)$time->format('i');
        $seconds = (int)$time->format('s');
        $totalMinutes = $minutes + ($seconds / 60);
        $roundUpTo = (int) ceil($totalMinutes / 15) * 15;

        if ($roundUpTo >= 60) {
            $time->modify('+1 hour');
            $time->setTime((int)$time->format('H'), 0);
        } else {
            $time->setTime((int)$time->format('H'), $roundUpTo);
        }

        if ($time->format('H:i') > '22:15') {
            return false;
        }
        return $time->format('H:i');
    }

    private static function generateTimeSlots($start, $end): array
    {
        $times = [];
        $current = strtotime($start);
        $endTs   = strtotime($end);
        $step    = 15 * 60;

        while ($current <= $endTs) {
            if ((int)date('i', $current) % 15 === 0) {
                $times[] = date('H:i', $current);
            }
            $current += $step;
        }
        return $times;
    }

    private static function removeBookedTimes($times, $start, $end): array
    {
        $startTs = strtotime($start);
        $endTs   = strtotime($end);

        $filtered = array_filter($times, function($t) use ($startTs, $endTs) {
            $ts = strtotime($t);
            return !($ts >= $startTs && $ts < $endTs);
        });

        return array_values($filtered);
    }

    private static function filterTimeSlots($times, $minSlots, $closingTime): array
    {
        $filtered = [];
        $count = count($times);
        if ($count === 0) return $filtered;

        $slotLength = 15 * 60;
        $duration   = $minSlots * $slotLength;

        $closingTs = strtotime($closingTime);

        if ($closingTime === '23:59:00') {
            $closingTs += 60;
        }
        if ($closingTime === '23:59:59') {
            $closingTs += 1; // treat as 24:00:00 (midnight), so 22:00 start + 2 h fits
        }

        $latestByClosing = $closingTs - $duration;
        $latestStartTs   = $latestByClosing;

        for ($i = 0; $i < $count; $i++) {
            $startTs = strtotime($times[$i]);
            if ($startTs > $latestStartTs) break;

            $ok = true;
            for ($j = 1; $j < $minSlots; $j++) {
                if ($i + $j >= $count) { $ok = false; break; }
                if (strtotime($times[$i + $j]) !== $startTs + ($j * $slotLength)) { $ok = false; break; }
            }

            if ($ok) $filtered[] = $times[$i];
        }

        return $filtered;
    }
}
