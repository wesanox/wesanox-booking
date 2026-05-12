<?php
/**
 * Template: Timeslot Booking Widget (multi-step)
 *
 * @var int    $area_id
 * @var string $redirect_url
 * @var string $title
 * @var string $nonce
 */
defined('ABSPATH') || exit;

$today = (new DateTimeImmutable('today'))->format('Y-m-d');
static $wsn_ts_uid = 0;
$uid = ++$wsn_ts_uid;
?>
<div class="wsn-timeslot-widget"
     id="wsn-timeslot-<?php echo esc_attr((string) $uid); ?>"
     data-area-id="<?php echo esc_attr((string) $area_id); ?>"
     data-redirect="<?php echo esc_attr($redirect_url); ?>">

    <?php if ($title): ?>
        <h3 class="wesanox-shortcode__title"><?php echo esc_html($title); ?></h3>
    <?php endif; ?>

    <!-- Step indicators -->
    <div class="wsn-steps">
        <div class="wsn-step wsn-step--active" data-step="1">
            <span class="wsn-step__num">1</span>
            <span class="wsn-step__label"><?php echo esc_html__('Datum', 'wesanox-booking'); ?></span>
        </div>
        <div class="wsn-step" data-step="2">
            <span class="wsn-step__num">2</span>
            <span class="wsn-step__label"><?php echo esc_html__('Uhrzeit', 'wesanox-booking'); ?></span>
        </div>
        <div class="wsn-step" data-step="3">
            <span class="wsn-step__num">3</span>
            <span class="wsn-step__label"><?php echo esc_html__('Dauer', 'wesanox-booking'); ?></span>
        </div>
        <div class="wsn-step" data-step="4">
            <span class="wsn-step__num">4</span>
            <span class="wsn-step__label"><?php echo esc_html__('Raum', 'wesanox-booking'); ?></span>
        </div>
    </div>

    <!-- Step 1: Date & Persons -->
    <div class="wsn-panel" data-panel="1">
        <div class="wesanox-shortcode__fields">
            <div class="wesanox-shortcode__field">
                <label for="wsn-ts-date-<?php echo esc_attr((string) $uid); ?>">
                    <?php echo esc_html__('Datum', 'wesanox-booking'); ?> <span aria-hidden="true">*</span>
                </label>
                <input type="date"
                       class="wsn-date-input"
                       id="wsn-ts-date-<?php echo esc_attr((string) $uid); ?>"
                       min="<?php echo esc_attr($today); ?>"
                       required>
            </div>
            <div class="wesanox-shortcode__field">
                <label for="wsn-ts-persons-<?php echo esc_attr((string) $uid); ?>">
                    <?php echo esc_html__('Personen', 'wesanox-booking'); ?>
                </label>
                <input type="number"
                       class="wsn-persons-input"
                       id="wsn-ts-persons-<?php echo esc_attr((string) $uid); ?>"
                       min="1" max="20" value="2">
            </div>
            <div class="wesanox-shortcode__field wesanox-shortcode__field--action">
                <button type="button" class="wsn-to-step-2 btn btn-primary">
                    <?php echo esc_html__('Zeiten laden', 'wesanox-booking'); ?> &rsaquo;
                </button>
            </div>
        </div>
    </div>

    <!-- Step 2: Time slots -->
    <div class="wsn-panel" data-panel="2" style="display:none">
        <h4 class="wsn-date-label mb-3"></h4>
        <div class="wsn-time-slots d-flex flex-wrap" id="booking-time-box-ts-<?php echo esc_attr((string) $uid); ?>"></div>
        <div class="wsn-panel-nav mt-3">
            <button type="button" class="wsn-back btn btn-primary back">
                &lsaquo; <?php echo esc_html__('Zurück', 'wesanox-booking'); ?>
            </button>
        </div>
    </div>

    <!-- Step 3: Duration -->
    <div class="wsn-panel" data-panel="3" style="display:none">
        <h4><?php echo esc_html__('Dauer des Aufenthalts', 'wesanox-booking'); ?></h4>
        <div class="wsn-duration-wrap"></div>
        <div class="wsn-panel-nav mt-3 d-flex gap-2">
            <button type="button" class="wsn-back btn btn-primary back">
                &lsaquo; <?php echo esc_html__('Zurück', 'wesanox-booking'); ?>
            </button>
            <button type="button" class="wsn-to-step-4 btn btn-primary forward">
                <?php echo esc_html__('Weiter', 'wesanox-booking'); ?> &rsaquo;
            </button>
        </div>
    </div>

    <!-- Step 4: Room selection -->
    <div class="wsn-panel" data-panel="4" style="display:none">
        <h4><?php echo esc_html__('Wähle Deine Suite-Kategorie', 'wesanox-booking'); ?></h4>
        <div class="wsn-items d-flex flex-wrap gap-3"></div>
        <div class="wsn-panel-nav mt-3">
            <button type="button" class="wsn-back btn btn-primary back">
                &lsaquo; <?php echo esc_html__('Zurück', 'wesanox-booking'); ?>
            </button>
        </div>
    </div>

    <div class="wesanox-shortcode__loading" style="display:none" aria-live="polite">
        <span class="wesanox-shortcode__spinner"></span>
        <?php echo esc_html__('Bitte warten …', 'wesanox-booking'); ?>
    </div>
    <div class="wesanox-shortcode__errors" role="alert" style="display:none"></div>

</div>
