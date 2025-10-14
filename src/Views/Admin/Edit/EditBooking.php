<?php

namespace Wesanox\Booking\Views\Admin\Edit;

defined( 'ABSPATH' )|| exit;

class EditBooking
{
    /**
     * @return void
     */
    public function wesanox_admin_edit_booking_render(): void
    {
        $html = '
            <div class="wrap medi-booking-tool-admin">
                <h1>Buchungstool</h1>
                <p class="mb-2">Das Buchungs - TOOL hilft dir dabei, deine Buchungen und Räume zu verwalten und auch anzulegen</p>

                <button id="show_booking_modal" class="button button-primary mt">Neue Buchung</button>
            </div>';

        echo $html;
    }
}