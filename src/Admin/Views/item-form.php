<?php
/**
 * Item create / edit form.
 *
 * @var \Wesanox\Booking\Domain\Item\Item|null          $item        null = create form
 * @var \Wesanox\Booking\Domain\Item\Item|null          $posted      recovered POST values on validation failure
 * @var \Wesanox\Booking\Domain\Area\Area[]             $areas
 * @var \Wesanox\Booking\Domain\ItemCategory\ItemCategory[] $categories
 * @var string[]                                         $errors
 * @var string                                           $nonce_action
 * @var string                                           $nonce_field
 */

defined('ABSPATH') || exit;

$is_edit     = $item !== null && $item->id > 0;
$base_url    = admin_url('admin.php?page=room-settings');
$form_action = add_query_arg(
    $is_edit ? ['action' => 'edit', 'id' => $item->id] : ['action' => 'create'],
    admin_url('admin.php?page=room-settings')
);

// Data source: posted values > existing record > empty defaults.
$src = $posted ?? $item;
?>
<div class="wrap wsn-admin">
    <h1><?php echo $is_edit
        ? esc_html__('Item bearbeiten', 'wesanox-booking')
        : esc_html__('Neues Item anlegen', 'wesanox-booking');
    ?></h1>

    <a href="<?php echo esc_url($base_url); ?>" class="wsn-back">&larr; <?php echo esc_html__('Zurück zur Übersicht', 'wesanox-booking'); ?></a>

    <?php if (!empty($errors)): ?>
        <div class="notice notice-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo esc_html($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url($form_action); ?>">
        <?php wp_nonce_field($nonce_action, $nonce_field); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="item_name"><?php echo esc_html__('Name', 'wesanox-booking'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text"
                           id="item_name"
                           name="item_name"
                           class="regular-text"
                           required
                           value="<?php echo esc_attr($src ? $src->name : ''); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="item_category_id"><?php echo esc_html__('Kategorie', 'wesanox-booking'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <select id="item_category_id" name="item_category_id" required>
                        <option value=""><?php echo esc_html__('— Kategorie wählen —', 'wesanox-booking'); ?></option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo esc_attr((string) $cat->id); ?>"
                                    <?php selected($src ? $src->item_category_id : null, $cat->id); ?>>
                                <?php echo esc_html($cat->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="area_id"><?php echo esc_html__('Area (optional)', 'wesanox-booking'); ?></label>
                </th>
                <td>
                    <select id="area_id" name="area_id">
                        <option value=""><?php echo esc_html__('— Keine Area —', 'wesanox-booking'); ?></option>
                        <?php foreach ($areas as $area): ?>
                            <option value="<?php echo esc_attr((string) $area->id); ?>"
                                    <?php selected($src ? $src->area_id : null, $area->id); ?>>
                                <?php echo esc_html($area->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>

        <h2 class="title"><?php echo esc_html__('Inaktivität', 'wesanox-booking'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php echo esc_html__('Inaktiv', 'wesanox-booking'); ?></th>
                <td>
                    <label>
                        <input type="checkbox"
                               name="inactive"
                               value="1"
                               <?php checked($src ? $src->inactive : false, true); ?>>
                        <?php echo esc_html__('Item als inaktiv markieren', 'wesanox-booking'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="inactiv_from"><?php echo esc_html__('Inaktiv von', 'wesanox-booking'); ?></label>
                </th>
                <td>
                    <input type="date"
                           id="inactiv_from"
                           name="inactiv_from"
                           value="<?php echo esc_attr($src ? ($src->inactiv_from ?? '') : ''); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="inactiv_to"><?php echo esc_html__('Inaktiv bis', 'wesanox-booking'); ?></label>
                </th>
                <td>
                    <input type="date"
                           id="inactiv_to"
                           name="inactiv_to"
                           value="<?php echo esc_attr($src ? ($src->inactiv_to ?? '') : ''); ?>">
                    <p class="description">
                        <?php echo esc_html__('"Inaktiv bis" darf nicht vor "Inaktiv von" liegen.', 'wesanox-booking'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="inactiv_note"><?php echo esc_html__('Inaktiv-Notiz', 'wesanox-booking'); ?></label>
                </th>
                <td>
                    <textarea id="inactiv_note" name="inactiv_note" rows="3" class="large-text"><?php
                        echo esc_textarea($src ? ($src->inactiv_note ?? '') : '');
                    ?></textarea>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" class="button-primary"
                   value="<?php echo $is_edit
                       ? esc_attr__('Änderungen speichern', 'wesanox-booking')
                       : esc_attr__('Item anlegen', 'wesanox-booking'); ?>">
        </p>
    </form>
</div>
