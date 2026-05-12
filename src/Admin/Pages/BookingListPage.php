<?php

declare(strict_types=1);

namespace Wesanox\Booking\Admin\Pages;

defined('ABSPATH') || exit;

use Wesanox\Booking\Application\Booking\CancelBookingService;
use Wesanox\Booking\Application\Booking\GetBookingService;
use Wesanox\Booking\Application\Booking\ListBookingsService;
use Wesanox\Booking\Domain\Booking\BookingStatus;

/**
 * Main admin booking page controller.
 * Handles both list view and detail view (via ?action=view).
 * All cancel actions are handled here via POST before rendering.
 */
final class BookingListPage
{
    private const NONCE_ACTION_CANCEL = 'wesanox_cancel_booking';
    private const NONCE_FIELD         = 'wesanox_cancel_nonce';

    public function __construct(
        private ListBookingsService   $list_service,
        private GetBookingService     $get_service,
        private CancelBookingService  $cancel_service,
    ) {
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung.', 'wesanox-booking'));
        }

        $this->handlePostActions();

        $action = sanitize_key($_GET['action'] ?? '');

        if ($action === 'view') {
            $this->renderDetail();
        } else {
            $this->renderList();
        }
    }

    private function handlePostActions(): void
    {
        if (!isset($_POST['wesanox_cancel_booking'])) {
            return;
        }

        $booking_id = absint($_POST['booking_id'] ?? 0);

        check_admin_referer(self::NONCE_ACTION_CANCEL . '_' . $booking_id, self::NONCE_FIELD);

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung.', 'wesanox-booking'));
        }

        $success = $this->cancel_service->execute($booking_id);

        $redirect = add_query_arg(
            [
                'page'      => 'admin-booking-overview',
                'cancelled' => $success ? '1' : '0',
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }

    private function renderList(): void
    {
        $status   = sanitize_key($_GET['status_filter'] ?? '');
        $bookings = $this->list_service->execute($status ?: null);
        $statuses = BookingStatus::all();

        $cancelled = isset($_GET['cancelled']) ? absint($_GET['cancelled']) : -1;

        require __DIR__ . '/../Views/booking-list.php';
    }

    private function renderDetail(): void
    {
        $booking_id = absint($_GET['booking_id'] ?? 0);
        $booking    = $this->get_service->execute($booking_id);

        $nonce_action = self::NONCE_ACTION_CANCEL . '_' . $booking_id;
        $nonce_field  = self::NONCE_FIELD;

        require __DIR__ . '/../Views/booking-detail.php';
    }
}
