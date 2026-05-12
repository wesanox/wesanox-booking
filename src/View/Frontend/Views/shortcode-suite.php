<?php
/**
 * Template: Suite Booking Widget (multi-step)
 *
 * @var int    $area_id
 * @var string $redirect_url
 * @var string $title
 * @var string $nonce
 */
defined('ABSPATH') || exit;

$today    = (new DateTimeImmutable('today'))->format('Y-m-d');
$tomorrow = (new DateTimeImmutable('+1 day'))->format('Y-m-d');
static $wsn_suite_uid = 0;
$uid = ++$wsn_suite_uid;
?>
<div class="wsn-suite-widget"
     id="wsn-suite-<?php echo esc_attr((string) $uid); ?>"
     data-area-id="<?php echo esc_attr((string) $area_id); ?>"
     data-redirect="<?php echo esc_attr($redirect_url); ?>">

    <?php if ($title): ?>
        <h3 class="wesanox-shortcode__title"><?php echo esc_html($title); ?></h3>
    <?php endif; ?>

    <!-- Step indicators -->
    <div class="wsn-steps">
        <div class="wsn-step wsn-step--active" data-step="1">
            <span class="wsn-step__num">1</span>
            <span class="wsn-step__label"><?php echo esc_html__('Zeitraum', 'wesanox-booking'); ?></span>
        </div>
        <div class="wsn-step" data-step="2">
            <span class="wsn-step__num">2</span>
            <span class="wsn-step__label"><?php echo esc_html__('Suite wählen', 'wesanox-booking'); ?></span>
        </div>
    </div>

    <!-- Step 1: Date range + persons -->
    <div class="wsn-panel" data-panel="1">
        <input type="hidden" class="wsn-suite-nonce" value="<?php echo esc_attr($nonce); ?>">
        <div class="wesanox-shortcode__fields">
            <div class="wesanox-shortcode__field">
                <label for="wsn-suite-checkin-<?php echo esc_attr((string) $uid); ?>">
                    <?php echo esc_html__('Anreise', 'wesanox-booking'); ?> <span aria-hidden="true">*</span>
                </label>
                <input type="date"
                       class="wsn-checkin-input"
                       id="wsn-suite-checkin-<?php echo esc_attr((string) $uid); ?>"
                       min="<?php echo esc_attr($today); ?>"
                       required>
            </div>
            <div class="wesanox-shortcode__field">
                <label for="wsn-suite-checkout-<?php echo esc_attr((string) $uid); ?>">
                    <?php echo esc_html__('Abreise', 'wesanox-booking'); ?> <span aria-hidden="true">*</span>
                </label>
                <input type="date"
                       class="wsn-checkout-input"
                       id="wsn-suite-checkout-<?php echo esc_attr((string) $uid); ?>"
                       min="<?php echo esc_attr($tomorrow); ?>"
                       required>
            </div>
            <div class="wesanox-shortcode__field">
                <label for="wsn-suite-persons-<?php echo esc_attr((string) $uid); ?>">
                    <?php echo esc_html__('Personen', 'wesanox-booking'); ?>
                </label>
                <input type="number"
                       class="wsn-persons-input"
                       id="wsn-suite-persons-<?php echo esc_attr((string) $uid); ?>"
                       min="1" max="20" value="2">
            </div>
            <div class="wesanox-shortcode__field wesanox-shortcode__field--action">
                <button type="button" class="wsn-to-step-2 btn btn-primary">
                    <?php echo esc_html__('Verfügbarkeit prüfen', 'wesanox-booking'); ?> &rsaquo;
                </button>
            </div>
        </div>
    </div>

    <!-- Step 2: Suite selection -->
    <div class="wsn-panel" data-panel="2" style="display:none">
        <h4 class="wsn-suite-nights-label mb-3"></h4>
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
