<?php

namespace Wesanox\Booking\Service;

defined( 'ABSPATH' )|| exit;

use DateTime;

class ServiceBookingTime
{
    private $time_format;
    private $time_cutoff;
    public $times_cutoffs;

    protected ServiceGetAvailableTimes $available_times;

    public function __construct()
    {
        $this->time_format = 'H:i';
        $this->time_cutoff = '16:00';
        $this->times_cutoffs = [];

        $this->available_times = new ServiceGetAvailableTimes();
    }

    /**
     * Calculates the difference in hours between the booking start and end times and the cutoff time. In this case it is 16:00.
     *
     * @param string $booking_start The start time of the booking, formatted as a time string.
     * @param string $booking_end The end time of the booking, formatted as a time string.
     *
     * @return string The JSON-encoded string representing the difference in hours between the start and end time.
     * @throws \DateMalformedStringException
     */
    public function getBookingTimeDifferenceBetween($booking_start, $booking_end) : string
    {
        $start = new DateTime( date('Y-m-d H:i', strtotime($booking_start)) );
        $end   = new DateTime( date('Y-m-d H:i', strtotime($booking_end)) );

        if ($end <= $start) {
            $this->times_cutoffs = [
                'vth'  => 0, 'vtm'  => 0, 'vtmf' => 0.0,
                'nth'  => 0, 'ntm'  => 0, 'ntmf' => 0.0,
            ];
            return json_encode($this->times_cutoffs);
        }

        $cutoff = (clone $start)->setTime(16, 0, 0);

        $vm_minutes = 0;

        if ($start < $cutoff) {
            $vm_end = ($end < $cutoff) ? $end : $cutoff;
            $vm_minutes = max(0, (int) round(($vm_end->getTimestamp() - $start->getTimestamp()) / 60));
        }

        $nm_minutes = 0;

        if ($end > $cutoff) {
            $nm_start = ($start > $cutoff) ? $start : $cutoff;
            $nm_minutes = max(0, (int) round(($end->getTimestamp() - $nm_start->getTimestamp()) / 60));
        }

        $vh  = intdiv($vm_minutes, 60);
        $vtm = $vm_minutes % 60;

        $nh  = intdiv($nm_minutes, 60);
        $ntm = $nm_minutes % 60;

        $vtmf = $this->minutesToQuarterFloat($vtm);
        $ntmf = $this->minutesToQuarterFloat($ntm);

        $this->times_cutoffs = [
            'vth'  => $vh,
            'vtm'  => $vtm,
            'vtmf' => $vtmf,
            'nth'  => $nh,
            'ntm'  => $ntm,
            'ntmf' => $ntmf,
        ];

        return json_encode($this->times_cutoffs);
    }

    /**
     * Generates HTML code for displaying available times for a selected day and start date.
     *
     * @param string $selected_day The selected day in the format 'Y-m-d'.
     * @param string $start_date The start date in the format 'd.m.Y H:i'.
     *
     * @return string The generated HTML code.
     */
    public function getTimeSeparatet( $selected_day , $start_date ) :string
    {
        $html = '';

        $times = $this->available_times->wesanox_get_available_times(date('Y-m-d' , strtotime($selected_day)));

        foreach ($times as $time) {
            foreach ($time as $value) {
                $timestamp = date('H:i', strtotime($selected_day . ' ' . $value));

                $active = ($timestamp == $start_date) ? ' active' : '';

                $html .= '
                    <div class="col-6 bg-white p-1">
                        <div class="text-center p-2 time-box' . $active . '" data-start-time="' . $timestamp . '">
                            ' . $value . '
                        </div>
                    </div>';
            }
        }

        return $html;
    }

    /**
     * Set the minutes to a float format for the cart calculation
     *
     * @param int $minutes
     * @return float
     */
    private function minutesToQuarterFloat(int $minutes) : float
    {
        $rounded = (int) (round($minutes / 15) * 15);
        if ($rounded >= 60) { $rounded = 45; }

        switch ($rounded) {
            case 15: return 0.25;
            case 30: return 0.50;
            case 45: return 0.75;
            default: return 0.0;
        }
    }
}