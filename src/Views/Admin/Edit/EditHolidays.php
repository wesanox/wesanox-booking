<?php

namespace Wesanox\Booking\Views\Admin\Edit;

defined('ABSPATH') || exit;

class EditHolidays
{
    /**
     * @return void
     */
    public function wesanox_admin_edit_holiday_render(): void
    {
        $html = '
            <div class="wrap medi-booking-tool-admin">
                <h1>Buchungstool</h1>
                <p class="mb-2">Das Buchungssdafdsfaadsf - TOOL hilft dir dabei, deine Buchungen und Räume zu verwalten und auch anzulegen</p>
                ' . $this->render_holiday_table() . '
                <button id="show_booking_modal" class="button button-primary mt">Feiertag</button><button id="show_booking_modal" class="button button-primary mt">Feiertag synchronisieren</button>
            </div>';

        echo $html;
    }

    private function render_holiday_table()
    {
        return 'test';
    }
}