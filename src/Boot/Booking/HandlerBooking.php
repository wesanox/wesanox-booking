<?php

namespace Wesanox\Booking\Boot\Booking;

defined( 'ABSPATH' )|| exit;

class HandlerBooking
{
    public function __construct()
    {
        add_action('woocommerce_order_status_changed', [$this, 'wesanox_handle_order'], 10, 1 );
        add_action('woocommerce_checkout_order_processed', [$this, 'wesanox_handle_order'], 10, 1 );
    }

    public function wesanox_handle_order($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return 'Ungültige Bestellung.';
        }

        $start_time = $order->get_meta('Startzeit', true);
        $end_time = $order->get_meta('Endzeit', true);
        $coupon =  $this->order_contains_product($order, 2807);

        if ($coupon) {
            $response = $this->api_call_requests('handle-orders?orderId=' . $order_id);
        } else if (!$start_time || !$end_time) {
            $to = 'wester@mediamus.de, sandra@emsland-camping.de, melissa@emsland-camping.de';
            $subject = 'FEHLER BEI BESTELLUNG ' . $order_id;
            $message = 'Die Bestellung mit der ID ' . $order_id . ' fehlt die Startzeit oder Endzeit.';
            $headers = array('Content-Type: text/html; charset=UTF-8');

            $order->add_order_note('Startzeit oder Endzeit fehlt. Bitte prüfen.');

            wp_mail($to, $subject, $message, $headers);

            return 'Startzeit oder Endzeit fehlt.';
        } else {
            $response = $this->api_call_requests('handle-orders?orderId=' . $order_id);
        }

        if (is_wp_error($response)) {
            return $response->get_error_message();
        } else {
            return $response;
        }
    }

    public function api_call_requests($urlSection) {
        global $wpdb;

        include_once(ABSPATH . 'wp-admin/includes/plugin.php');

        if (!is_plugin_active('medi-api-tool/medi-api-tool.php')) {
            error_log('medi-api-tool ist nicht aktiv. API-Aufruf kann nicht ausgeführt werden.');

            return new \WP_Error('plugin_inactive', 'medi-api-tool ist nicht aktiv');
        }

        $booking_api_id = $wpdb->get_var("SELECT booking_api_id FROM " . $wpdb->prefix . "medi_booking_table WHERE id = 1");

        if (!$booking_api_id) {
            error_log('Keine API-ID gefunden.');

            return new \WP_Error('missing_api_id', 'Keine API-ID gefunden');
        }

        $api_info = $wpdb->get_row($wpdb->prepare(
            "SELECT api_url, api_key, api_secret FROM {$wpdb->prefix}medi_api_tool_table WHERE id = %d",
            $booking_api_id
        ));

        if (!$api_info) {
            error_log('API-Informationen konnten nicht abgerufen werden.');

            return new \WP_Error('missing_api_info', 'API-Informationen konnten nicht abgerufen werden');
        }

        $url = $api_info->api_url . $urlSection;

        $headers = [
            'mediApiKey'    => $api_info->api_key,
            'mediApiSecret' => $api_info->api_secret,
        ];

        // API-Aufruf
        $response = wp_remote_get($url, [
            'headers' => $headers,
            'timeout' => 15, // Timeout in Sekunden
        ]);

        if (is_wp_error($response)) {
            error_log('API-Fehler: ' . $response->get_error_message());

            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            error_log('API-Antwort war nicht erfolgreich. Statuscode: ' . $status_code);

            return new \WP_Error('api_error', 'API-Antwort war nicht erfolgreich. Statuscode: ' . $status_code);
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    private function order_contains_product( $order, $product_id ) : bool
    {
        foreach ( $order->get_items() as $item ) {
            if ( (int) $item->get_product_id() === (int) $product_id ) {
                return true;
            }
        }
        return false;
    }
}