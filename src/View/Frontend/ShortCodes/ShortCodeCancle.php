<?php

namespace Wesanox\Booking\View\Frontend\ShortCodes;

defined( 'ABSPATH' )|| exit;

use WC_Order;
use Wesanox\Booking\Application\Integration\CancellationPolicyResult;
use Wesanox\Booking\Application\Integration\FetchCancellationPolicyService;
use Wesanox\Booking\Infrastructure\WesanoxApi\WesanoxApiBridge;

class ShortCodeCancle
{
    private const CANCELLATION_REQUESTED_META_KEY = '_wesanox_cancellation_requested_at';
    /** Lets the backend tell a guest's own cancellation apart from one made in the WooCommerce admin. */
    public const CANCELLED_BY_META_KEY = '_wesanox_cancelled_by';
    private const CANCELLATION_INSURANCE_META_KEY = '_wesanox_cancellation_has_withdrawal_insurance';
    // Historical key name; the value now reflects the deadline configured per area in the backend.
    private const CANCELLATION_WINDOW_META_KEY    = '_wesanox_cancellation_inside_48h';

    /**
     * Per-request cache so that the order list does not query the backend twice per order.
     *
     * @var array<int, CancellationPolicyResult>
     */
    private array $policy_cache = [];

    public function __construct(
        private ?FetchCancellationPolicyService $policy_service = null,
    ) {
        add_action('woocommerce_email_after_order_table', [$this, 'wesanox_cancel_to_email'], 10, 4);
        add_action('wp_loaded', [$this, 'redirect_native_cancel_order_to_request'], 1);
        add_action('template_redirect', [$this, 'wesanox_cancel_order_confirm']);
        add_filter('woocommerce_my_account_my_orders_actions', [$this, 'replace_native_cancel_order_action'], 20, 2);

        add_shortcode('wesanox_cancel_order', [$this, 'wesanox_cancel_order_shortcode']);
    }

    /**
     * @return string
     */
    public function wesanox_cancel_order_shortcode(): string
    {
        if (empty($_GET['order_id']) || empty($_GET['order_key'])) {
            return '<div class="woocommerce-error">' . esc_html__('Ungültiger Link.', 'textdomain') . '</div>';
        }

        $order_id  = absint($_GET['order_id']);
        $order_key = sanitize_text_field(wp_unslash($_GET['order_key']));
        $order     = wc_get_order($order_id);

        if (!$order || $order->get_order_key() !== $order_key) {
            return '<div class="woocommerce-error">' . esc_html__('Bestellung nicht gefunden oder Schlüssel ungültig.', 'textdomain') . '</div>';
        }

        if ($this->has_cancellation_request($order)) {
            return '<div class="woocommerce-info">' . esc_html__('Für diese Bestellung liegt bereits eine Stornierungsanfrage vor. Wir prüfen Deine Anfrage und melden uns bei Dir.', 'textdomain') . '</div>';
        }

        $allowed_statuses = $this->get_cancellation_request_statuses($order);

        if (!in_array($order->get_status(), $allowed_statuses, true)) {
            return '<div class="woocommerce-info">' . esc_html__('Für diese Bestellung kann keine Stornierungsanfrage gestellt werden.', 'textdomain') . '</div>';
        }

        $nonce_field         = wp_nonce_field('confirm-cancel-order-' . $order_id, '_wpnonce_confirm_cancel', true, false);
        $cancel_url          = esc_url(wc_get_account_endpoint_url('orders'));
        $order_key_attr      = esc_attr($order_key);
        $policy              = $this->get_cancellation_policy($order);
        $can_cancel_directly = $policy->canCancelDirectly();
        $deadline_notice     = $this->build_deadline_notice($policy);

        $headline     = $can_cancel_directly ? esc_html__('Stornierung', 'textdomain') : esc_html__('Stornierungsanfrage', 'textdomain');
        $description  = $can_cancel_directly
            ? esc_html__('Hier kannst Du Deine Bestellung stornieren.', 'textdomain')
            : esc_html__('Hier kannst Du eine Stornierungsanfrage senden. Die Bestellung wird dadurch nicht automatisch storniert.', 'textdomain');
        $terms_notice = esc_html($this->build_terms_notice($policy));
        $submit_label = $can_cancel_directly ? esc_html__('Bestellung stornieren', 'textdomain') : esc_html__('Stornierungsanfrage senden', 'textdomain');
        $back_label   = esc_html__('Zur Bestellübersicht', 'textdomain');

        $html = <<<HTML
            <div class="woocommerce wesanox-cancellation-request">
                <h3>{$headline}</h3>
                <p>{$description}</p>
                <p>{$terms_notice}</p>
                {$deadline_notice}
                <form method="post">
                    {$nonce_field}
                    <input type="hidden" name="order_id" value="{$order_id}">
                    <input type="hidden" name="order_key" value="{$order_key_attr}">
                    <button type="submit" name="wesanox_submit_cancellation_request" class="button alt">
                        {$submit_label}
                    </button>
                    <a class="button" href="{$cancel_url}">
                        {$back_label}
                    </a>
                </form>
            </div>
        HTML;

        return $html;
    }

    /**
     * @param $order
     * @param $sent_to_admin
     * @param $plain_text
     * @param $email
     * @return void
     */
    public function wesanox_cancel_to_email($order, $sent_to_admin, $plain_text, $email): void
    {
        if ($email->id === 'customer_processing_order') {
            $confirm_link = $this->get_cancel_order_confirm_link($order);
            if ($confirm_link) {
                echo '<h4>' . esc_html__('Stornierung', 'textdomain') . '</h4>';
                echo '<p>' . esc_html__('Wenn Du Deine Bestellung stornieren möchtest, kannst Du über den folgenden Link die Stornierung aufrufen:', 'textdomain') . '</p>';
                echo '<p><a href="' . esc_url($confirm_link) . '">' . esc_html__('Stornierung öffnen', 'textdomain') . '</a></p>';
            }
        }
    }

    /**
     * Handles the customer cancellation request without cancelling the order.
     *
     * @return void
     */
    public function wesanox_cancel_order_confirm(): void
    {
        if ( ! isset($_POST['wesanox_submit_cancellation_request']) && ! isset($_POST['wc_confirm_cancel']) ) {
            return;
        }

        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $order_key = isset($_POST['order_key']) ? sanitize_text_field(wp_unslash($_POST['order_key'])) : '';
        $nonce = $_POST['_wpnonce_confirm_cancel'] ?? '';

        if (!$order_id || !$order_key || !wp_verify_nonce($nonce, 'confirm-cancel-order-' . $order_id)) {
            wc_add_notice(__('Ungültige Anfrage.', 'textdomain'), 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('orders'));
            exit;
        }

        $order = wc_get_order($order_id);

        if (!$order || $order->get_order_key() !== $order_key) {
            wc_add_notice(__('Bestellung nicht gefunden oder Schlüssel ungültig.', 'textdomain'), 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('orders'));
            exit;
        }

        if ($this->has_cancellation_request($order)) {
            wc_add_notice(__('Für diese Bestellung liegt bereits eine Stornierungsanfrage vor.', 'textdomain'), 'notice');
            wp_safe_redirect(wc_get_account_endpoint_url('orders'));
            exit;
        }

        $allowed_statuses = $this->get_cancellation_request_statuses($order);

        if ( ! in_array( $order->get_status(), $allowed_statuses, true ) ) {
            wc_add_notice( __( 'Für diese Bestellung kann keine Stornierungsanfrage mehr gestellt werden.', 'textdomain' ), 'notice' );
            wp_safe_redirect( wc_get_account_endpoint_url('orders') );
            exit;
        }

        $requested_at = current_time('mysql');

        // Always ask the backend again right before cancelling: the page may have been open
        // across the deadline, and only the backend decides about deadline and insurance.
        $policy = $this->get_cancellation_policy($order, refresh: true);

        if ($policy->canCancelDirectly()) {
            // Saved together with the status change, so the order webhook already carries the marker.
            $order->update_meta_data(self::CANCELLED_BY_META_KEY, 'guest');
            $order->update_status('cancelled', __('Bestellung vom Kunden storniert.', 'textdomain'));
            wc_add_notice(__('Deine Bestellung wurde storniert.', 'textdomain'), 'success');

            wp_safe_redirect(wc_get_account_endpoint_url('orders'));
            exit;
        }

        $order->update_meta_data(self::CANCELLATION_REQUESTED_META_KEY, $requested_at);
        $order->update_meta_data(self::CANCELLATION_INSURANCE_META_KEY, $policy->has_withdrawal_insurance ? 'yes' : 'no');
        $order->update_meta_data(self::CANCELLATION_WINDOW_META_KEY, $this->window_meta_value($policy));
        $order->add_order_note($this->build_cancellation_request_note($policy));
        $order->save();

        // Benachrichtigungs-Mail zur Stornierungsanfrage vorübergehend deaktiviert.
        // $this->notify_cancellation_request($order, $requested_at, $policy);

        wc_add_notice( __( 'Deine Stornierungsanfrage wurde übermittelt. Wir prüfen sie nach AGB und melden uns bei Dir.', 'textdomain' ), 'success' );

        wp_safe_redirect( wc_get_account_endpoint_url('orders') );
        exit;
    }

    /**
     * Prevent WooCommerce's native account cancel action from cancelling booked orders directly.
     *
     * @param array<string,array<string,string>> $actions
     * @param mixed                             $order
     * @return array<string,array<string,string>>
     */
    public function replace_native_cancel_order_action(array $actions, $order): array
    {
        if (!isset($actions['cancel']) || !$order instanceof WC_Order || $this->should_keep_native_cancel_order_action($order)) {
            return $actions;
        }

        $request_link = $this->get_cancel_order_confirm_link($order);

        if ($request_link === '') {
            unset($actions['cancel']);

            return $actions;
        }

        $order_number = method_exists($order, 'get_order_number') ? $order->get_order_number() : (string) $order->get_id();
        $can_cancel_directly = $this->get_cancellation_policy($order)->canCancelDirectly();

        $actions['cancel']['url']        = $request_link;
        $actions['cancel']['name']       = $can_cancel_directly ? __('Stornieren', 'textdomain') : __('Stornierung anfragen', 'textdomain');
        $actions['cancel']['aria-label'] = sprintf(
            $can_cancel_directly ? __('Stornierung zu Bestellung %s bestätigen', 'textdomain') : __('Stornierungsanfrage zu Bestellung %s stellen', 'textdomain'),
            $order_number
        );

        return $actions;
    }

    public function redirect_native_cancel_order_to_request(): void
    {
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

        if (
            ! isset($_GET['cancel_order'], $_GET['order'], $_GET['order_id'], $_GET['_wpnonce'])
            || ! wp_verify_nonce($nonce, 'woocommerce-cancel_order')
        ) {
            return;
        }

        $order_id  = absint($_GET['order_id']);
        $order_key = sanitize_text_field(wp_unslash($_GET['order']));
        $order     = wc_get_order($order_id);

        if (!$order instanceof WC_Order || $order->get_order_key() !== $order_key || $this->should_keep_native_cancel_order_action($order)) {
            return;
        }

        if ($this->has_cancellation_request($order)) {
            wc_add_notice(__('Für diese Bestellung liegt bereits eine Stornierungsanfrage vor.', 'textdomain'), 'notice');
            wp_safe_redirect(wc_get_account_endpoint_url('orders'));
            exit;
        }

        $request_link = $this->get_cancel_order_confirm_link($order);

        if ($request_link === '') {
            wc_add_notice(__('Für diese Bestellung kann keine Stornierungsanfrage gestellt werden.', 'textdomain'), 'notice');
            wp_safe_redirect(wc_get_account_endpoint_url('orders'));
            exit;
        }

        wc_add_notice(__('Bitte bestätige die Stornierung auf der nächsten Seite.', 'textdomain'), 'notice');
        wp_safe_redirect($request_link);
        exit;
    }

    /**
     * @return false|string|null
     */
    private function my_get_cancel_confirm_page_url(): false|string|null
    {
        $page = get_page_by_path('buchung-stornieren');

        return $page ? get_permalink($page->ID) : home_url('/buchung-stornieren/');
    }

    /**
     * @param $order
     * @return string
     */
    private function get_cancel_order_confirm_link($order): string
    {
        if ( ! $order instanceof WC_Order ) {
            return '';
        }

        if ($this->has_cancellation_request($order)) {
            return '';
        }

        $allowed = $this->get_cancellation_request_statuses($order);
        if ( ! in_array($order->get_status(), $allowed, true) ) {
            return '';
        }

        return add_query_arg(
            array(
                'order_id'  => $order->get_id(),
                'order_key' => $order->get_order_key(),
            ),
            $this->my_get_cancel_confirm_page_url()
        );
    }

    private function should_keep_native_cancel_order_action(WC_Order $order): bool
    {
        return method_exists($order, 'needs_payment') && $order->needs_payment();
    }

    /**
     * @return string[]
     */
    private function get_cancellation_request_statuses(WC_Order $order): array
    {
        $statuses = apply_filters('woocommerce_valid_order_statuses_for_cancel', array('pending', 'processing'), $order);

        return array_values(array_map('strval', (array) $statuses));
    }

    private function has_cancellation_request(WC_Order $order): bool
    {
        return (string) $order->get_meta(self::CANCELLATION_REQUESTED_META_KEY, true) !== '';
    }

    /**
     * Stornierbarkeit kommt ausschließlich aus dem Laravel-Backend (Frist je Area ab Buchungsbeginn,
     * Rücktrittsversicherung über dort konfigurierte Produkte).
     */
    private function get_cancellation_policy(WC_Order $order, bool $refresh = false): CancellationPolicyResult
    {
        $order_id = (int) $order->get_id();

        if (!$refresh && isset($this->policy_cache[$order_id])) {
            return $this->policy_cache[$order_id];
        }

        $this->policy_service ??= new FetchCancellationPolicyService(new WesanoxApiBridge());

        try {
            $policy = $this->policy_service->execute($order_id);
        } catch (\Throwable) {
            $policy = CancellationPolicyResult::unavailable();
        }

        return $this->policy_cache[$order_id] = $policy;
    }

    private function build_deadline_notice(CancellationPolicyResult $policy): string
    {
        $message = match (true) {
            $policy->canCancelDirectly() && $policy->cancel_until !== null => sprintf(
                __('Kostenlos stornierbar bis %s Uhr.', 'textdomain'),
                $this->format_date($policy->cancel_until)
            ),
            !$policy->available => __('Die Stornierbarkeit konnte gerade nicht geprüft werden. Du kannst eine Stornierungsanfrage senden; wir prüfen sie nach AGB.', 'textdomain'),
            $policy->reason === 'cancellation_deadline_passed' => sprintf(
                __('Die Stornofrist von %d Stunden vor dem Termin ist abgelaufen. Die Anfrage wird manuell nach AGB geprüft.', 'textdomain'),
                (int) $policy->cutoff_hours
            ),
            $policy->reason === 'booking_started' => __('Der Termin hat bereits begonnen. Die Anfrage wird manuell nach AGB geprüft.', 'textdomain'),
            $policy->can_cancel === false => __('Diese Bestellung kann nicht direkt storniert werden. Die Anfrage wird manuell nach AGB geprüft.', 'textdomain'),
            default => '',
        };

        return $message === '' ? '' : '<p class="woocommerce-info">' . esc_html($message) . '</p>';
    }

    private function build_terms_notice(CancellationPolicyResult $policy): string
    {
        if ($policy->cutoff_hours === null) {
            return __('Die Erstattung erfolgt grundsätzlich als Gutschein; eine abgeschlossene Rücktrittsversicherung wird bei der Prüfung berücksichtigt.', 'textdomain');
        }

        if ($policy->has_withdrawal_insurance) {
            return __('Mit Deiner Rücktrittsversicherung ist eine Stornierung bis zum Terminbeginn möglich. Die Erstattung erfolgt grundsätzlich als Gutschein.', 'textdomain');
        }

        return sprintf(
            __('Laut AGB ist eine Stornierung bis %d Stunden vor dem Termin möglich. Die Erstattung erfolgt grundsätzlich als Gutschein; mit Rücktrittsversicherung ist eine Stornierung bis zum Terminbeginn möglich.', 'textdomain'),
            $policy->cutoff_hours
        );
    }

    private function window_meta_value(CancellationPolicyResult $policy): string
    {
        if (!$policy->available || $policy->reason === 'no_booking') {
            return 'unknown';
        }

        return $policy->can_cancel ? 'no' : 'yes';
    }

    private function window_label(CancellationPolicyResult $policy): string
    {
        return match (true) {
            !$policy->available => 'Backend nicht erreichbar, nicht prüfbar',
            $policy->reason === 'cancellation_deadline_passed' => sprintf('Stornofrist (%d Stunden vor Termin) abgelaufen', (int) $policy->cutoff_hours),
            $policy->reason === 'booking_started' => 'Termin bereits begonnen',
            $policy->reason === 'no_booking' => 'keine Buchung zur Bestellung gefunden',
            $policy->can_cancel => 'innerhalb der Stornofrist',
            default => 'nicht direkt stornierbar (' . (string) $policy->reason . ')',
        };
    }

    private function format_date(\DateTimeImmutable $date): string
    {
        if (function_exists('wp_date')) {
            return (string) wp_date('d.m.Y H:i', $date->getTimestamp());
        }

        return $date->setTimezone(wp_timezone())->format('d.m.Y H:i');
    }

    private function build_cancellation_request_note(CancellationPolicyResult $policy): string
    {
        return sprintf(
            'Stornierungsanfrage vom Kunden eingegangen. Bitte nach AGB prüfen: Erstattung grundsätzlich als Gutschein. Rücktrittsversicherung erkannt: %s. Zeitfenster: %s.',
            $policy->has_withdrawal_insurance ? 'ja' : 'nein',
            $this->window_label($policy)
        );
    }

    private function notify_cancellation_request(
        WC_Order $order,
        string $requested_at,
        CancellationPolicyResult $policy
    ): void {
        $recipients = apply_filters(
            'wesanox_booking_cancellation_request_recipients',
            get_option('admin_email'),
            $order
        );

        if (empty($recipients)) {
            return;
        }

        $order_number = method_exists($order, 'get_order_number') ? $order->get_order_number() : (string) $order->get_id();
        $edit_url     = method_exists($order, 'get_edit_order_url')
            ? $order->get_edit_order_url()
            : admin_url('post.php?post=' . $order->get_id() . '&action=edit');
        $subject = sprintf(__('Stornierungsanfrage zu Bestellung #%s', 'textdomain'), $order_number);
        $message = sprintf(
            '<p>Es ist eine Stornierungsanfrage zu Bestellung #%s eingegangen.</p>
            <p><strong>Angefragt am:</strong> %s<br>
            <strong>Rücktrittsversicherung erkannt:</strong> %s<br>
            <strong>Stornofrist:</strong> %s</p>
            <p>Bitte nach AGB prüfen: Erstattung grundsätzlich als Gutschein.</p>
            <p><a href="%s">Bestellung öffnen</a></p>',
            esc_html((string) $order_number),
            esc_html($requested_at),
            esc_html($policy->has_withdrawal_insurance ? __('ja', 'textdomain') : __('nein', 'textdomain')),
            esc_html($this->window_label($policy)),
            esc_url($edit_url)
        );

        wp_mail($recipients, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
    }
}
