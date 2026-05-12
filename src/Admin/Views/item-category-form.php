<?php
/**
 * Item category create / edit form.
 *
 * @var \Wesanox\Booking\Domain\ItemCategory\ItemCategory|null $category    null = create form
 * @var string|null                                             $posted_name recovered POST name on validation failure
 * @var string[]                                                $errors
 * @var string                                                  $nonce_action
 * @var string                                                  $nonce_field
 */

defined('ABSPATH') || exit;

$is_edit    = $category !== null && $category->id > 0;
$base_url   = admin_url('admin.php?page=item-category-settings');
$form_action = add_query_arg(
    $is_edit ? ['action' => 'edit', 'id' => $category->id] : ['action' => 'create'],
    admin_url('admin.php?page=item-category-settings')
);

$name_value = $posted_name ?? ($category ? $category->name : '');
?>
<div class="wrap wsn-admin">
    <h1><?php echo $is_edit
        ? esc_html__('Kategorie bearbeiten', 'wesanox-booking')
        : esc_html__('Neue Kategorie anlegen', 'wesanox-booking');
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
                    <label for="category_name"><?php echo esc_html__('Name', 'wesanox-booking'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text"
                           id="category_name"
                           name="category_name"
                           class="regular-text"
                           required
                           value="<?php echo esc_attr($name_value); ?>">
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" class="button-primary"
                   value="<?php echo $is_edit
                       ? esc_attr__('Änderungen speichern', 'wesanox-booking')
                       : esc_attr__('Kategorie anlegen', 'wesanox-booking'); ?>">
        </p>
    </form>
</div>
