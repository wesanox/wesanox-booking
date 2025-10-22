<?php

namespace Wesanox\Booking\View\Admin\Edit;

defined( 'ABSPATH' )|| exit;

class EditBooking
{
    public function __construct()
    {
        add_action('wp_ajax_import_old_bookings', [$this, 'wesanox_ajax_import_old_bookings']);
    }

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
                <button id="import_old_bookings" class="button button-primary mt">Importiere alte Buchungen</button>
            </div>';

        echo $html;
    }

    public function wesanox_ajax_import_old_bookings()
    {
        $response = [
            'status' => 'success',
            'message' => 'Import erfolgreich',
        ];
    }
}