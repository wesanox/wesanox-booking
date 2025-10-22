<?php

namespace Wesanox\Booking\Service;

defined( 'ABSPATH' )|| exit;

class ServiceGetHolidayPrices
{
    public function wesanox_get_day_price( string $input_date ): int {
        global $wpdb;

        $table = $wpdb->prefix . 'wesanox_holidays';

        $normalize_date = $this->wesanox_normalize_date($input_date);

        if (!$normalize_date) return 0;

        $price = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT opening_holiday 
                     FROM {$table}
                     WHERE opening_date = %s
                     LIMIT 1",
                    $normalize_date
            )
        );

        return is_null($price) ? 0 : (int) $price;
    }

    private function wesanox_normalize_date( string $date ): ?string
    {
        $date = trim($date);

        if ($date === '') return null;

        $dt = \DateTime::createFromFormat('d.m.Y', $date);

        if ($dt && $dt->format('d.m.Y') === $date) return $dt->format('Y-m-d');

        $ts = strtotime($date);

        return $ts ? date('Y-m-d', $ts) : null;
    }
}