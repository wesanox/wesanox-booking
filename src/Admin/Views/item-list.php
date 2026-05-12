<?php
/**
 * Item list view.
 *
 * @var \Wesanox\Booking\Domain\Item\Item[]             $items
 * @var \Wesanox\Booking\Domain\Area\Area[]             $areas
 * @var \Wesanox\Booking\Domain\ItemCategory\ItemCategory[] $categories
 * @var int|null                                         $filter_area
 * @var int|null                                         $filter_category
 * @var bool|null                                        $filter_inactive
 * @var int                                              $saved   1 = saved, -1 = none
 * @var int                                              $deleted 1 = deleted, 0 = failed, -1 = none
 */

defined('ABSPATH') || exit;

$base_url   = admin_url('admin.php?page=room-settings');
$create_url = add_query_arg('action', 'create', $base_url);
?>
<div class="wrap wsn-admin">
    <h1><?php echo esc_html__('Items', 'wesanox-booking'); ?></h1>
    <hr class="wp-header-end">

    <?php if ($saved === 1): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Item wurde gespeichert.', 'wesanox-booking'); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($deleted === 1): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Item wurde gelöscht.', 'wesanox-booking'); ?></p>
        </div>
    <?php elseif ($deleted === 0): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html__('Item konnte nicht gelöscht werden.', 'wesanox-booking'); ?></p>
        </div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin-bottom:1em">
        <input type="hidden" name="page" value="room-settings">

        <select name="filter_area">
            <option value=""><?php echo esc_html__('Alle Areas', 'wesanox-booking'); ?></option>
            <?php foreach ($areas as $area): ?>
                <option value="<?php echo esc_attr((string) $area->id); ?>"
                        <?php selected($filter_area, $area->id); ?>>
                    <?php echo esc_html($area->name); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="filter_category">
            <option value=""><?php echo esc_html__('Alle Kategorien', 'wesanox-booking'); ?></option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo esc_attr((string) $cat->id); ?>"
                        <?php selected($filter_category, $cat->id); ?>>
                    <?php echo esc_html($cat->name); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="filter_inactive">
            <option value=""><?php echo esc_html__('Alle Status', 'wesanox-booking'); ?></option>
            <option value="0" <?php selected($filter_inactive, false); ?>><?php echo esc_html__('Aktiv', 'wesanox-booking'); ?></option>
            <option value="1" <?php selected($filter_inactive, true); ?>><?php echo esc_html__('Inaktiv', 'wesanox-booking'); ?></option>
        </select>

        <button type="submit" class="button"><?php echo esc_html__('Filtern', 'wesanox-booking'); ?></button>
        <a href="<?php echo esc_url($base_url); ?>" class="button"><?php echo esc_html__('Zurücksetzen', 'wesanox-booking'); ?></a>
    </form>

    <div class="wsn-table-toolbar">
        <a href="<?php echo esc_url($create_url); ?>" class="button button-primary button-small">
            + <?php echo esc_html__('Neues Item', 'wesanox-booking'); ?>
        </a>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:50px"><?php echo esc_html__('ID', 'wesanox-booking'); ?></th>
                <th><?php echo esc_html__('Name', 'wesanox-booking'); ?></th>
                <th><?php echo esc_html__('Kategorie', 'wesanox-booking'); ?></th>
                <th><?php echo esc_html__('Area', 'wesanox-booking'); ?></th>
                <th style="width:80px"><?php echo esc_html__('Status', 'wesanox-booking'); ?></th>
                <th><?php echo esc_html__('Inaktiv von / bis', 'wesanox-booking'); ?></th>
                <th><?php echo esc_html__('Aktionen', 'wesanox-booking'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="7"><?php echo esc_html__('Keine Items gefunden.', 'wesanox-booking'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <?php $edit_url = add_query_arg(['action' => 'edit', 'id' => $item->id], $base_url); ?>
                    <tr>
                        <td><?php echo esc_html((string) $item->id); ?></td>
                        <td><strong><?php echo esc_html($item->name); ?></strong></td>
                        <td>
                            <?php echo $item->category_name
                                ? esc_html($item->category_name)
                                : '<em>' . esc_html__('—', 'wesanox-booking') . '</em>'; ?>
                        </td>
                        <td>
                            <?php echo $item->area_name
                                ? esc_html($item->area_name)
                                : '<em>' . esc_html__('—', 'wesanox-booking') . '</em>'; ?>
                        </td>
                        <td>
                            <?php if ($item->inactive): ?>
                                <span class="order-status order-status-cancelled">
                                    <span><?php echo esc_html__('Inaktiv', 'wesanox-booking'); ?></span>
                                </span>
                            <?php else: ?>
                                <span class="order-status order-status-completed">
                                    <span><?php echo esc_html__('Aktiv', 'wesanox-booking'); ?></span>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($item->inactiv_from || $item->inactiv_to): ?>
                                <?php echo esc_html(($item->inactiv_from ?? '?') . ' – ' . ($item->inactiv_to ?? '?')); ?>
                            <?php else: ?>
                                <em><?php echo esc_html__('—', 'wesanox-booking'); ?></em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url($edit_url); ?>" class="button button-small">
                                <?php echo esc_html__('Bearbeiten', 'wesanox-booking'); ?>
                            </a>
                            <form method="post"
                                  action="<?php echo esc_url(add_query_arg(['page' => 'room-settings', 'action' => 'delete'], admin_url('admin.php'))); ?>"
                                  style="display:inline-block"
                                  onsubmit="return confirm('<?php echo esc_js(__('Item wirklich löschen?', 'wesanox-booking')); ?>')">
                                <?php wp_nonce_field('wesanox_item_delete_' . $item->id, 'wesanox_nonce'); ?>
                                <input type="hidden" name="item_id" value="<?php echo esc_attr((string) $item->id); ?>">
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
        <?php echo esc_html__('Neues Item', 'wesanox-booking'); ?>
    </a>
</div>
