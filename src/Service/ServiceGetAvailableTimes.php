<?php

namespace Wesanox\Booking\Service;

defined( 'ABSPATH' )|| exit;

use DateTime;

class ServiceGetAvailableTimes
{
    public static function wesanox_get_available_times($inputDate): array
    {
        global $wpdb;

        $tz = wp_timezone();
        $now = new DateTime('now', $tz);
        $today = (new DateTime('now', $tz))->format('Y-m-d');

        $holidays_table = $wpdb->prefix . 'wesanox_holidays';

        $holiday = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT opening_from, opening_to, opening_closed, opening_holiday
                 FROM {$holidays_table}
                 WHERE opening_date = %s
                 LIMIT 1",
                 $inputDate
            )
        );

        if ( $holiday ) {
            if ( (int) $holiday->opening_closed === 1 ) {
                return ['closed' => 1];
            }

            $openingFrom = $holiday->opening_from ?: '10:00';
            $openingTo = $holiday->opening_to   ?: '24:00';
        } else {
            $rates_table = $wpdb->prefix . 'wesanox_rates';

            $currentDay  = (int) wp_date('N', strtotime($inputDate), $tz);

            // Hole früheste Startzeit & späteste Endzeit für den Tag
            $rates_row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT MIN(rate_time_from) AS from_min, MAX(rate_time_to) AS to_max
                     FROM {$rates_table}
                     WHERE rate_day = %d",
                    $currentDay
                )
            );

            if ( $rates_row && ($rates_row->from_min || $rates_row->to_max) ) {
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
            return [];
        }

        $rooms_table = $wpdb->prefix . 'wesanox_rooms';
        $bookings_table = $wpdb->prefix . 'wesanox_bookings';

        // Räume, die online aktiv sind
        $rooms = $wpdb->get_results(
            "SELECT id
             FROM {$rooms_table}
             WHERE room_inactive = 0"
        );

        $excluded_post_statuses = ['wc-cancelled','wc-completed','wc-refunded','wc-failed','trash'];
        $placeholders = implode(',', array_fill(0, count($excluded_post_statuses), '%s'));

        $bookings = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT b.room_id, b.booking_from, b.booking_to
                 FROM {$bookings_table} b
                 LEFT JOIN {$wpdb->posts} p ON p.ID = b.wc_order_id
                 WHERE b.booking_date = %s
                   AND (p.ID IS NULL OR p.post_status NOT IN ($placeholders))",
                array_merge([$inputDate], $excluded_post_statuses)
            )
        );

        $availableTimes = [];

        $timesBase = self::generateTimeSlots($startTime, $openingToNorm);

        foreach ($rooms as $room) {
            $times = $timesBase;

            foreach ($bookings as $b) {
                if ((int)$b->room_id !== (int)$room->id) {
                    continue;
                }

                $startDT   = new DateTime($b->booking_from, $tz);
                $endDT     = new DateTime($b->booking_to,   $tz);
                $closingTS = strtotime($openingToNorm); // 'H:i:s' / 'H:i' → gleiches Format wie unten

                $adjustedStartTime = $startDT->modify('-145 minutes')->format('H:i:s');

                $tmpEnd = new DateTime($b->booking_to, $tz);
                if (strtotime($tmpEnd->format('H:i:s')) < $closingTS) {
                    $tmpEnd->modify('+30 minutes');
                }

                $endOnly = $tmpEnd->format('H:i:s');

                if (strtotime($endOnly) > $closingTS || $endOnly === '00:00:00') {
                    $endOnly = $openingToNorm; // z.B. 23:59:59
                }

                $adjustedEndTime = $endOnly;

                $times = self::removeBookedTimes($times, $adjustedStartTime, $adjustedEndTime);
            }


            $availableTimes[$room->id] = self::filterTimeSlots($times, 1, $openingToNorm);
        }

        uasort($availableTimes, function ($a, $b) {
            return count($b) - count($a);
        });

        foreach ($availableTimes as $roomId => $times) {
            if (!empty($times)) {
                return [$roomId => $times];
            }
        }

        return [];
    }

    /** "24:00" -> "23:59:59" für strtotime-Kompatibilität */
    private static function normalize_upper_time($time)
    {
        $t = trim($time);
        if ($t === '24:00' || $t === '24:00:00') {
            return '23:59:59';
        }
        return $t;
    }

    /** Rundet auf das nächste 15-Min-Fenster, begrenzt auf 22:15 */
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

    /** Zeitslots generieren (15-Minuten) */
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

    /** Gebuchte Zeiten (inkl. Puffer) entfernen */
    private static function removeBookedTimes($times, $start, $end)
    {
        $startTs = strtotime($start);
        $endTs = strtotime($end);

        $filtered = array_filter($times, function($t) use ($startTs, $endTs) {
            $ts = strtotime($t);
            return !($ts >= $startTs && $ts < $endTs);
        });

        return array_values($filtered);
    }

    /** Nur gültige Startzeiten bis (closing - 2h + 15m) und vor 22:15 */
    private static function filterTimeSlots($times, $minSlots, $closingTime)
    {
        $filtered = [];
        $count = count($times);
        if ($count === 0) return $filtered;

        $slotLength = 15 * 60;
        $duration = $minSlots * $slotLength;

        $closingTs = strtotime($closingTime);

        $latestByClosing = $closingTs - $duration;

        $latestHardCap = strtotime('22:00');

        $latestStartTs = min($latestByClosing, $latestHardCap);

        for ($i = 0; $i < $count; $i++) {
            $startTs = strtotime($times[$i]);

            if ($startTs > $latestStartTs) {
                break;
            }

            $ok = true;
            for ($j = 1; $j < $minSlots; $j++) {
                if ($i + $j >= $count) { $ok = false; break; }
                if (strtotime($times[$i + $j]) !== $startTs + ($j * $slotLength)) { $ok = false; break; }
            }

            if ($ok) {
                $filtered[] = $times[$i];
            }
        }

        return $filtered;
    }
}