<?php
/**
 * Item category list view.
 *
 * @var \Wesanox\Booking\Domain\ItemCategory\ItemCategory[] $categories
 * @var int                                                  $saved   1 = saved, -1 = none
 * @var int                                                  $deleted 1 = deleted, 0 = failed, -1 = none
 */

defined('ABSPATH') || exit;

$base_url   = admin_url('admin.php?page=item-category-settings');
$create_url = add_query_arg('action', 'create', $base_url);
?>
<div class="wrap wsn-admin">
    <h1><?php echo esc_html__('Item-Kategorien', 'wesanox-booking'); ?></h1>
    <hr class="wp-header-end">

    <?php if ($saved === 1): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Kategorie wurde gespeichert.', 'wesanox-booking'); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($deleted === 1): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Kategorie wurde gelöscht.', 'wesanox-booking'); ?></p>
        </div>
    <?php elseif ($deleted === 0): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html__('Kategorie konnte nicht gelöscht werden.', 'wesanox-booking'); ?></p>
        </div>
    <?php endif; ?>

    <div class="wsn-table-toolbar">
        <a href="<?php echo esc_url($create_url); ?>" class="button button-primary button-small">
            + <?php echo esc_html__('Neue Kategorie', 'wesanox-booking'); ?>
        </a>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:50px"><?php echo esc_html__('ID', 'wesanox-booking'); ?></th>
                <th><?php echo esc_html__('Name', 'wesanox-booking'); ?></th>
                <th><?php echo esc_html__('Aktionen', 'wesanox-booking'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($categories)): ?>
                <tr>
                    <td colspan="3"><?php echo esc_html__('Keine Kategorien vorhanden.', 'wesanox-booking'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($categories as $category): ?>
                    <?php $edit_url = add_query_arg(['action' => 'edit', 'id' => $category->id], $base_url); ?>
                    <tr>
                        <td><?php echo esc_html((string) $category->id); ?></td>
                        <td><strong><?php echo esc_html($category->name); ?></strong></td>
                        <td>
                            <a href="<?php echo esc_url($edit_url); ?>" class="button button-small">
                                <?php echo esc_html__('Bearbeiten', 'wesanox-booking'); ?>
                            </a>
                            <form method="post"
                                  action="<?php echo esc_url(add_query_arg(['page' => 'item-category-settings', 'action' => 'delete'], admin_url('admin.php'))); ?>"
                                  style="display:inline-block"
                                  onsubmit="return confirm('<?php echo esc_js(__('Kategorie wirklich löschen?', 'wesanox-booking')); ?>')">
                                <?php wp_nonce_field('wesanox_item_category_delete_' . $category->id, 'wesanox_nonce'); ?>
                                <input type="hidden" name="category_id" value="<?php echo esc_attr((string) $category->id); ?>">
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
        <?php echo esc_html__('Neue Kategorie', 'wesanox-booking'); ?>
    </a>
</div>
