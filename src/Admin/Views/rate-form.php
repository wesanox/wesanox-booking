<?php
/**
 * Rate create / edit form.
 *
 * @var \Wesanox\Booking\Domain\Rate\Rate|null  $rate          null = create form
 * @var array|null                              $posted        recovered POST values on validation failure
 * @var \Wesanox\Booking\Domain\Area\Area[]     $areas
 * @var array<int, string>                      $products      [product_id => product_name]
 * @var string[]                                $errors
 * @var string                                  $nonce_action
 * @var string                                  $nonce_field
 */

defined('ABSPATH') || exit;

$is_edit = $rate !== null && $rate->id > 0;

// Values shown in form: prefer re-posted (on validation error), then saved rate, then defaults.
$v_area_id         = $posted['area_id']         ?? ($rate?->area_id       ?? 0);
$v_name            = $posted['rate_name']        ?? ($rate?->name          ?? '');
$v_time_from       = $posted['time_from']        ?? ($rate?->time_from     ?? '');
$v_time_to         = $posted['time_to']          ?? ($rate?->time_to       ?? '');
$v_product_id      = $posted['wc_product_id']    ?? ($rate?->wc_product_id ?? 0);
$v_variation_id    = $posted['wc_variation_id']  ?? ($rate?->wc_variation_id ?? null);
$v_is_active       = $posted['is_active']        ?? ($rate?->is_active     ?? true);
$v_sort_order      = $posted['sort_order']       ?? ($rate?->sort_order    ?? 0);

// Days: default to all days when creating a new rate.
$all_days = \Wesanox\Booking\Domain\Rate\Rate::WEEKDAYS;
$v_days   = $posted['days'] ?? ($rate?->days ?? $all_days);

$day_labels = [
    'monday'    => 'Montag',
    'tuesday'   => 'Dienstag',
    'wednesday' => 'Mittwoch',
    'thursday'  => 'Donnerstag',
    'friday'    => 'Freitag',
    'saturday'  => 'Samstag',
    'sunday'    => 'Sonntag',
];

$base_url    = admin_url('admin.php?page=rate-settings');
$form_action = add_query_arg(
    $is_edit ? ['action' => 'edit', 'id' => $rate->id] : ['action' => 'create'],
    admin_url('admin.php?page=rate-settings')
);
?>
<div class="wrap wsn-admin">
    <h1><?php echo $is_edit
        ? esc_html__('Rate bearbeiten', 'wesanox-booking')
        : esc_html__('Neue Rate anlegen', 'wesanox-booking');
    ?></h1>

    <a href="<?php echo esc_url($base_url); ?>">&larr; <?php echo esc_html__('Zurück zur Übersicht', 'wesanox-booking'); ?></a>

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

        <table class="form-table widefat">
            <tr>
                <th scope="row" style="width:220px">
                    <label for="rate_name"><?php echo esc_html__('Name', 'wesanox-booking'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text"
                           id="rate_name"
                           name="rate_name"
                           class="regular-text"
                           required
                           value="<?php echo esc_attr($v_name); ?>">
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="area_id"><?php echo esc_html__('Area', 'wesanox-booking'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <select id="area_id" name="area_id" required>
                        <option value="0"><?php echo esc_html__('— Area wählen —', 'wesanox-booking'); ?></option>
                        <?php foreach ($areas as $area): ?>
                            <option value="<?php echo esc_attr((string) $area->id); ?>"
                                    <?php selected($v_area_id, $area->id); ?>>
                                <?php echo esc_html($area->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="time_from"><?php echo esc_html__('Von', 'wesanox-booking'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="time"
                           id="time_from"
                           name="time_from"
                           required
                           value="<?php echo esc_attr($v_time_from); ?>">
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="time_to"><?php echo esc_html__('Bis', 'wesanox-booking'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="time"
                           id="time_to"
                           name="time_to"
                           required
                           value="<?php echo esc_attr($v_time_to); ?>">
                    <p class="description">
                        <?php echo esc_html__('00:00 = Mitternacht (Ende des Tages).', 'wesanox-booking'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php echo esc_html__('Wochentage', 'wesanox-booking'); ?> <span class="required">*</span>
                </th>
                <td>
                    <div style="display:flex;flex-wrap:wrap;gap:10px 20px">
                        <?php foreach ($day_labels as $day_key => $day_label): ?>
                            <label style="display:flex;align-items:center;gap:5px;cursor:pointer">
                                <input type="checkbox"
                                       name="days[]"
                                       value="<?php echo esc_attr($day_key); ?>"
                                       <?php checked(in_array($day_key, (array) $v_days, true)); ?>>
                                <?php echo esc_html($day_label); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="description">
                        <?php echo esc_html__('An welchen Tagen gilt diese Rate?', 'wesanox-booking'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wc_product_id"><?php echo esc_html__('WooCommerce-Produkt', 'wesanox-booking'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <select id="wc_product_id" name="wc_product_id" required>
                        <option value="0"><?php echo esc_html__('— Produkt wählen —', 'wesanox-booking'); ?></option>
                        <?php foreach ($products as $pid => $pname): ?>
                            <option value="<?php echo esc_attr((string) $pid); ?>"
                                    <?php selected($v_product_id, $pid); ?>>
                                <?php echo esc_html($pname); ?> (#<?php echo esc_html((string) $pid); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wc_variation_id"><?php echo esc_html__('Variation (optional)', 'wesanox-booking'); ?></label>
                </th>
                <td>
                    <select id="wc_variation_id" name="wc_variation_id">
                        <option value=""><?php echo esc_html__('— keine Variation —', 'wesanox-booking'); ?></option>
                        <?php
                        // Pre-populate variations for edit form (product already selected).
                        if ($v_product_id > 0 && !empty($products[$v_product_id])):
                            // Pass initial variations via data attribute; JS will replace on product change.
                        endif;
                        ?>
                    </select>
                    <span id="wsn-variation-spinner" style="display:none">
                        <span class="spinner is-active" style="float:none;vertical-align:middle"></span>
                    </span>
                    <p class="description" id="wsn-variation-hint" style="display:none">
                        <?php echo esc_html__('Wird geladen…', 'wesanox-booking'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="is_active"><?php echo esc_html__('Aktiv', 'wesanox-booking'); ?></label>
                </th>
                <td>
                    <input type="checkbox"
                           id="is_active"
                           name="is_active"
                           value="1"
                           <?php checked($v_is_active, true); ?>>
                    <label for="is_active">
                        <?php echo esc_html__('Rate ist aktiv (wird bei Überschneidungsprüfung berücksichtigt)', 'wesanox-booking'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="sort_order"><?php echo esc_html__('Sortierung', 'wesanox-booking'); ?></label>
                </th>
                <td>
                    <input type="number"
                           id="sort_order"
                           name="sort_order"
                           class="small-text"
                           min="0"
                           value="<?php echo esc_attr((string) $v_sort_order); ?>">
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" class="button-primary"
                   value="<?php echo $is_edit
                       ? esc_attr__('Änderungen speichern', 'wesanox-booking')
                       : esc_attr__('Rate anlegen', 'wesanox-booking'); ?>">
        </p>
    </form>
</div>

<script>
(function () {
    var AJAX_URL    = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
    var AJAX_NONCE  = <?php echo wp_json_encode(wp_create_nonce('wesanox_booking_nonce')); ?>;
    var INITIAL_PID = <?php echo (int) $v_product_id; ?>;
    var INITIAL_VID = <?php echo $v_variation_id !== null ? (int) $v_variation_id : 'null'; ?>;

    var productSelect   = document.getElementById('wc_product_id');
    var variationSelect = document.getElementById('wc_variation_id');
    var spinner         = document.getElementById('wsn-variation-spinner');

    function loadVariations(productId, preselect) {
        variationSelect.innerHTML = '<option value=""><?php echo esc_js(__('— keine Variation —', 'wesanox-booking')); ?></option>';

        if (!productId || productId === '0') {
            return;
        }

        spinner.style.display = 'inline';

        var data = new FormData();
        data.append('action',     'wesanox_get_product_variations');
        data.append('_ajax_nonce', AJAX_NONCE);
        data.append('product_id', productId);

        fetch(AJAX_URL, { method: 'POST', body: data })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                spinner.style.display = 'none';

                if (!res.success || !res.data) return;

                var variations = res.data;

                Object.keys(variations).forEach(function (vid) {
                    var opt = document.createElement('option');
                    opt.value       = vid;
                    opt.textContent = variations[vid] + ' (#' + vid + ')';

                    if (preselect !== null && parseInt(vid, 10) === preselect) {
                        opt.selected = true;
                    }

                    variationSelect.appendChild(opt);
                });
            })
            .catch(function () {
                spinner.style.display = 'none';
            });
    }

    productSelect.addEventListener('change', function () {
        loadVariations(this.value, null);
    });

    // On edit: pre-load existing product's variations.
    if (INITIAL_PID > 0) {
        loadVariations(String(INITIAL_PID), INITIAL_VID);
    }
}());
</script>
