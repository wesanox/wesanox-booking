<?php
/**
 * Holiday create / edit form.
 *
 * @var \Wesanox\Booking\Domain\Holiday\Holiday|null $holiday  null = create form
 * @var \Wesanox\Booking\Domain\Holiday\Holiday|null $posted   recovered POST values on validation failure
 * @var \Wesanox\Booking\Domain\Area\Area[]          $areas
 * @var string[]                                     $errors
 * @var string                                       $nonce_action
 * @var string                                       $nonce_field
 */

defined('ABSPATH') || exit;

$source  = $posted ?? $holiday;
$is_edit = $holiday !== null && $holiday->id > 0;

$base_url    = admin_url('admin.php?page=holiday-settings');
$form_action = add_query_arg(
    $is_edit ? ['action' => 'edit', 'id' => $holiday->id] : ['action' => 'create'],
    admin_url('admin.php?page=holiday-settings')
);

$date_val    = $source ? (string) ($source->opening_date ?? '') : '';
$from_val    = $source ? (string) ($source->opening_from ?? '') : '';
$to_val      = $source ? (string) ($source->opening_to   ?? '') : '';
$closed_val  = $source ? $source->isClosed()         : false;
$holiday_val = $source ? $source->isHolidayPricing() : false;
$area_id_val = $source ? $source->area_id            : null;

// Convert Y-m-d → date input value (already correct format for <input type="date">).
?>
<div class="wrap wsn-admin">
    <h1><?php echo $is_edit
        ? esc_html__('Eintrag bearbeiten', 'wesanox-booking')
        : esc_html__('Neuen Eintrag anlegen', 'wesanox-booking');
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

    <form method="post" action="<?php echo esc_url($form_action); ?>" style="margin-top:1.5em">
        <?php wp_nonce_field($nonce_action, $nonce_field); ?>

        <table class="form-table widefat" style="margin-top:0">
            <tr>
                <th scope="row" style="width:220px">
                    <label for="opening_date">
                        <?php echo esc_html__('Datum', 'wesanox-booking'); ?> <span class="required">*</span>
                    </label>
                </th>
                <td>
                    <input type="date"
                           id="opening_date"
                           name="opening_date"
                           required
                           value="<?php echo esc_attr($date_val); ?>">
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label><?php echo esc_html__('Geschlossen', 'wesanox-booking'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox"
                               id="opening_closed"
                               name="opening_closed"
                               value="1"
                               <?php checked($closed_val, true); ?>>
                        <?php echo esc_html__('Dieser Tag ist geschlossen (keine Buchungen möglich)', 'wesanox-booking'); ?>
                    </label>
                </td>
            </tr>

            <tr id="row_opening_times" <?php echo $closed_val ? 'style="display:none"' : ''; ?>>
                <th scope="row">
                    <?php echo esc_html__('Öffnungszeiten (optional)', 'wesanox-booking'); ?>
                </th>
                <td>
                    <label for="opening_from"><?php echo esc_html__('Von', 'wesanox-booking'); ?></label>
                    <input type="time"
                           id="opening_from"
                           name="opening_from"
                           value="<?php echo esc_attr($from_val); ?>"
                           style="margin-right:1em">

                    <label for="opening_to"><?php echo esc_html__('Bis', 'wesanox-booking'); ?></label>
                    <input type="time"
                           id="opening_to"
                           name="opening_to"
                           value="<?php echo esc_attr($to_val); ?>">

                    <p class="description">
                        <?php echo esc_html__('Leer lassen, um die Standard-Öffnungszeiten der Area zu verwenden. Schließzeit 00:00 = Mitternacht.', 'wesanox-booking'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="area_id"><?php echo esc_html__('Gültig für Area', 'wesanox-booking'); ?></label>
                </th>
                <td>
                    <select id="area_id" name="area_id">
                        <option value="0"><?php echo esc_html__('— Alle Areas (global) —', 'wesanox-booking'); ?></option>
                        <?php foreach ($areas as $area): ?>
                            <option value="<?php echo esc_attr((string) $area->id); ?>"
                                <?php selected($area_id_val, $area->id); ?>>
                                <?php echo esc_html($area->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php echo esc_html__('„Alle Areas" überschreibt jede Area, sofern kein area-spezifischer Eintrag für dieses Datum existiert.', 'wesanox-booking'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label><?php echo esc_html__('Feiertagspreis', 'wesanox-booking'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox"
                               name="opening_holiday"
                               value="1"
                               <?php checked($holiday_val, true); ?>>
                        <?php echo esc_html__('Feiertagspreise anwenden', 'wesanox-booking'); ?>
                    </label>
                    <p class="description">
                        <?php echo esc_html__('Aktivieren, um Wochenend-/Feiertagspreise für diesen Tag zu berechnen.', 'wesanox-booking'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <p class="submit" style="margin-top:1.5em">
            <input type="submit" class="button-primary"
                   value="<?php echo $is_edit
                       ? esc_attr__('Änderungen speichern', 'wesanox-booking')
                       : esc_attr__('Eintrag anlegen', 'wesanox-booking'); ?>">
        </p>
    </form>
</div>

<script>
(function () {
    var checkbox  = document.getElementById('opening_closed');
    var timesRow  = document.getElementById('row_opening_times');

    function toggle() {
        timesRow.style.display = checkbox.checked ? 'none' : '';
    }

    checkbox.addEventListener('change', toggle);
}());
</script>
