<?php
/**
 * Holiday list view.
 *
 * @var \Wesanox\Booking\Domain\Holiday\Holiday[] $holidays
 * @var \Wesanox\Booking\Domain\Area\Area[]        $areas
 * @var int                                        $saved   1 = saved, -1 = none
 * @var int                                        $deleted 1 = deleted, 0 = failed, -1 = none
 */

defined('ABSPATH') || exit;

// Build area-id → name map for display.
$area_names = [];
foreach ($areas as $area) {
    $area_names[$area->id] = $area->name;
}

$base_url   = admin_url('admin.php?page=holiday-settings');
$create_url = add_query_arg('action', 'create', $base_url);
?>
<div class="wrap wsn-admin">
    <h1><?php echo esc_html__('Feiertage & Sondertage', 'wesanox-booking'); ?></h1>
    <hr class="wp-header-end">

    <?php if ($saved === 1): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Eintrag wurde gespeichert.', 'wesanox-booking'); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($deleted === 1): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Eintrag wurde gelöscht.', 'wesanox-booking'); ?></p>
        </div>
    <?php elseif ($deleted === 0): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html__('Eintrag konnte nicht gelöscht werden.', 'wesanox-booking'); ?></p>
        </div>
    <?php endif; ?>

    <div class="wsn-table-toolbar">
        <a href="<?php echo esc_url($create_url); ?>" class="button button-primary button-small">
            + <?php echo esc_html__('Neuer Eintrag', 'wesanox-booking'); ?>
        </a>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:50px"><?php echo esc_html__('ID', 'wesanox-booking'); ?></th>
                <th style="width:130px"><?php echo esc_html__('Datum', 'wesanox-booking'); ?></th>
                <th style="width:100px"><?php echo esc_html__('Von', 'wesanox-booking'); ?></th>
                <th style="width:100px"><?php echo esc_html__('Bis', 'wesanox-booking'); ?></th>
                <th style="width:90px"><?php echo esc_html__('Geschlossen', 'wesanox-booking'); ?></th>
                <th style="width:110px"><?php echo esc_html__('Feiertagspreis', 'wesanox-booking'); ?></th>
                <th style="width:130px"><?php echo esc_html__('Area', 'wesanox-booking'); ?></th>
                <th><?php echo esc_html__('Aktionen', 'wesanox-booking'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($holidays)): ?>
                <tr>
                    <td colspan="7"><?php echo esc_html__('Keine Einträge vorhanden.', 'wesanox-booking'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($holidays as $holiday): ?>
                    <?php
                    $edit_url = add_query_arg(['action' => 'edit', 'id' => $holiday->id], $base_url);
                    ?>
                    <tr>
                        <td><?php echo esc_html((string) $holiday->id); ?></td>
                        <td><strong><?php echo esc_html($holiday->dateLabel()); ?></strong></td>
                        <td><?php echo $holiday->isClosed() ? '<em>—</em>' : esc_html($holiday->opening_from ?? '—'); ?></td>
                        <td><?php echo $holiday->isClosed() ? '<em>—</em>' : esc_html($holiday->opening_to   ?? '—'); ?></td>
                        <td>
                            <?php if ($holiday->isClosed()): ?>
                                <span class="dashicons dashicons-yes" style="color:#d63638"></span>
                            <?php else: ?>
                                <span style="color:#ccc">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($holiday->isHolidayPricing()): ?>
                                <span class="dashicons dashicons-yes" style="color:#00a32a"></span>
                            <?php else: ?>
                                <span style="color:#ccc">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($holiday->area_id !== null && isset($area_names[$holiday->area_id])): ?>
                                <?php echo esc_html($area_names[$holiday->area_id]); ?>
                            <?php else: ?>
                                <em><?php echo esc_html__('Alle Areas', 'wesanox-booking'); ?></em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url($edit_url); ?>" class="button button-small">
                                <?php echo esc_html__('Bearbeiten', 'wesanox-booking'); ?>
                            </a>
                            <form method="post"
                                  action="<?php echo esc_url(add_query_arg(['page' => 'holiday-settings', 'action' => 'delete'], admin_url('admin.php'))); ?>"
                                  style="display:inline-block"
                                  onsubmit="return confirm('<?php echo esc_js(__('Eintrag wirklich löschen?', 'wesanox-booking')); ?>')">
                                <?php wp_nonce_field('wesanox_holiday_delete_' . $holiday->id, 'wesanox_nonce'); ?>
                                <input type="hidden" name="holiday_id" value="<?php echo esc_attr((string) $holiday->id); ?>">
                                <button type="submit" class="button button-small button-link-delete">
                                    <?php echo esc_html__('Löschen', 'wesanox-booking'); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <a href="<?php echo esc_url($create_url); ?>" class="wsn-add-entry-btn">
        <span class="wsn-add-entry-btn__icon">+</span>
        <?php echo esc_html__('Neuen Eintrag', 'wesanox-booking'); ?>
    </a>
</div>
