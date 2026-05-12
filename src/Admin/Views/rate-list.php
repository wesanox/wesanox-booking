<?php
/**
 * Rate list view.
 *
 * @var \Wesanox\Booking\Domain\Rate\Rate[]  $rates
 * @var \Wesanox\Booking\Domain\Area\Area[]  $areas
 * @var int                                  $filter_area  active area filter (0 = all)
 * @var int                                  $saved        1 = saved, -1 = none
 * @var int                                  $deleted      1 = deleted, 0 = failed, -1 = none
 */

defined('ABSPATH') || exit;

$base_url   = admin_url('admin.php?page=rate-settings');
$create_url = add_query_arg('action', 'create', $base_url);
?>
<div class="wrap wsn-admin">
    <h1 class="wp-heading-inline"><?php echo esc_html__('Booking-Rates', 'wesanox-booking'); ?></h1>
    <a href="<?php echo esc_url($create_url); ?>" class="page-title-action">
        <?php echo esc_html__('Neue Rate', 'wesanox-booking'); ?>
    </a>
    <hr class="wp-header-end">

    <?php if ($saved === 1): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Rate wurde gespeichert.', 'wesanox-booking'); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($deleted === 1): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Rate wurde gelöscht.', 'wesanox-booking'); ?></p>
        </div>
    <?php elseif ($deleted === 0): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html__('Rate konnte nicht gelöscht werden.', 'wesanox-booking'); ?></p>
        </div>
    <?php endif; ?>

    <!-- Area filter -->
    <form method="get" style="margin-bottom:12px">
        <input type="hidden" name="page" value="rate-settings">
        <select name="filter_area" onchange="this.form.submit()">
            <option value="0"><?php echo esc_html__('Alle Areas', 'wesanox-booking'); ?></option>
            <?php foreach ($areas as $area): ?>
                <option value="<?php echo esc_attr((string) $area->id); ?>"
                        <?php selected($filter_area, $area->id); ?>>
                    <?php echo esc_html($area->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:50px"><?php echo esc_html__('ID', 'wesanox-booking'); ?></th>
                <th><?php echo esc_html__('Name', 'wesanox-booking'); ?></th>
                <th><?php echo esc_html__('Area', 'wesanox-booking'); ?></th>
                <th style="width:130px"><?php echo esc_html__('Von', 'wesanox-booking'); ?></th>
                <th style="width:130px"><?php echo esc_html__('Bis', 'wesanox-booking'); ?></th>
                <th><?php echo esc_html__('Tage', 'wesanox-booking'); ?></th>
                <th><?php echo esc_html__('Produkt-ID', 'wesanox-booking'); ?></th>
                <th><?php echo esc_html__('Variation-ID', 'wesanox-booking'); ?></th>
                <th style="width:80px"><?php echo esc_html__('Aktiv', 'wesanox-booking'); ?></th>
                <th style="width:60px"><?php echo esc_html__('Sortierung', 'wesanox-booking'); ?></th>
                <th><?php echo esc_html__('Aktionen', 'wesanox-booking'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rates)): ?>
                <tr>
                    <td colspan="11"><?php echo esc_html__('Keine Rates vorhanden.', 'wesanox-booking'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($rates as $rate): ?>
                    <?php
                    $edit_url = add_query_arg(['action' => 'edit', 'id' => $rate->id], $base_url);
                    ?>
                    <tr>
                        <td><?php echo esc_html((string) $rate->id); ?></td>
                        <td><strong><?php echo esc_html($rate->name); ?></strong></td>
                        <td><?php echo esc_html($rate->area_name ?? '—'); ?></td>
                        <td><?php echo esc_html($rate->time_from); ?></td>
                        <td><?php echo esc_html($rate->time_to === '00:00' ? '00:00 (Mitternacht)' : $rate->time_to); ?></td>
                        <td>
                            <?php
                            $short = ['monday' => 'Mo', 'tuesday' => 'Di', 'wednesday' => 'Mi',
                                      'thursday' => 'Do', 'friday' => 'Fr', 'saturday' => 'Sa', 'sunday' => 'So'];
                            $labels = array_intersect_key($short, array_flip($rate->days));
                            echo esc_html(implode(' ', $labels) ?: '—');
                            ?>
                        </td>
                        <td><?php echo esc_html((string) $rate->wc_product_id); ?></td>
                        <td><?php echo $rate->wc_variation_id ? esc_html((string) $rate->wc_variation_id) : '<em>—</em>'; ?></td>
                        <td>
                            <?php if ($rate->is_active): ?>
                                <span class="dashicons dashicons-yes-alt" style="color:#00a32a" title="Aktiv"></span>
                            <?php else: ?>
                                <span class="dashicons dashicons-dismiss" style="color:#d63638" title="Inaktiv"></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html((string) $rate->sort_order); ?></td>
                        <td>
                            <a href="<?php echo esc_url($edit_url); ?>" class="button button-small">
                                <?php echo esc_html__('Bearbeiten', 'wesanox-booking'); ?>
                            </a>
                            <form method="post"
                                  action="<?php echo esc_url(add_query_arg(['page' => 'rate-settings', 'action' => 'delete'], admin_url('admin.php'))); ?>"
                                  style="display:inline-block"
                                  onsubmit="return confirm('<?php echo esc_js(__('Rate wirklich löschen?', 'wesanox-booking')); ?>')">
                                <?php wp_nonce_field('wesanox_rate_delete_' . $rate->id, 'wesanox_nonce'); ?>
                                <input type="hidden" name="rate_id" value="<?php echo esc_attr((string) $rate->id); ?>">
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
</div>
