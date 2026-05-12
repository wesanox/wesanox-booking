<?php
/**
 * Area create / edit form — tabbed layout.
 *
 * @var \Wesanox\Booking\Domain\Area\Area|null                  $area               null = create form
 * @var \Wesanox\Booking\Domain\Area\Area|null                  $posted             recovered POST values on validation failure
 * @var \Wesanox\Booking\Domain\ItemCategory\ItemCategory[]     $item_categories    all item categories
 * @var \Wesanox\Booking\Domain\Rate\Rate[]                     $rates              all rates for this area
 * @var array<int, string>                                      $products           [product_id => product_name]
 * @var string[]                                                $errors
 * @var string[]                                                $rate_errors
 * @var int                                                     $rate_saved         1 = saved, -1 = none
 * @var int                                                     $rate_deleted       1 = deleted, 0 = failed, -1 = none
 * @var string                                                  $nonce_action
 * @var string                                                  $nonce_field
 */

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Area\Area;

$source        = $posted ?? $area;
$is_edit       = $area !== null && $area->id > 0;
$opening_data  = $source ? $source->openingData()         : array_fill_keys(Area::WEEKDAYS, ['enabled' => false, 'from' => null, 'to' => null]);
$ts_data       = $source ? $source->timeSettingsData()    : ['area_time_separator' => '1', 'area_time_sheet' => '', 'area_time_min' => '', 'area_time_max' => ''];
$bs_data       = $source ? $source->bookingSettingsData() : ['booking_type' => 'standard', 'redirect_url' => '', 'title' => ''];
$api_data      = $source ? $source->apiSettingsData()     : ['api_enabled' => false, 'credential_id' => 0, 'external_id' => ''];
$snippet       = ($is_edit && $area) ? $area->shortcodeSnippet() : '';

// $api_available and $api_credential_options are injected by AreaListPage::renderForm().
/** @var bool $api_available */
/** @var array<int, string> $api_credential_options */
$api_available          ??= false;
$api_credential_options ??= [];

$base_url    = admin_url('admin.php?page=area-settings');
$form_action = add_query_arg(
    $is_edit ? ['action' => 'edit', 'id' => $area->id] : ['action' => 'create'],
    admin_url('admin.php?page=area-settings')
);

$day_labels = [
    'monday'    => __('Montag',     'wesanox-booking'),
    'tuesday'   => __('Dienstag',   'wesanox-booking'),
    'wednesday' => __('Mittwoch',   'wesanox-booking'),
    'thursday'  => __('Donnerstag', 'wesanox-booking'),
    'friday'    => __('Freitag',    'wesanox-booking'),
    'saturday'  => __('Samstag',    'wesanox-booking'),
    'sunday'    => __('Sonntag',    'wesanox-booking'),
];

// Determine which tab to open on load:
// - On validation error: show the tab that contains the first error (naive: always stammdaten unless only opening errors)
$default_tab = 'stammdaten';
if (!empty($errors)) {
    $has_name_error   = false;
    $has_ts_error     = false;
    $has_opening_error = false;
    foreach ($errors as $e) {
        if (str_contains($e, 'Name'))                 $has_name_error   = true;
        if (str_contains($e, 'Zeitintervall') || str_contains($e, 'Zeiteinstellung')) $has_ts_error = true;
        if (str_contains($e, 'Öffnungszeit') || str_contains($e, 'liegt'))           $has_opening_error = true;
    }
    if (!$has_name_error && !$has_ts_error && $has_opening_error) {
        $default_tab = 'oeffnung';
    } elseif (!$has_name_error && $has_ts_error) {
        $default_tab = 'zeiten';
    }
}

$booking_type_labels = [
    'standard' => __('Standard (Tagesbuchung)', 'wesanox-booking'),
    'suite'    => __('Suite (Mehrtage)', 'wesanox-booking'),
    'timeslot' => __('Zeitslot (Stundenweise)', 'wesanox-booking'),
];
?>
<div class="wrap wsn-admin">
    <h1><?php echo $is_edit
        ? esc_html__('Area bearbeiten', 'wesanox-booking')
        : esc_html__('Neue Area anlegen', 'wesanox-booking');
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

    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper wp-clearfix" style="margin-top:1em">
        <a href="#" class="nav-tab wesanox-area-tab" data-tab="stammdaten">
            <?php echo esc_html__('Stammdaten', 'wesanox-booking'); ?>
        </a>
        <a href="#" class="nav-tab wesanox-area-tab" data-tab="zeiten">
            <?php echo esc_html__('Zeiteinstellungen', 'wesanox-booking'); ?>
        </a>
        <a href="#" class="nav-tab wesanox-area-tab" data-tab="oeffnung">
            <?php echo esc_html__('Öffnungszeiten', 'wesanox-booking'); ?>
        </a>
        <a href="#" class="nav-tab wesanox-area-tab" data-tab="shortcode">
            <?php echo esc_html__('Shortcode', 'wesanox-booking'); ?>
        </a>
        <a href="#" class="nav-tab wesanox-area-tab" data-tab="api">
            <?php echo esc_html__('API Integration', 'wesanox-booking'); ?>
            <?php if ($api_data['api_enabled']): ?>
                <span class="dashicons dashicons-rest-api" style="font-size:14px;vertical-align:middle;color:#2271b1"></span>
            <?php endif; ?>
        </a>
        <?php if ($is_edit): ?>
        <a href="#" class="nav-tab wesanox-area-tab" data-tab="rates">
            <?php echo esc_html__('Rates', 'wesanox-booking'); ?>
            <?php if (!empty($rates)): ?>
                <span class="awaiting-mod" style="margin-left:4px"><?php echo count($rates); ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
    </nav>

    <form method="post" action="<?php echo esc_url($form_action); ?>">
        <?php wp_nonce_field($nonce_action, $nonce_field); ?>

        <!-- Tab: Stammdaten -->
        <div id="wesanox-tab-stammdaten" class="wesanox-tab-panel">
            <table class="form-table widefat">
                <tr>
                    <th scope="row" style="width:220px">
                        <label for="area_name"><?php echo esc_html__('Name', 'wesanox-booking'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text"
                               id="area_name"
                               name="area_name"
                               class="regular-text"
                               required
                               value="<?php echo esc_attr($source ? $source->name : ''); ?>">
                    </td>
                </tr>
            </table>
        </div>

        <!-- Tab: Zeiteinstellungen -->
        <div id="wesanox-tab-zeiten" class="wesanox-tab-panel" style="display:none">
            <table class="form-table widefat">
                <tr>
                    <th scope="row" style="width:220px">
                        <label for="area_time_separator"><?php echo esc_html__('Zeitintervall-Typ', 'wesanox-booking'); ?></label>
                    </th>
                    <td>
                        <select id="area_time_separator" name="area_time_separator">
                            <option value="1" <?php selected($ts_data['area_time_separator'], '1'); ?>>
                                <?php echo esc_html__('Minutenweise', 'wesanox-booking'); ?>
                            </option>
                            <option value="2" <?php selected($ts_data['area_time_separator'], '2'); ?>>
                                <?php echo esc_html__('Stundenweise', 'wesanox-booking'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="area_time_sheet"><?php echo esc_html__('Abstand zwischen Slots (Min.)', 'wesanox-booking'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="area_time_sheet" name="area_time_sheet" min="0"
                               class="small-text" value="<?php echo esc_attr($ts_data['area_time_sheet']); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="area_time_min"><?php echo esc_html__('Minimale Buchungszeit (Min.)', 'wesanox-booking'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="area_time_min" name="area_time_min" min="0"
                               class="small-text" value="<?php echo esc_attr($ts_data['area_time_min']); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="area_time_max"><?php echo esc_html__('Maximale Buchungszeit (Min.)', 'wesanox-booking'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="area_time_max" name="area_time_max" min="0"
                               class="small-text" value="<?php echo esc_attr($ts_data['area_time_max']); ?>">
                    </td>
                </tr>
            </table>
        </div>

        <!-- Tab: Öffnungszeiten -->
        <div id="wesanox-tab-oeffnung" class="wesanox-tab-panel" style="display:none">
            <p class="description" style="margin:.5em 0 1em">
                <?php echo esc_html__('Aktivierte Tage begrenzen die buchbaren Zeiten im Frontend. Deaktivierte Tage erscheinen als geschlossen.', 'wesanox-booking'); ?>
                <?php echo esc_html__('Schließzeit 00:00 = Mitternacht (Ende des Tages).', 'wesanox-booking'); ?>
            </p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:130px"><?php echo esc_html__('Tag', 'wesanox-booking'); ?></th>
                        <th style="width:80px"><?php echo esc_html__('Geöffnet', 'wesanox-booking'); ?></th>
                        <th><?php echo esc_html__('Von', 'wesanox-booking'); ?></th>
                        <th><?php echo esc_html__('Bis', 'wesanox-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (Area::WEEKDAYS as $day):
                        $day_data = $opening_data[$day] ?? ['enabled' => false, 'from' => null, 'to' => null];
                        $enabled  = (bool) $day_data['enabled'];
                        $from     = (string) ($day_data['from'] ?? '');
                        $to       = (string) ($day_data['to']   ?? '');
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($day_labels[$day] ?? $day); ?></strong></td>
                            <td>
                                <input type="checkbox"
                                       name="opening[<?php echo esc_attr($day); ?>][enabled]"
                                       value="1"
                                       <?php checked($enabled, true); ?>
                                       class="wesanox-opening-toggle"
                                       data-day="<?php echo esc_attr($day); ?>">
                            </td>
                            <td>
                                <input type="time"
                                       name="opening[<?php echo esc_attr($day); ?>][from]"
                                       value="<?php echo esc_attr($from); ?>"
                                       <?php echo $enabled ? '' : 'disabled'; ?>>
                            </td>
                            <td>
                                <input type="time"
                                       name="opening[<?php echo esc_attr($day); ?>][to]"
                                       value="<?php echo esc_attr($to); ?>"
                                       <?php echo $enabled ? '' : 'disabled'; ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Tab: Shortcode -->
        <div id="wesanox-tab-shortcode" class="wesanox-tab-panel" style="display:none">
            <table class="form-table widefat">
                <tr>
                    <th scope="row" style="width:220px">
                        <label for="booking_type"><?php echo esc_html__('Buchungstyp', 'wesanox-booking'); ?></label>
                    </th>
                    <td>
                        <select id="booking_type" name="booking_type">
                            <?php foreach ($booking_type_labels as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>"
                                    <?php selected($bs_data['booking_type'], $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php echo esc_html__('Bestimmt welcher Shortcode-Tag generiert wird.', 'wesanox-booking'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="booking_redirect_url"><?php echo esc_html__('Weiterleitungs-URL', 'wesanox-booking'); ?></label>
                    </th>
                    <td>
                        <input type="url"
                               id="booking_redirect_url"
                               name="booking_redirect_url"
                               class="regular-text"
                               value="<?php echo esc_attr($bs_data['redirect_url']); ?>"
                               placeholder="https://example.com/buchung">
                        <p class="description">
                            <?php echo esc_html__('„Jetzt buchen"-Button im Shortcode verlinkt auf diese URL.', 'wesanox-booking'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="booking_title"><?php echo esc_html__('Titel im Widget', 'wesanox-booking'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               id="booking_title"
                               name="booking_title"
                               class="regular-text"
                               value="<?php echo esc_attr($bs_data['title']); ?>"
                               placeholder="<?php echo esc_attr__('Verfügbarkeit prüfen', 'wesanox-booking'); ?>">
                        <p class="description">
                            <?php echo esc_html__('Überschrift im Frontend-Widget. Leer lassen für Standard-Titel.', 'wesanox-booking'); ?>
                        </p>
                    </td>
                </tr>
                <?php if ($is_edit): ?>
                <tr>
                    <th scope="row"><?php echo esc_html__('Shortcode', 'wesanox-booking'); ?></th>
                    <td>
                        <div style="display:flex;align-items:center;gap:.5em">
                            <code id="wesanox-shortcode-snippet"
                                  style="background:#f0f0f1;padding:.4em .6em;border-radius:3px;font-size:13px;user-select:all">
                                <?php echo esc_html($snippet); ?>
                            </code>
                            <button type="button"
                                    id="wesanox-copy-snippet"
                                    class="button button-secondary">
                                <?php echo esc_html__('Kopieren', 'wesanox-booking'); ?>
                            </button>
                            <span id="wesanox-copy-notice" style="display:none;color:#00a32a">
                                <?php echo esc_html__('Kopiert!', 'wesanox-booking'); ?>
                            </span>
                        </div>
                        <p class="description" style="margin-top:.5em">
                            <?php echo esc_html__('Nach dem Speichern wird der Snippet mit den aktuellen Einstellungen aktualisiert.', 'wesanox-booking'); ?>
                        </p>
                    </td>
                </tr>
                <?php else: ?>
                <tr>
                    <th scope="row"><?php echo esc_html__('Shortcode', 'wesanox-booking'); ?></th>
                    <td>
                        <p class="description">
                            <?php echo esc_html__('Der Shortcode wird nach dem Anlegen der Area angezeigt.', 'wesanox-booking'); ?>
                        </p>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
        </div>

        <!-- Tab: API Integration -->
        <div id="wesanox-tab-api" class="wesanox-tab-panel" style="display:none">

            <?php if (!$api_available): ?>
                <div class="notice notice-warning inline" style="margin-top:1em">
                    <p>
                        <?php echo esc_html__('Das Plugin wesanox-api ist nicht aktiv. Bitte aktiviere es, um die API-Integration nutzen zu können.', 'wesanox-booking'); ?>
                    </p>
                </div>
            <?php endif; ?>

            <table class="form-table widefat">
                <tr>
                    <th scope="row" style="width:220px">
                        <label for="api_enabled"><?php echo esc_html__('API aktiviert', 'wesanox-booking'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox"
                               id="api_enabled"
                               name="api_enabled"
                               value="1"
                               <?php checked($api_data['api_enabled'], true); ?>
                               <?php echo !$api_available ? 'disabled' : ''; ?>>
                        <p class="description">
                            <?php echo esc_html__('Buchungen mit dem externen System synchronisieren.', 'wesanox-booking'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="api_credential_id"><?php echo esc_html__('API-Zugangsdaten', 'wesanox-booking'); ?></label>
                    </th>
                    <td>
                        <?php if ($api_available && !empty($api_credential_options)): ?>
                            <select id="api_credential_id" name="api_credential_id">
                                <option value="0"><?php echo esc_html__('— bitte wählen —', 'wesanox-booking'); ?></option>
                                <?php foreach ($api_credential_options as $cred_id => $cred_label): ?>
                                    <option value="<?php echo esc_attr((string) $cred_id); ?>"
                                        <?php selected($api_data['credential_id'], $cred_id); ?>>
                                        <?php echo esc_html($cred_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($api_data['credential_id'] > 0): ?>
                                <button type="button"
                                        id="wsn-test-api-connection"
                                        class="button button-secondary"
                                        style="margin-left:.5em"
                                        data-credential-id="<?php echo esc_attr((string) $api_data['credential_id']); ?>">
                                    <?php echo esc_html__('Verbindung testen', 'wesanox-booking'); ?>
                                </button>
                                <span id="wsn-api-connection-result" style="margin-left:.5em"></span>
                            <?php endif; ?>
                        <?php elseif ($api_available): ?>
                            <p class="description">
                                <?php echo esc_html__('Keine API-Zugangsdaten hinterlegt. Bitte zuerst im wesanox-api Plugin anlegen.', 'wesanox-booking'); ?>
                            </p>
                            <input type="hidden" name="api_credential_id" value="0">
                        <?php else: ?>
                            <input type="hidden" name="api_credential_id" value="<?php echo esc_attr((string) $api_data['credential_id']); ?>">
                            <span class="description"><?php echo esc_html__('Nicht verfügbar (wesanox-api inaktiv).', 'wesanox-booking'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="api_external_id"><?php echo esc_html__('Externe Area-ID', 'wesanox-booking'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               id="api_external_id"
                               name="api_external_id"
                               class="regular-text"
                               value="<?php echo esc_attr($api_data['external_id']); ?>"
                               placeholder="z. B. suite-1"
                               <?php echo !$api_available ? 'disabled' : ''; ?>>
                        <p class="description">
                            <?php echo esc_html__('Die ID dieser Area im externen Buchungssystem.', 'wesanox-booking'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit" id="wsn-area-submit" style="margin-top:1.5em">
            <input type="submit" class="button-primary"
                   value="<?php echo $is_edit
                       ? esc_attr__('Änderungen speichern', 'wesanox-booking')
                       : esc_attr__('Area anlegen', 'wesanox-booking'); ?>">
        </p>
    </form>

    <?php if ($is_edit && $area): ?>
    <!-- Tab: Rates (outside the area form to avoid nested forms) -->
    <div id="wesanox-tab-rates" class="wesanox-tab-panel" style="display:none">

        <?php if ($rate_saved === 1): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html__('Rate wurde gespeichert.', 'wesanox-booking'); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($rate_deleted === 1): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html__('Rate wurde gelöscht.', 'wesanox-booking'); ?></p>
            </div>
        <?php elseif ($rate_deleted === 0): ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html__('Rate konnte nicht gelöscht werden.', 'wesanox-booking'); ?></p>
            </div>
        <?php endif; ?>

        <?php
        $rate_create_url = add_query_arg(
            ['action' => 'rate_create', 'area_id' => $area->id],
            admin_url('admin.php?page=area-settings')
        );

        // Build lookup: item_category_id → Rate[]
        $rates_by_category = [];
        foreach ($rates as $rate) {
            $rates_by_category[$rate->item_category_id][] = $rate;
        }

        // Build lookup: item_category id → name
        $category_names = [];
        foreach ($item_categories as $ic) {
            $category_names[$ic->id] = $ic->name;
        }

        $day_abbr = [
            'monday'    => 'Mo', 'tuesday' => 'Di', 'wednesday' => 'Mi',
            'thursday'  => 'Do', 'friday'  => 'Fr',
            'saturday'  => 'Sa', 'sunday'  => 'So',
        ];
        ?>

        <div class="wsn-section-header">
            <h3><?php echo esc_html__('Rates', 'wesanox-booking'); ?></h3>
            <a href="<?php echo esc_url($rate_create_url); ?>" class="button button-primary">
                + <?php echo esc_html__('Neue Rate', 'wesanox-booking'); ?>
            </a>
        </div>

        <?php if (empty($rates)): ?>
            <p class="wsn-card__empty">
                <?php echo esc_html__('Noch keine Rates vorhanden.', 'wesanox-booking'); ?>
            </p>
        <?php else: ?>
            <?php foreach ($rates_by_category as $cat_id => $cat_rates):
                $cat_name       = $category_names[$cat_id] ?? __('Ohne Kategorie', 'wesanox-booking');
                $cat_create_url = add_query_arg(['action' => 'rate_create', 'area_id' => $area->id, 'category_id' => $cat_id], admin_url('admin.php?page=area-settings'));
                $card_class     = 'wsn-card' . ($cat_id === 0 ? ' wsn-card--warning' : '');
            ?>
            <div class="<?php echo esc_attr($card_class); ?>">

                <div class="wsn-card__header">
                    <div class="wsn-card__header-meta">
                        <strong><?php echo esc_html($cat_name); ?></strong>
                    </div>
                    <?php if ($cat_id > 0): ?>
                    <div class="wsn-card__header-actions">
                        <a href="<?php echo esc_url($cat_create_url); ?>" class="button button-small">
                            + <?php echo esc_html__('Rate anlegen', 'wesanox-booking'); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <table class="wp-list-table widefat fixed">
                    <thead>
                        <tr>
                            <th style="width:40px">ID</th>
                            <th><?php echo esc_html__('Name', 'wesanox-booking'); ?></th>
                            <th style="width:90px"><?php echo esc_html__('Von', 'wesanox-booking'); ?></th>
                            <th style="width:90px"><?php echo esc_html__('Bis', 'wesanox-booking'); ?></th>
                            <th style="width:160px"><?php echo esc_html__('Tage', 'wesanox-booking'); ?></th>
                            <th><?php echo esc_html__('Produkt', 'wesanox-booking'); ?></th>
                            <th style="width:65px"><?php echo esc_html__('Aktiv', 'wesanox-booking'); ?></th>
                            <th style="width:150px"><?php echo esc_html__('Aktionen', 'wesanox-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cat_rates as $rate):
                            $rate_edit_url = add_query_arg(
                                ['action' => 'rate_edit', 'area_id' => $area->id, 'rate_id' => $rate->id],
                                admin_url('admin.php?page=area-settings')
                            );
                            $product_label = isset($products[$rate->wc_product_id])
                                ? esc_html($products[$rate->wc_product_id]) . ' (#' . $rate->wc_product_id . ')'
                                : '#' . $rate->wc_product_id;
                            if ($rate->wc_variation_id) {
                                $product_label .= ' / #' . $rate->wc_variation_id;
                            }
                        ?>
                            <tr>
                                <td><?php echo esc_html((string) $rate->id); ?></td>
                                <td><strong><?php echo esc_html($rate->name); ?></strong></td>
                                <?php $is_allday = ($rate->time_from === '00:00' && $rate->time_to === '00:00'); ?>
                                <td><?php echo $is_allday ? '<em>' . esc_html__('Ganzer Tag', 'wesanox-booking') . '</em>' : esc_html($rate->time_from); ?></td>
                                <td><?php echo $is_allday ? '' : esc_html($rate->time_to === '00:00' ? '00:00*' : $rate->time_to); ?></td>
                                <td>
                                    <span class="wsn-days-badge">
                                        <?php foreach ($rate->days as $d): ?>
                                            <span><?php echo esc_html($day_abbr[$d] ?? $d); ?></span>
                                        <?php endforeach; ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($product_label); ?></td>
                                <td>
                                    <?php if ($rate->is_active): ?>
                                        <span class="dashicons dashicons-yes-alt" style="color:#00a32a"></span>
                                    <?php else: ?>
                                        <span class="dashicons dashicons-dismiss" style="color:#d63638"></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($rate_edit_url); ?>" class="button button-small">
                                        <?php echo esc_html__('Bearbeiten', 'wesanox-booking'); ?>
                                    </a>
                                    <form method="post"
                                          action="<?php echo esc_url(add_query_arg(['page' => 'area-settings', 'action' => 'rate_delete', 'area_id' => $area->id], admin_url('admin.php'))); ?>"
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
                    </tbody>
                </table>

            </div><!-- end category block -->
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var STORAGE_KEY = 'wesanox_area_tab';
    var defaultTab  = <?php echo json_encode($default_tab); ?>;

    // If returning from a rate action, open the rates tab.
    <?php if ($rate_saved >= 0 || $rate_deleted >= 0): ?>
    defaultTab = 'rates';
    <?php endif; ?>

    function activateTab(name) {
        document.querySelectorAll('.wesanox-area-tab').forEach(function (a) {
            a.classList.toggle('nav-tab-active', a.dataset.tab === name);
        });
        document.querySelectorAll('.wesanox-tab-panel').forEach(function (el) {
            el.style.display = el.id === 'wesanox-tab-' + name ? '' : 'none';
        });
        var submitRow = document.getElementById('wsn-area-submit');
        if (submitRow) {
            submitRow.style.display = name === 'rates' ? 'none' : '';
        }
        try { localStorage.setItem(STORAGE_KEY, name); } catch (e) {}
    }

    // Restore saved tab, or use server-determined default.
    var saved = '';
    try { saved = localStorage.getItem(STORAGE_KEY) || ''; } catch (e) {}
    // If a validation error forces a tab, always honour the server default.
    var initialTab = (<?php echo json_encode(!empty($errors)); ?> || !saved) ? defaultTab : saved;
    activateTab(initialTab);

    document.querySelectorAll('.wesanox-area-tab').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            activateTab(this.dataset.tab);
        });
    });

    // Opening hours: enable/disable time inputs when checkbox is toggled.
    document.querySelectorAll('.wesanox-opening-toggle').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            var day    = this.dataset.day;
            var inputs = document.querySelectorAll('[name^="opening[' + day + ']"]');
            inputs.forEach(function (input) {
                if (input !== checkbox) input.disabled = !checkbox.checked;
            });
        });
    });

    // API connection test button.
    var testBtn = document.getElementById('wsn-test-api-connection');
    if (testBtn) {
        testBtn.addEventListener('click', function () {
            var resultEl   = document.getElementById('wsn-api-connection-result');
            var credId     = this.dataset.credentialId;
            var nonce      = (typeof ajax_object !== 'undefined') ? ajax_object.nonce : '';

            this.disabled = true;
            if (resultEl) resultEl.textContent = '…';

            var data = new URLSearchParams();
            data.append('action', 'wesanox_test_api_connection');
            data.append('_ajax_nonce', nonce);
            data.append('credential_id', credId);

            fetch((typeof ajax_object !== 'undefined' ? ajax_object.ajax_url : '/wp-admin/admin-ajax.php'), {
                method: 'POST',
                body: data,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (resultEl) {
                    resultEl.textContent = json.data ? json.data.message : '?';
                    resultEl.style.color  = json.success ? '#00a32a' : '#d63638';
                }
            })
            .catch(function () {
                if (resultEl) { resultEl.textContent = 'Fehler'; resultEl.style.color = '#d63638'; }
            })
            .finally(function () { testBtn.disabled = false; });
        });
    }

    // Copy shortcode snippet.
    var copyBtn = document.getElementById('wesanox-copy-snippet');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var code = document.getElementById('wesanox-shortcode-snippet');
            if (!code) return;

            var text = code.textContent.trim();

            navigator.clipboard.writeText(text).then(function () {
                var notice = document.getElementById('wesanox-copy-notice');
                if (notice) {
                    notice.style.display = 'inline';
                    setTimeout(function () { notice.style.display = 'none'; }, 2000);
                }
            }).catch(function () {
                // Fallback for older browsers.
                var range = document.createRange();
                range.selectNode(code);
                window.getSelection().removeAllRanges();
                window.getSelection().addRange(range);
                document.execCommand('copy');
                window.getSelection().removeAllRanges();
            });
        });
    }
}());
</script>
