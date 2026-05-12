<?php
/**
 * Booking detail view.
 *
 * Available variables (set by BookingListPage::renderDetail):
 * @var \Wesanox\Booking\Domain\Booking\Booking|null $booking
 * @var string                                        $nonce_action
 * @var string                                        $nonce_field
 */

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Booking\BookingStatus;

$list_url = admin_url('admin.php?page=admin-booking-overview');
?>
<div class="wrap wsn-admin">
    <h1>
        <?php echo esc_html__('Buchung', 'wesanox-booking'); ?>
        <a href="<?php echo esc_url($list_url); ?>" class="page-title-action">
            &larr; <?php echo esc_html__('Zurück zur Übersicht', 'wesanox-booking'); ?>
        </a>
    </h1>

    <?php if ($booking === null): ?>
        <div class="notice notice-error">
            <p><?php echo esc_html__('Buchung nicht gefunden.', 'wesanox-booking'); ?></p>
        </div>
        <?php return; ?>
    <?php endif; ?>

    <table class="form-table widefat" style="max-width:640px">
        <tbody>
            <tr>
                <th><?php echo esc_html__('Buchungs-ID', 'wesanox-booking'); ?></th>
                <td><?php echo esc_html((string) $booking->id); ?></td>
            </tr>
            <tr>
                <th><?php echo esc_html__('Datum', 'wesanox-booking'); ?></th>
                <td><?php echo esc_html($booking->booking_date); ?></td>
            </tr>
            <tr>
                <th><?php echo esc_html__('Von', 'wesanox-booking'); ?></th>
                <td><?php echo esc_html(substr($booking->booking_from, 0, 5)); ?></td>
            </tr>
            <tr>
                <th><?php echo esc_html__('Bis', 'wesanox-booking'); ?></th>
                <td><?php echo esc_html(substr($booking->booking_to, 0, 5)); ?></td>
            </tr>
            <tr>
                <th><?php echo esc_html__('Raum', 'wesanox-booking'); ?></th>
                <td>
                    <?php echo $booking->room_name
                        ? esc_html($booking->room_name)
                        : '<em>' . esc_html__('Nicht zugeordnet', 'wesanox-booking') . '</em>'; ?>
                    <?php if ($booking->room_id): ?>
                        <small>(ID: <?php echo esc_html((string) $booking->room_id); ?>)</small>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php echo esc_html__('Kunde', 'wesanox-booking'); ?></th>
                <td>
                    <?php echo $booking->customer_name
                        ? esc_html($booking->customer_name)
                        : '<em>' . esc_html__('Unbekannt', 'wesanox-booking') . '</em>'; ?>
                    <?php if ($booking->wc_customer_id): ?>
                        <small>(ID: <?php echo esc_html((string) $booking->wc_customer_id); ?>)</small>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php echo esc_html__('WooCommerce Bestellung', 'wesanox-booking'); ?></th>
                <td>
                    <?php if ($booking->wc_order_id): ?>
                        <a href="<?php echo esc_url(get_edit_post_link($booking->wc_order_id) ?? '#'); ?>">
                            #<?php echo esc_html((string) $booking->wc_order_id); ?>
                        </a>
                    <?php else: ?>
                        <em><?php echo esc_html__('Keine Bestellung verknüpft', 'wesanox-booking'); ?></em>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php echo esc_html__('Status', 'wesanox-booking'); ?></th>
                <td>
                    <span class="order-status <?php echo esc_attr(BookingStatus::badgeClass($booking->status())); ?>">
                        <span><?php echo esc_html(BookingStatus::label($booking->status())); ?></span>
                    </span>
                </td>
            </tr>
        </tbody>
    </table>

    <?php if ($booking->isCancellable()): ?>
        <hr style="max-width:640px;margin-top:1.5em">

        <h2><?php echo esc_html__('Buchung stornieren', 'wesanox-booking'); ?></h2>

        <p class="description">
            <?php echo esc_html__('Durch Stornierung wird die verknüpfte WooCommerce-Bestellung auf "Storniert" gesetzt.', 'wesanox-booking'); ?>
        </p>

        <form method="post"
              action="<?php echo esc_url(admin_url('admin.php?page=admin-booking-overview')); ?>"
              onsubmit="return confirm('<?php echo esc_js(__('Buchung wirklich stornieren?', 'wesanox-booking')); ?>')">

            <?php wp_nonce_field($nonce_action, $nonce_field); ?>
            <input type="hidden" name="booking_id" value="<?php echo esc_attr((string) $booking->id); ?>">

            <button type="submit"
                    name="wesanox_cancel_booking"
                    class="button button-secondary"
                    style="color:#b32d2e;border-color:#b32d2e">
                <?php echo esc_html__('Buchung stornieren', 'wesanox-booking'); ?>
            </button>
        </form>
    <?php endif; ?>
</div>
