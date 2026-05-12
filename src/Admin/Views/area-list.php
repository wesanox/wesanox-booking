<?php
/**
 * Area list view.
 *
 * @var \Wesanox\Booking\Domain\Area\Area[] $areas
 * @var int                                 $saved   1 = saved, -1 = none
 * @var int                                 $deleted 1 = deleted, 0 = failed, -1 = none
 */

defined('ABSPATH') || exit;

$base_url   = admin_url('admin.php?page=area-settings');
$create_url = add_query_arg('action', 'create', $base_url);
?>
<div class="wrap wsn-admin">
    <h1><?php echo esc_html__('Areas', 'wesanox-booking'); ?></h1>
    <hr class="wp-header-end">

    <?php if ($saved === 1): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Area wurde gespeichert.', 'wesanox-booking'); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($deleted === 1): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Area wurde gelöscht.', 'wesanox-booking'); ?></p>
        </div>
    <?php elseif ($deleted === 0): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html__('Area konnte nicht gelöscht werden.', 'wesanox-booking'); ?></p>
        </div>
    <?php endif; ?>

    <div class="wsn-table-toolbar">
        <a href="<?php echo esc_url($create_url); ?>" class="button button-primary button-small">
            + <?php echo esc_html__('Neue Area', 'wesanox-booking'); ?>
        </a>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:50px"><?php echo esc_html__('ID', 'wesanox-booking'); ?></th>
                <th><?php echo esc_html__('Name', 'wesanox-booking'); ?></th>
                <th><?php echo esc_html__('Öffnungszeiten', 'wesanox-booking'); ?></th>
                <th style="width:140px"><?php echo esc_html__('Buchungstyp', 'wesanox-booking'); ?></th>
                <th><?php echo esc_html__('Shortcode', 'wesanox-booking'); ?></th>
                <th><?php echo esc_html__('Aktionen', 'wesanox-booking'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($areas)): ?>
                <tr>
                    <td colspan="6"><?php echo esc_html__('Keine Areas vorhanden.', 'wesanox-booking'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($areas as $area): ?>
                    <?php
                    $edit_url   = add_query_arg(['action' => 'edit', 'id' => $area->id], $base_url);
                    $opening    = $area->openingData();
                    $days_label = [];
                    $day_abbr   = [
                        'monday'    => 'Mo',
                        'tuesday'   => 'Di',
                        'wednesday' => 'Mi',
                        'thursday'  => 'Do',
                        'friday'    => 'Fr',
                        'saturday'  => 'Sa',
                        'sunday'    => 'So',
                    ];
                    foreach ($opening as $day => $data) {
                        if ($data['enabled']) {
                            $days_label[] = ($day_abbr[$day] ?? $day)
                                . ' ' . esc_html($data['from'] ?? '')
                                . '–' . esc_html($data['to'] ?? '');
                        }
                    }
                    ?>
                    <?php
                    $bs      = $area->bookingSettingsData();
                    $type_labels = ['standard' => 'Standard', 'suite' => 'Suite', 'timeslot' => 'Zeitslot'];
                    $snippet_val = $area->shortcodeSnippet();
                    ?>
                    <tr>
                        <td><?php echo esc_html((string) $area->id); ?></td>
                        <td><strong><?php echo esc_html($area->name); ?></strong></td>
                        <td>
                            <?php if (empty($days_label)): ?>
                                <em><?php echo esc_html__('—', 'wesanox-booking'); ?></em>
                            <?php else: ?>
                                <?php echo esc_html(implode(', ', $days_label)); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($type_labels[$bs['booking_type']] ?? $bs['booking_type']); ?></td>
                        <td>
                            <code style="font-size:11px;white-space:nowrap">
                                <?php echo esc_html($snippet_val); ?>
                            </code>
                        </td>
                        <td>
                            <a href="<?php echo esc_url($edit_url); ?>" class="button button-small">
                                <?php echo esc_html__('Bearbeiten', 'wesanox-booking'); ?>
                            </a>
                            <form method="post"
                                  action="<?php echo esc_url(add_query_arg(['page' => 'area-settings', 'action' => 'delete'], admin_url('admin.php'))); ?>"
                                  style="display:inline-block"
                                  onsubmit="return confirm('<?php echo esc_js(__('Area wirklich löschen?', 'wesanox-booking')); ?>')">
                                <?php wp_nonce_field('wesanox_area_delete_' . $area->id, 'wesanox_nonce'); ?>
                                <input type="hidden" name="area_id" value="<?php echo esc_attr((string) $area->id); ?>">
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
        <?php echo esc_html__('Neue Area', 'wesanox-booking'); ?>
    </a>
</div>
