<?php

namespace Wesanox\Booking\Service;

defined( 'ABSPATH' )|| exit;

use DateTime;
use DateInterval;

class ServiceGetNextAvailableRoomarts
{
    protected ServiceGetAvailableTimes $timesService;
    protected ServiceGetHolidayPrices $holidayService;
    protected ServiceBookingTime $bookingTimeService;
    protected ServiceGetAvailableRoomarts $roomartService;

    public function __construct()
    {
        $this->timesService       = new ServiceGetAvailableTimes();
        $this->holidayService     = new ServiceGetHolidayPrices();
        $this->bookingTimeService = new ServiceBookingTime();
        $this->roomartService     = new ServiceGetAvailableRoomarts();
    }

    /**
     * Finde die nächsten verfügbaren Slots für eine Roomart.
     *
     * @param int         $roomartId          1 / 2 / ...
     * @param string|null $fromYmdHis         Startzeitpunkt (Y-m-d H:i:s). Null => jetzt (gerundet auf nächstes 15er-Intervall).
     * @param int         $durationMinutes    Gewünschte Dauer in Minuten (z. B. 120).
     * @param int         $limit              Wie viele Treffer zurückgeben (z. B. 5).
     * @param int         $maxDaysAhead       Wie viele Tage maximal nach vorne suchen (z. B. 14).
     * @return array[]    Liste aus Treffern: [
     *   ['date' => 'Y-m-d', 'start' => 'H:i', 'stop' => 'H:i', 'roomart_id' => X]
     * ]
     */
    public function wesanox_get_next_available_for_roomart(
        int $roomartId,
        ?string $fromYmdHis = null,
        int $durationMinutes = 120,
        int $limit = 5,
        int $maxDaysAhead = 14
    ): array {
        $tz = wp_timezone();

        // Startpunkt bestimmen (jetzt → nächstes 15er-Intervall)
        $startDT = $fromYmdHis
            ? new DateTime($fromYmdHis, $tz)
            : new DateTime('now', $tz);

        $startDT = $this->roundUpToQuarter($startDT);

        $results = [];
        $oneDay  = new DateInterval('P1D');

        for ($d = 0; $d <= $maxDaysAhead; $d++) {
            $currentDate = (clone $startDT)->add(new DateInterval("P{$d}D"))->format('Y-m-d');

            // Öffnungszeiten für den Tag ermitteln (nutzt deine bestehende Logik aus ServiceGetAvailableTimes)
            [$openingFrom, $openingToNorm] = $this->get_opening_bounds_for_day($currentDate);

            if ($openingFrom === null || $openingToNorm === null) {
                // geschlossen
                continue;
            }

            // Startzeit an diesem Tag:
            $dayStart = ($d === 0)
                ? max($startDT->format('H:i'), $openingFrom)
                : $openingFrom;

            // Zeitslots generieren ab dayStart bis Schließzeit
            $times = $this->generateQuarterSlots($dayStart, $openingToNorm);

            // Für jeden möglichen Startslot prüfen, ob die Roomart für die Dauer verfügbar ist
            foreach ($times as $startHi) {
                $startTs = strtotime($startHi);
                $stopTs  = $startTs + $durationMinutes * 60;

                // Nicht über Schließzeit und optional Hardcap 22:00
                $closingTs = strtotime($openingToNorm);
                $hardCapTs = strtotime('22:00'); // falls du das weiterhin willst
                $latestAllowedStartTs = min($closingTs - $durationMinutes * 60, $hardCapTs);

                if ($startTs > $latestAllowedStartTs) {
                    break;
                }

                $stopHi = date('H:i', $stopTs);

                // CHECK: roomart verfügbar?
                $availableCountOrBool = $this->roomartService->wesanox_roomart_available(
                    $roomartId,
                    $currentDate,
                    date('H:i:s', $startTs),
                    date('H:i:s', $stopTs)
                );

                // Wenn deine Funktion einen Bool liefert: if ($availableCountOrBool)
                // Wenn sie Anzahl verfügbarer Räume liefert: if ((int)$availableCountOrBool > 0)
                if (!empty($availableCountOrBool)) {
                    $results[] = [
                        'date'       => $currentDate,
                        'start'      => date('H:i', $startTs),
                        'stop'       => $stopHi,
                        'roomart_id' => $roomartId,
                    ];
                    if (count($results) >= $limit) {
                        return $results;
                    }
                }
            }
        }

        return $results;
    }

    /** Öffnungs-Bounds wie in ServiceGetAvailableTimes (Holiday, Raten, Fallbacks) */
    private function get_opening_bounds_for_day(string $ymd): array
    {
        // => nutzt deine vorhandenen Regeln 1:1
        $tz = wp_timezone();
        $today = (new DateTime('now', $tz))->format('Y-m-d');

        // Hol’ dir openingFrom/openingTo identisch zu ServiceGetAvailableTimes::wesanox_get_available_times()
        // Ich rufe pragmatisch einmal deine Funktion auf und lese Start/Ende daraus:
        // (Kleine Abkürzung: wir berechnen hier dieselben Werte nochmal inline – stabiler gegen Seiteneffekte)

        global $wpdb;
        $holidays_table = $wpdb->prefix . 'wesanox_holidays';
        $holiday = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT opening_from, opening_to, opening_closed, opening_holiday
                 FROM {$holidays_table}
                 WHERE opening_date = %s LIMIT 1",
                $ymd
            )
        );

        if ($holiday && (int)$holiday->opening_closed === 1) {
            return [null, null]; // geschlossen
        }

        if ($holiday) {
            $openingFrom = $holiday->opening_from ?: '10:00';
            $openingTo   = $holiday->opening_to   ?: '24:00';
        } else {
            $rates_table = $wpdb->prefix . 'wesanox_rates';
            $currentDay  = (int) wp_date('N', strtotime($ymd), $tz);
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
                if ($currentDay >= 1 && $currentDay <= 4) { $openingFrom = '12:00'; $openingTo = '22:00';
                } elseif ($currentDay == 5)             { $openingFrom = '12:00'; $openingTo = '24:00';
                } elseif ($currentDay == 7)             { $openingFrom = '10:00'; $openingTo = '22:00';
                } else                                   { $openingFrom = '10:00'; $openingTo = '24:00'; }
            }
        }

        $openingToNorm = ($openingTo === '24:00' || $openingTo === '24:00:00') ? '23:59:59' : $openingTo;

        return [$openingFrom, $openingToNorm];
    }

    /** 15-Min-Slots als 'H:i' von start..end (inkl.) */
    private function generateQuarterSlots(string $startHi, string $endHis): array
    {
        $slots = [];
        $current = strtotime($startHi);
        $endTs   = strtotime($endHis);
        while ($current <= $endTs) {
            if (((int)date('i', $current) % 15) === 0) {
                $slots[] = date('H:i', $current);
            }
            $current += 15 * 60;
        }
        return $slots;
    }

    /** Auf nächstes 15er Intervall runden, aber nicht über 22:00 raus */
    private function roundUpToQuarter(DateTime $dt): DateTime
    {
        $m = (int)$dt->format('i');
        $s = (int)$dt->format('s');
        $total = $m + ($s / 60);
        $up = (int)ceil($total / 15) * 15;

        if ($up >= 60) {
            $dt->modify('+1 hour')->setTime((int)$dt->format('H'), 0);
        } else {
            $dt->setTime((int)$dt->format('H'), $up);
        }

        if ($dt->format('H:i') > '22:00') {
            $dt->setTime(22, 0);
        }
        return $dt;
    }
}