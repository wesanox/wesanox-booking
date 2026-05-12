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

                // Nicht über Schließzeit hinaus starten
                $closingTs = strtotime($openingToNorm);
                if ($openingToNorm === '23:59:59') {
                    $closingTs += 1; // treat as 24:00:00 (midnight)
                }
                $latestAllowedStartTs = $closingTs - $durationMinutes * 60;

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

    /**
     * Delegates to ServiceGetAvailableTimes::get_opening_window() so that the full
     * priority chain (Holiday → Area Opening → Rates → Hardcoded) is respected here too.
     *
     * @return array{0: string|null, 1: string|null}  [openingFrom, openingToNorm] or [null, null] if closed.
     */
    private function get_opening_bounds_for_day(string $ymd): array
    {
        $ow = ServiceGetAvailableTimes::get_opening_window($ymd);

        if ($ow['closed'] ?? false) {
            return [null, null];
        }

        return [$ow['opening_from'], $ow['opening_to']];
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