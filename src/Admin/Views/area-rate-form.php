<?php
/**
 * Rate create / edit form — within Area context.
 * area_id is fixed from the URL; no area selector is shown.
 *
 * @var \Wesanox\Booking\Domain\Area\Area                       $area                 the owning Area (always set)
 * @var \Wesanox\Booking\Domain\Rate\Rate|null                  $rate                 null = create form
 * @var array|null                                              $posted               recovered POST values on validation failure
 * @var \Wesanox\Booking\Domain\ItemCategory\ItemCategory[]     $item_categories      all item categories
 * @var array<int, string>                                      $products             [product_id => product_name]
 * @var string[]                                                $rate_errors
 * @var int                                                     $preselect_category_id  pre-select category from URL param
 */

defined('ABSPATH') || exit;

$is_edit = $rate !== null && $rate->id > 0;

// Values: prefer re-posted on validation error, then saved rate, then defaults.
$v_category_id  = $posted['item_category_id'] ?? ($rate?->item_category_id ?? $preselect_category_id ?? 0);
$v_name         = $posted['rate_name']        ?? ($rate?->name             ?? '');
$v_time_from    = $posted['time_from']        ?? ($rate?->time_from        ?? '');
$v_time_to      = $posted['time_to']          ?? ($rate?->time_to          ?? '');
$v_days         = $posted['days']             ?? ($rate?->days             ?? \Wesanox\Booking\Domain\Rate\Rate::WEEKDAYS);
$v_product_id   = $posted['wc_product_id']    ?? ($rate?->wc_product_id    ?? 0);
$v_variation_id = $posted['wc_variation_id']  ?? ($rate?->wc_variation_id  ?? null);
$v_is_active    = $posted['is_active']        ?? ($rate?->is_active        ?? true);
$v_sort_order   = $posted['sort_order']       ?? ($rate?->sort_order       ?? 0);

$day_labels = [
    'monday'    => __('Montag',     'wesanox-booking'),
    'tuesday'   => __('Dienstag',   'wesanox-booking'),
    'wednesday' => __('Mittwoch',   'wesanox-booking'),
    'thursday'  => __('Donnerstag', 'wesanox-booking'),
    'friday'    => __('Freitag',    'wesanox-booking'),
    'saturday'  => __('Samstag',    'wesanox-booking'),
    'sunday'    => __('Sonntag',    'wesanox-booking'),
];

$back_url = add_query_arg(
    ['action' => 'edit', 'id' => $area->id],
    admin_url('admin.php?page=area-settings')
);

$form_action = add_query_arg(
    $is_edit
        ? ['action' => 'rate_edit', 'area_id' => $area->id, 'rate_id' => $rate->id]
        : ['action' => 'rate_create', 'area_id' => $area->id],
    admin_url('admin.php?page=area-settings')
);
?>
<div class="wrap wsn-admin">
    <h1>
        <?php if ($is_edit): ?>
            <?php printf(esc_html__('Rate bearbeiten — %s', 'wesanox-booking'), esc_html($area->name)); ?>
        <?php else: ?>
            <?php printf(esc_html__('Neue Rate — %s', 'wesanox-booking'), esc_html($area->name)); ?>
        <?php endif; ?>
    </h1>

    <a href="<?php echo esc_url($back_url); ?>">&larr; <?php echo esc_html__('Zurück zu den Rates', 'wesanox-booking'); ?></a>

    <?php if (!empty($rate_errors)): ?>
        <div class="notice notice-error">
            <ul>
                <?php foreach ($rate_errors as $error): ?>
                    <li><?php echo esc_html($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url($form_action); ?>" style="margin-top:1.5em">
        <?php wp_nonce_field('wesanox_rate_save', 'wesanox_nonce'); ?>

        <table class="form-table widefat">

            <tr>
                <th scope="row" style="width:220px">
                    <label for="item_category_id"><?php echo esc_html__('Item-Kategorie', 'wesanox-booking'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <?php if (empty($item_categories)): ?>
                        <p class="description" style="color:#d63638">
                            <?php echo esc_html__('Keine Item-Kategorien vorhanden. Bitte zuerst eine Item-Kategorie anlegen.', 'wesanox-booking'); ?>
                        </p>
                        <select id="item_category_id" name="item_category_id" disabled>
                            <option value="0"><?php echo esc_html__('— keine Kategorie —', 'wesanox-booking'); ?></option>
                        </select>
                    <?php else: ?>
                        <select id="item_category_id" name="item_category_id" required>
                            <option value="0"><?php echo esc_html__('— Kategorie wählen —', 'wesanox-booking'); ?></option>
                            <?php foreach ($item_categories as $cat): ?>
                                <option value="<?php echo esc_attr((string) $cat->id); ?>"
                                        <?php selected($v_category_id, $cat->id); ?>>
                                    <?php echo esc_html($cat->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </td>
            </tr>

            <tr>
                <th scope="row">
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
                    <label for="time_from"><?php echo esc_html__('Von', 'wesanox-booking'); ?></label>
                </th>
                <td>
                    <input type="time"
                           id="time_from"
                           name="time_from"
                           value="<?php echo esc_attr($v_time_from); ?>">
                    <p class="description">
                        <?php echo esc_html__('Leer lassen für ganztägige Gültigkeit.', 'wesanox-booking'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="time_to"><?php echo esc_html__('Bis', 'wesanox-booking'); ?></label>
                </th>
                <td>
                    <input type="time"
                           id="time_to"
                           name="time_to"
                           value="<?php echo esc_attr($v_time_to); ?>">
                    <p class="description">
                        <?php echo esc_html__('Leer lassen für ganztägige Gültigkeit. 00:00 = Mitternacht (Ende des Tages).', 'wesanox-booking'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label><?php echo esc_html__('Wochentage', 'wesanox-booking'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <div class="wsn-days-grid">
                        <?php foreach ($day_labels as $day_key => $day_label): ?>
                            <label>
                                <input type="checkbox"
                                       name="days[]"
                                       value="<?php echo esc_attr($day_key); ?>"
                                       <?php checked(in_array($day_key, (array) $v_days, true)); ?>>
                                <?php echo esc_html($day_label); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="wsn-days-shortcuts">
                        <button type="button" class="button button-small" id="wsn-days-all">
                            <?php echo esc_html__('Alle', 'wesanox-booking'); ?>
                        </button>
                        <button type="button" class="button button-small" id="wsn-days-weekdays">
                            <?php echo esc_html__('Mo–Fr', 'wesanox-booking'); ?>
                        </button>
                        <button type="button" class="button button-small" id="wsn-days-weekend">
                            <?php echo esc_html__('Sa–So', 'wesanox-booking'); ?>
                        </button>
                    </div>
                    <p class="description">
                        <?php echo esc_html__('An welchen Wochentagen gilt dieser Tarif?', 'wesanox-booking'); ?>
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
                    </select>
                    <span id="wsn-variation-spinner" style="display:none">
                        <span class="spinner is-active" style="float:none;vertical-align:middle"></span>
                    </span>
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
                        <?php echo esc_html__('Rate ist aktiv (Überschneidungsprüfung gilt nur für aktive Rates)', 'wesanox-booking'); ?>
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
            <a href="<?php echo esc_url($back_url); ?>" class="button" style="margin-left:.5em">
                <?php echo esc_html__('Abbrechen', 'wesanox-booking'); ?>
            </a>
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

        if (!productId || productId === '0') return;

        spinner.style.display = 'inline';

        var data = new FormData();
        data.append('action',      'wesanox_get_product_variations');
        data.append('_ajax_nonce', AJAX_NONCE);
        data.append('product_id',  productId);

        fetch(AJAX_URL, { method: 'POST', body: data })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                spinner.style.display = 'none';

                if (!res.success || !res.data) return;

                Object.keys(res.data).forEach(function (vid) {
                    var opt       = document.createElement('option');
                    opt.value     = vid;
                    opt.textContent = res.data[vid] + ' (#' + vid + ')';

                    if (preselect !== null && parseInt(vid, 10) === preselect) {
                        opt.selected = true;
                    }

                    variationSelect.appendChild(opt);
                });
            })
            .catch(function () { spinner.style.display = 'none'; });
    }

    productSelect.addEventListener('change', function () { loadVariations(this.value, null); });

    if (INITIAL_PID > 0) {
        loadVariations(String(INITIAL_PID), INITIAL_VID);
    }

    // Day quick-select buttons.
    var ALL_DAYS     = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
    var WEEKDAY_DAYS = ['monday','tuesday','wednesday','thursday','friday'];
    var WEEKEND_DAYS = ['saturday','sunday'];

    function setDays(days) {
        document.querySelectorAll('input[name="days[]"]').forEach(function (cb) {
            cb.checked = days.indexOf(cb.value) !== -1;
        });
    }

    var btnAll      = document.getElementById('wsn-days-all');
    var btnWeekdays = document.getElementById('wsn-days-weekdays');
    var btnWeekend  = document.getElementById('wsn-days-weekend');

    if (btnAll)      btnAll.addEventListener('click',      function () { setDays(ALL_DAYS); });
    if (btnWeekdays) btnWeekdays.addEventListener('click', function () { setDays(WEEKDAY_DAYS); });
    if (btnWeekend)  btnWeekend.addEventListener('click',  function () { setDays(WEEKEND_DAYS); });
}());
</script>
