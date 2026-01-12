<?php

namespace Wesanox\Booking\View\Frontend\ShortCodes;

defined( 'ABSPATH' )|| exit;

use WC_Order;

class ShortCodeCancle
{
    public function __construct()
    {
        add_action('woocommerce_email_after_order_table', [$this, 'wesanox_cancel_to_email'], 10, 4);
        add_action('template_redirect', [$this, 'wesanox_cancel_order_confirm']);

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

        $allowed_statuses = apply_filters('woocommerce_valid_order_statuses_for_cancel', array('pending', 'processing'), $order);

        if (!in_array($order->get_status(), $allowed_statuses, true)) {
            return '<div class="woocommerce-info">' . esc_html__('Diese Bestellung kann nicht storniert werden.', 'textdomain') . '</div>';
        }

        $nonce_field = wp_nonce_field('confirm-cancel-order-' . $order_id, '_wpnonce_confirm_cancel', true, false);
        $cancel_url  = esc_url(wc_get_account_endpoint_url('orders'));

        $html = <<<HTML
            <div class="woocommerce">
                <form method="post">
                    {$nonce_field}
                    <input type="hidden" name="order_id" value="{$order_id}">
                    <input type="hidden" name="order_key" value="{$order_key}">
                    <button type="submit" name="wc_confirm_cancel" class="button alt">
                        {esc_html__('Ja, Bestellung stornieren', 'textdomain')}
                    </button>
                    <a class="button" href="{$cancel_url}">
                        {esc_html__('Abbrechen', 'textdomain')}
                    </a>
                </form>
            </div>
        HTML;

        $html = str_replace(
            ['{esc_html__(\'Ja, Bestellung stornieren\', \'textdomain\')}', '{esc_html__(\'Abbrechen\', \'textdomain\')}'],
            [esc_html__('Ja, Bestellung stornieren', 'textdomain'), esc_html__('Abbrechen', 'textdomain')],
            $html
        );

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
                echo '<h4>' . esc_html__('Um Deine Bestellung zu stornieren, klicke auf den folgenden Link:', 'textdomain') . '</h4>';
                echo '<p><a href="' . esc_url($confirm_link) . '">' . esc_html__('Stornierung bestätigen', 'textdomain') . '</a></p>';
            }
        }
    }

    /**
     * Handles the confirmation of an order cancellation request.
     *
     * This method validates the incoming cancellation request by verifying the provided
     * order ID, order key, and nonce. If the request is invalid, it redirects to the
     * orders endpoint with an error notice. Otherwise, it checks if the order is eligible
     * for cancellation based on its status. If eligible, it constructs a URL
     * to process the cancellation and redirects the user to it.
     *
     * @return void
     */
    public function wesanox_cancel_order_confirm(): void
    {
        if ( ! isset($_POST['wc_confirm_cancel']) ) {
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

        $allowed_statuses = array( 'pending', 'processing' );

        if ( ! in_array( $order->get_status(), $allowed_statuses, true ) ) {
            wc_add_notice( __( 'Diese Bestellung kann nicht mehr storniert werden.', 'textdomain' ), 'notice' );
            wp_safe_redirect( wc_get_account_endpoint_url('orders') );
            exit;
        }

        $order->update_status( 'cancelled', __( 'Bestellung vom Kunden storniert.', 'textdomain' ) );
        wc_add_notice( __( 'Deine Bestellung wurde storniert.', 'textdomain' ), 'success' );

        wp_safe_redirect( wc_get_account_endpoint_url('orders') );
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

        $allowed = apply_filters('woocommerce_valid_order_statuses_for_cancel', array('pending','processing'), $order);
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
}