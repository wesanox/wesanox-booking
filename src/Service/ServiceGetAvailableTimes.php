<?php

namespace Wesanox\Booking\Service;

defined('ABSPATH') || exit;

use DateTime;

class ServiceGetAvailableTimes
{
    /**
     * @param string $inputDate
     * @return array|true[]
     * @throws \DateMalformedStringException
     */
    public static function get_opening_window(string $inputDate): array
    {
        global $wpdb;

        $tz    = wp_timezone();
        $now   = new DateTime('now', $tz);
        $today = (new DateTime('now', $tz))->format('Y-m-d');

        $holidays_table = $wpdb->prefix . 'wesanox_holidays';
        $rates_table    = $wpdb->prefix . 'wesanox_rates';

        // Holiday-Override
        $holiday = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT opening_from, opening_to, opening_closed
                 FROM {$holidays_table}
                 WHERE opening_date = %s
                 LIMIT 1",
                $inputDate
            )
        );

        if ($holiday) {
            if ((int)$holiday->opening_closed === 1) {
                return ['closed' => true];
            }
            $openingFrom = $holiday->opening_from ?: '10:00';
            $openingTo   = $holiday->opening_to   ?: '24:00';
        } else {
            // Fallback auf Raten / Standardzeiten
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
            'closed'        => false,
            'opening_from'  => $openingFrom,
            'opening_to'    => $openingToNorm,
            'start_time'    => $startTime,
        ];
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

                // Deine aktuelle Pufferlogik: -30 / +30
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

        // Lead-Key (wie bisher): Raum mit den meisten Slots
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

    /**
     * @param string $inputDate
     * @return array|int[]
     * @throws \DateMalformedStringException
     */
    public static function wesanox_get_available_times(string $inputDate): array
    {
        $ow = self::get_opening_window($inputDate);
        if ($ow['closed'] ?? false) return ['closed' => 1];

        return self::get_union_available_times(
            $inputDate,
            $ow['start_time'],
            $ow['opening_to'],
            8
        );
    }

    /**
     * @param $time
     * @return string
     */
    private static function normalize_upper_time($time)
    {
        $t = trim($time);
        if ($t === '24:00' || $t === '24:00:00') return '23:59:59';
        return $t;
    }

    /**
     * @param DateTime $time
     * @return false|string
     * @throws \DateMalformedStringException
     */
    private static function roundToNextQuarterHour(DateTime $time)
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

    /**
     * @param $start
     * @param $end
     * @return array
     */
    private static function generateTimeSlots($start, $end)
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

    /**
     * @param $times
     * @param $start
     * @param $end
     * @return array
     */
    private static function removeBookedTimes($times, $start, $end)
    {
        $startTs = strtotime($start);
        $endTs   = strtotime($end);

        $filtered = array_filter($times, function($t) use ($startTs, $endTs) {
            $ts = strtotime($t);
            return !($ts >= $startTs && $ts < $endTs);
        });

        return array_values($filtered);
    }

    /**
     * @param $times
     * @param $minSlots
     * @param $closingTime
     * @return array
     */
    private static function filterTimeSlots($times, $minSlots, $closingTime)
    {
        $filtered = [];
        $count = count($times);
        if ($count === 0) return $filtered;

        $slotLength = 15 * 60;
        $duration   = $minSlots * $slotLength;

        $closingTs = strtotime($closingTime);
        $latestByClosing = $closingTs - $duration;

        // Falls ihr auch nach 22:00 öffnet, entferne die Hardcap-Zeile:
        $latestHardCap = strtotime('22:00');
        $latestStartTs = min($latestByClosing, $latestHardCap);

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