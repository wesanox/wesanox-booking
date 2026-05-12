<?php
/**
 * Booking list view.
 *
 * Available variables (set by BookingListPage::renderList):
 * @var \Wesanox\Booking\Domain\Booking\Booking[] $bookings
 * @var array<string, string>                     $statuses
 * @var string                                    $status
 * @var int                                       $cancelled  1 = success, 0 = failed, -1 = none
 */

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Booking\BookingStatus;

$base_url   = admin_url('admin.php?page=admin-booking-overview');
$detail_url = admin_url('admin.php?page=admin-booking-overview&action=view&booking_id=');
?>
<div class="wrap wsn-admin">
    <h1><?php echo esc_html__('Buchungen', 'wesanox-booking'); ?></h1>

    <?php if ($cancelled === 1): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Buchung wurde erfolgreich storniert.', 'wesanox-booking'); ?></p>
        </div>
    <?php elseif ($cancelled === 0): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html__('Stornierung fehlgeschlagen. Die Buchung konnte nicht storniert werden.', 'wesanox-booking'); ?></p>
        </div>
    <?php endif; ?>

    <ul class="subsubsub">
        <li>
            <a href="<?php echo esc_url($base_url); ?>"
               class="<?php echo $status === '' ? 'current' : ''; ?>">
                <?php echo esc_html__('Alle', 'wesanox-booking'); ?>
            </a>
        </li>
        <?php foreach ($statuses as $key => $label): ?>
            <li> |
                <a href="<?php echo esc_url(add_query_arg('status_filter', $key, $base_url)); ?>"
                   class="<?php echo $status === $key ? 'current' : ''; ?>">
                    <?php echo esc_html($label); ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" style="width:50px"><?php echo esc_html__('ID', 'wesanox-booking'); ?></th>
                <th scope="col"><?php echo esc_html__('Datum', 'wesanox-booking'); ?></th>
                <th scope="col"><?php echo esc_html__('Von', 'wesanox-booking'); ?></th>
                <th scope="col"><?php echo esc_html__('Bis', 'wesanox-booking'); ?></th>
                <th scope="col"><?php echo esc_html__('Raum', 'wesanox-booking'); ?></th>
                <th scope="col"><?php echo esc_html__('Kunde', 'wesanox-booking'); ?></th>
                <th scope="col"><?php echo esc_html__('Bestellung', 'wesanox-booking'); ?></th>
                <th scope="col"><?php echo esc_html__('Status', 'wesanox-booking'); ?></th>
                <th scope="col"><?php echo esc_html__('Aktionen', 'wesanox-booking'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($bookings)): ?>
                <tr>
                    <td colspan="9">
                        <?php echo esc_html__('Keine Buchungen gefunden.', 'wesanox-booking'); ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <td><?php echo esc_html((string) $booking->id); ?></td>
                        <td><?php echo esc_html($booking->booking_date); ?></td>
                        <td><?php echo esc_html(substr($booking->booking_from, 0, 5)); ?></td>
                        <td><?php echo esc_html(substr($booking->booking_to, 0, 5)); ?></td>
                        <td>
                            <?php echo $booking->room_name
                                ? esc_html($booking->room_name)
                                : '<em>' . esc_html__('—', 'wesanox-booking') . '</em>'; ?>
                        </td>
                        <td>
                            <?php echo $booking->customer_name
                                ? esc_html($booking->customer_name)
                                : '<em>' . esc_html__('—', 'wesanox-booking') . '</em>'; ?>
                        </td>
                        <td>
                            <?php if ($booking->wc_order_id): ?>
                                <a href="<?php echo esc_url(get_edit_post_link($booking->wc_order_id) ?? '#'); ?>">
                                    #<?php echo esc_html((string) $booking->wc_order_id); ?>
                                </a>
                            <?php else: ?>
                                <em><?php echo esc_html__('—', 'wesanox-booking'); ?></em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="order-status <?php echo esc_attr(BookingStatus::badgeClass($booking->status())); ?>">
                                <span><?php echo esc_html(BookingStatus::label($booking->status())); ?></span>
                            </span>
                        </td>
                        <td>
                            <a href="<?php echo esc_url($detail_url . $booking->id); ?>"
                               class="button button-small">
                                <?php echo esc_html__('Details', 'wesanox-booking'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
