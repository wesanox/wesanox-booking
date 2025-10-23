<?php

namespace Wesanox\Booking\Woocommerce;

defined( 'ABSPATH' )|| exit;

use DateTime;
use DateTimeZone;
use Wesanox\Booking\Service\ServiceBookingTime;
use Wesanox\Booking\Service\ServiceGetAvailableRoomarts;
use Wesanox\Booking\Repository\RepositoryBooking;

class WoocommerceProductHandler
{
    protected ServiceBookingTime $service_booking_time;
    protected ServiceGetAvailableRoomarts $service_get_available_roomarts;
    protected RepositoryBooking $repository_booking;

    public function __construct()
    {
        $this->service_booking_time = new ServiceBookingTime();
        $this->service_get_available_roomarts = new ServiceGetAvailableRoomarts();
        $this->repository_booking = new RepositoryBooking();

        /**
         * Represents the current object instance in the context of a class.
         *
         * The `$this` variable is a special pseudo-variable in PHP that is used
         * to reference the current object instance within a class. It allows access to
         * the object's properties, methods, and constants.
         *
         * Characteristics:
         * - Can only be used within class methods.
         * - Context-specific; it always refers to the object invoking the method.
         * - Not available in static methods as they are not tied to a specific object instance.
         *
         * Common use cases include accessing and modifying properties or calling
         * methods of the current object.
         */
        add_action('wp_ajax_add_product_to_cart', [$this, 'add_product_to_cart_callback']);
        add_action('wp_ajax_nopriv_add_product_to_cart', [$this, 'add_product_to_cart_callback']);
        add_action('wp_ajax_delete_cart_booking', [$this, 'delete_cart_booking']);
        add_action('wp_ajax_nopriv_delete_cart_booking', [$this, 'delete_cart_booking']);
        add_action('wp_ajax_store_booking_payload', [$this, 'wesanox_store_booking_payload']);
        add_action('wp_ajax_nopriv_store_booking_payload', [$this, 'wesanox_store_booking_payload']);
        add_action('wp_ajax_delete_cart_booking', [$this, 'delete_cart_booking']);
        add_action('wp_ajax_nopriv_delete_cart_booking', [$this, 'delete_cart_booking']);
        add_action('wp_ajax_check_cart', [$this, 'ajax_check_cart']);
        add_action('wp_ajax_nopriv_check_cart', [$this, 'ajax_check_cart']);
        add_action('wp_ajax_delete_cart_only_booking', [$this, 'delete_cart_only_booking']);
        add_action('wp_ajax_nopriv_delete_cart_only_booking', [$this, 'delete_cart_only_booking']);
        add_action('wp_ajax_remove_product_from_cart', [$this, 'remove_product_from_cart']);
        add_action('wp_ajax_nopriv_remove_product_from_cart', [$this, 'remove_product_from_cart']);
        add_action('wp_ajax_refresh_cart', [$this, 'refresh_cart_callback']);
        add_action('wp_ajax_nopriv_refresh_cart', [$this, 'refresh_cart_callback']);
        add_action('wp_ajax_add_variation_to_cart', [$this, 'add_variation_to_cart']);
        add_action('wp_ajax_nopriv_add_variation_to_cart', [$this, 'add_variation_to_cart']);
        add_action('wp_ajax_wesanox_update_booking_session', [$this, 'wesanox_update_booking_session']);
        add_action('wp_ajax_nopriv_wesanox_update_booking_session', [$this, 'wesanox_update_booking_session']);

        add_action('woocommerce_admin_order_data_after_shipping_address', [$this, 'checkout_order_meta_adminpanel'], 10, 2 );
//        add_action('woocommerce_order_status_changed', [$this, 'medi_handle_order'], 10, 1 );
//        add_action('woocommerce_checkout_order_processed', [$this, 'medi_handle_order'], 10, 1 );
        add_action('woocommerce_checkout_update_order_meta', [$this, 'checkout_order_meta_update']);
        add_action('woocommerce_checkout_process', [$this, 'check_room_availability_before_order']);
        add_action('woocommerce_checkout_process', [$this, 'birthday_woocommerce_checkout_process']);
        add_action('woocommerce_product_data_panels', [$this, 'medi_product_data_fields']);
        add_action('woocommerce_process_product_meta', [$this, 'medi_save_product_data']);
        add_action('woocommerce_checkout_order_processed', [$this, 'wesanox_after_order_processed'], 20, 3);
        add_action('init', [$this, 'move_checkboxes_woocommerce'], 50);


        add_filter('gettext', [$this, 'medi_cancel_order_button_text'], 20, 3);
        add_filter('woocommerce_checkout_fields', [$this, 'add_custom_checkout_field']);
        add_filter('woocommerce_checkout_fields' , [$this, 'default_address_fields_override']);
        add_filter('woocommerce_account_menu_items', [$this, 'hide_downloads_account_menu_item'], 10, 1);
        add_filter('woocommerce_valid_order_statuses_for_cancel', [$this, 'medi_cancellable_status'], 20, 2);
        add_filter('woocommerce_account_orders_columns', [$this, 'medi_account_orders_column_header']);
        add_filter('woocommerce_email_attachments', [$this, 'add_ics_attachment_to_email'], 10, 4);
        add_filter('woocommerce_product_data_tabs', [$this, 'medi_product_data_tab']);
        add_filter('woocommerce_form_field_heading', [$this, 'add_heading_to_additional_field'], 10, 4);

        add_filter('woocommerce_cart_needs_shipping_address', function($needs){
            return true; // erzwingt Anzeige des "An eine andere Adresse liefern?" Bereichs
        }, 10, 1);
    }

    /**
     * @return void
     */
    public function wesanox_update_booking_session()
    {
        if (!function_exists('WC') || !WC()->session) {
            wp_send_json_error('Keine Session gefunden');
        }

        $extras_json = isset($_POST['extras']) ? wp_unslash($_POST['extras']) : '[]';
        $extras = json_decode($extras_json, true);
        if (!is_array($extras)) {
            $extras = '';
        }

        $booking = (array)WC()->session->get('booking', []);
        $booking['extras'] = $extras;
        WC()->session->set('booking', $booking);

        wp_send_json_success(['extras' => $extras]);
    }

    /**
     * Refresh the card with AJAX
     *
     * @return void
     */
    public function refresh_cart_callback()
    {
        if(!wp_doing_ajax()) {
            die('Ungültige Anfrage');
        }

        echo do_shortcode('[woocommerce_cart]');

        wp_die();
    }


    public function wesanox_store_booking_payload(): void
    {
        if ( ! isset($_POST['payload']) ) {
            wp_send_json_error(['msg' => 'payload missing'], 400);
        }
        $raw  = wp_unslash($_POST['payload']);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            wp_send_json_error(['msg' => 'invalid json'], 400);
        }

        $booking = [
            'person_count' => isset($data['person_count']) ? (int)$data['person_count'] : null,
            'day'          => !empty($data['day'])         ? sanitize_text_field($data['day']) : null,
            'start_time'   => !empty($data['start_time'])  ? sanitize_text_field($data['start_time']) : null,
            'stop_time'    => !empty($data['stop_time'])   ? sanitize_text_field($data['stop_time']) : null,
            'how_long'     => isset($data['how_long'])     ? (int)$data['how_long'] : null,
            'product_id'   => !empty($data['product_id'])  ? sanitize_text_field($data['product_id']) : null,
            'extras'       => (isset($data['extras'])) ? $data['extras'] : [],
        ];

        $booking['start'] = ($booking['day'] && $booking['start_time']) ? "{$booking['day']} {$booking['start_time']}" : null;
        $booking['stop']  = ($booking['day'] && $booking['stop_time'])  ? "{$booking['day']} {$booking['stop_time']}"  : null;

        if ( function_exists('WC') && WC()->session ) {
            WC()->session->set('booking', $booking);
        }

        wp_send_json_success(['ok' => true]);
    }

    public function ajax_check_cart() {
        $cart_contents_count = WC()->cart->get_cart_contents_count();

        if($cart_contents_count > 0){
            echo 'not_empty';
        } else {
            echo 'empty';
        }

        wp_die();
    }

    public function add_product_to_cart_callback() {
        // 1) Sicherheits-Check (aktivieren, wenn du bereit bist)
        // if ( empty($_POST['nonce']) || ! wp_verify_nonce( $_POST['nonce'], 'booking_nonce' ) ) {
        //     wp_send_json_error(['message' => 'Ungültige Anfrage (Nonce).'], 403);
        // }

        if ( ! function_exists('WC') || ! WC()->cart || ! WC()->session ) {
            wp_send_json_error(['message' => 'Warenkorb/Session nicht verfügbar.'], 500);
        }

        $cart    = WC()->cart;
        $session = WC()->session;

        $booking_session = (array) $session->get('booking', []);

        $product_ids_raw = isset($_POST['product_id']) ? (string) $_POST['product_id'] : ($booking_session['product_id'] ?? '');
        $person = isset($_POST['person_count']) ? (int) $_POST['person_count'] : (int)($booking_session['person_count'] ?? 1);
        $day = isset($_POST['day']) ? sanitize_text_field( wp_unslash($_POST['day']) ) : (string) ($booking_session['day'] ?? '');
        $start_time = isset($_POST['start_time']) ? sanitize_text_field( wp_unslash($_POST['start_time']) ) : (string) ($booking_session['start_time'] ?? '');
        $stop_time = isset($_POST['stop_time'])  ? sanitize_text_field( wp_unslash($_POST['stop_time']) ) : (string) ($booking_session['stop_time'] ?? '');
        $duration = isset($_POST['how_long']) ? (int) $_POST['how_long'] : (int) ($booking_session['how_long'] ?? 0);

        if ( $product_ids_raw === '' ) {
            wp_send_json_error(['message' => 'product_id fehlt.'], 400);
        }

        $product_ids = array_values( array_filter( array_map('intval', explode(',', $product_ids_raw)) ) );

        if ( empty($product_ids) ) {
            wp_send_json_error(['message' => 'Keine gültigen Produkt-IDs.'], 400);
        }

        $start = ($day && $start_time) ? ($day . ' ' . $start_time) : '';
        $stop  = ($day && $stop_time)  ? ($day . ' ' . $stop_time)  : '';

        $vh = $vtm = $nh = $ntm = 0.0;
        if ( $start && $stop ) {
            $diff = json_decode( $this->service_booking_time->getBookingTimeDifferenceBetween($start, $stop) );

            $vh   = (float) ($diff->vth  ?? 0);
            $vtm  = (float) ($diff->vtmf ?? 0);
            $nh   = (float) ($diff->nth  ?? 0);
            $ntm  = (float) ($diff->ntmf ?? 0);
        }

        $hours_vm  = $vh + $vtm;
        $hours_nm  = $nh + $ntm;
        $hours_all = $hours_vm + $hours_nm;

        if ( $hours_all <= 0 && $duration > 0 ) {
            $hours_vm = 0;
            $hours_nm = 0;
            $hours_all = (float) $duration;
        }

        $cart->empty_cart();

        $added = [];

        foreach ($product_ids as $pid) {
            if ( in_array($pid, [251, 254], true) ) {
                $quantity = $person * $hours_vm;
            } elseif ( in_array($pid, [252, 255], true) ) {
                $quantity = $person * $hours_nm;
            } else {
                $quantity = $person * $hours_all;
            }

            // Wenn $pid eine VARIATION ist:
            // $parent_id = wp_get_post_parent_id($pid) ?: 0;
            // if ( $parent_id ) {
            //     $key = $cart->add_to_cart($parent_id, $quantity, $pid, /* $variation_attributes */ [], /* $cart_item_data */ []);
            // } else {
            //     $key = $cart->add_to_cart($pid, $quantity, 0, [], []);
            // }

            // Wenn $pid ein simples Produkt ist:
            $cart_item_data = [];
            // Optional: eindeutigen Marker setzen, falls du Merging vermeiden willst
            // $cart_item_data['_line_uid'] = uniqid('booking_', true);

            // Optional: Metadaten mitschreiben (aber Achtung: identische Daten → Merge)
            // $cart_item_data['booking_meta'] = compact('day','start_time','stop_time','person','duration');

            $key = $cart->add_to_cart($pid, $quantity, 0, [], $cart_item_data);

            if ( $key ) {
                $added[] = ['product_id' => $pid, 'quantity' => $quantity];
            }
        }

        if ( empty($added) ) {
            wp_send_json_error(['message' => 'Kein Produkt konnte in den Warenkorb gelegt werden.'], 500);
        }

         $session->set('booking', [
             'person_count' => $person,
             'how_long'     => $duration,
             'day'          => $day,
             'start_time'   => $start_time,
             'stop_time'    => $stop_time,
             'product_id'   => implode(',', $product_ids),
         ]);

        wp_send_json_success([
            'message' => (count($added) > 1) ? 'Produkte hinzugefügt' : 'Produkt hinzugefügt',
            'items'   => $added,
        ]);
    }

    /**
     * Remove product from cart
     *
     * @return void
     */
    public function remove_product_from_cart()
    {
        if(!wp_doing_ajax()) {
            die('Ungültige Anfrage');
        }

        if (WC()->cart->get_cart_contents_count() == 0) {
            wp_send_json_error('Warenkorb ist leer.');
            wp_die();
        }

        $product_id = intval($_POST['product_id']);
        $cart_items = WC()->cart->get_cart();

        if ( $product_id ) {
            foreach($cart_items as $cart_item_key => $item) {
                if($item['product_id'] == $product_id) {
                    WC()->cart->remove_cart_item($cart_item_key);
                    break;
                }

                if($item['variation_id'] == $product_id) {
                    WC()->cart->remove_cart_item($cart_item_key);
                    break;
                }
            }

            echo 'success';
        }

        wp_die();
    }

    /**
     * @TODO Duplicate functions? Check this...
     *
     * @return void
     */
    public function delete_cart_booking() {
        if(!wp_doing_ajax()) {
            die('Ungültige Anfrage');
        }

        if ( method_exists(WC()->session, '__unset') ) {
            WC()->session->__unset('booking');
        } else {
            WC()->session->set('booking', null);
        }

        WC()->cart->empty_cart();

        wp_die();
    }

    public function delete_cart_only_booking() {
        if(!wp_doing_ajax()) {
            die('Ungültige Anfrage');
        }

        if ( method_exists(WC()->session, '__unset') ) {
            WC()->session->__unset('booking');
        } else {
            WC()->session->set('booking', null);
        }

        WC()->cart->empty_cart();

        wp_die();
    }

    /**
     * Get products to card
     *
     * @return void
     * @throws Exception
     */
    public function add_product_to_cart_upsell_callback() {
        $product_id = intval($_POST['product_id']);
        $quant      = intval($_POST['quant']);

        if ( $product_id ) {
            WC()->cart->add_to_cart($product_id, $quant);
            echo 'success';
        }

        wp_die();
    }

    /**
     * Get variation to card
     *
     * @return void
     * @throws Exception
     */
    public function add_variation_to_cart() {
        // Optional: Nonce prüfen
        // if ( empty($_POST['nonce']) || ! wp_verify_nonce( $_POST['nonce'], 'booking_nonce' ) ) {
        //     wp_send_json_error(['message' => 'Ungültige Anfrage (Nonce).'], 403);
        // }

        if ( ! isset($_POST['variation_id']) ) {
            wp_send_json_error('variation_id fehlt!', 400);
        }

        if ( ! function_exists('WC') || ! WC()->cart || ! WC()->session ) {
            wp_send_json_error('Warenkorb/Session nicht verfügbar.', 500);
        }

        $variation_id   = absint($_POST['variation_id']);

        // Menge ermitteln (deine Sonderfälle beibehalten)
        $quantity_raw   = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        $quant_raw      = isset($_POST['quant']) ? intval($_POST['quant']) : $quantity_raw;

        if ( in_array($variation_id, [95,96], true) ) {
            $qty = 1;
        } elseif ( $variation_id === 235 ) {
            $qty = max(1, $quant_raw);
        } else {
            $qty = max(1, $quantity_raw);
        }

        // Variation korrekt hinzufügen
        $parent_id = wp_get_post_parent_id($variation_id);
        // Variation-Attribute aus AJAX übernehmen (falls vorhanden), z.B. ["attribute_pa_farbe" => "rot"]
        $variation_attributes = [];
        if ( isset($_POST['variation']) && is_array($_POST['variation']) ) {
            foreach ($_POST['variation'] as $k => $v) {
                $variation_attributes[wc_clean($k)] = wc_clean($v);
            }
        }

        // add_to_cart: (product_id, qty, variation_id, variation_attributes, cart_item_data)
        // Wenn keine Parent-ID vorhanden (zur Sicherheit), nimm variation_id als product_id.
        $product_id = $parent_id ? $parent_id : $variation_id;

        $cart_item_key = WC()->cart->add_to_cart($product_id, $qty, $parent_id ? $variation_id : 0, $variation_attributes, /*$cart_item_data*/[]);

        if ( ! $cart_item_key ) {
            wp_send_json_error('Fehler beim Hinzufügen der Variation zum Warenkorb!', 500);
        }

        // --- WC Session: Booking-Daten ablegen (kompatibel zu deiner Payload-Funktion) ---
        $session = WC()->session;
        $existing = (array) $session->get('booking', []);

        // Werte aus POST übernehmen; fallback auf vorhandene Session-Werte
        $person_count = isset($_POST['person_count']) ? (int) $_POST['person_count'] : (int) ($existing['person_count'] ?? 0);
        $day          = isset($_POST['day'])         ? sanitize_text_field( wp_unslash($_POST['day']) )         : (string) ($existing['day'] ?? '');
        $start_time   = isset($_POST['start_time'])  ? sanitize_text_field( wp_unslash($_POST['start_time']) )  : (string) ($existing['start_time'] ?? '');
        $stop_time    = isset($_POST['stop_time'])   ? sanitize_text_field( wp_unslash($_POST['stop_time']) )   : (string) ($existing['stop_time'] ?? '');
        $how_long     = isset($_POST['how_long'])    ? (int) $_POST['how_long']                                 : (int) ($existing['how_long'] ?? 0);

        // optional: extras aus POST (Array oder JSON) übernehmen
        $extras = [];
        if ( isset($_POST['extras']) ) {
            $extras = is_array($_POST['extras']) ? $_POST['extras'] : json_decode(wp_unslash($_POST['extras']), true);
            if ( ! is_array($extras) ) { $extras = []; }
        } elseif ( isset($_POST['extra_select']) ) {
            // falls du noch ein einzelnes Feld nutzt
            $extras = ['extra_select' => sanitize_text_field( wp_unslash($_POST['extra_select']) )];
        } else {
            // Fallback: vorhandene Extras aus Session
            $extras = isset($existing['extras']) ? (array) $existing['extras'] : [];
        }

        $booking = [
            'person_count' => $person_count ?: null,
            'day'          => $day         ?: null,
            'start_time'   => $start_time  ?: null,
            'stop_time'    => $stop_time   ?: null,
            'how_long'     => $how_long    ?: null,
            'extras'       => json_encode($extras),
        ];

        $booking['start'] = ($booking['day'] && $booking['start_time']) ? ($booking['day'].' '.$booking['start_time']) : null;
        $booking['stop']  = ($booking['day'] && $booking['stop_time'])  ? ($booking['day'].' '.$booking['stop_time'])  : null;

        // Vorhandene Werte nicht verlieren, gezielt überschreiben
        $session->set('booking', array_merge($existing, $booking));

        // Erfolg
        wp_send_json_success([
            'message'         => 'Variation zum Warenkorb hinzugefügt!',
            'cart_item_key'   => $cart_item_key,
            'session_booking' => $session->get('booking'),
        ]);

        wp_die();
    }

    /**
     * Get Override default address fields
     *
     * @param $address_fields
     * @return array
     */
    public function default_address_fields_override( $address_fields ) {
        unset($address_fields['billing']['billing_company']);
        unset($address_fields['billing']['billing_address_2']);
        unset($address_fields['billing']['billing_state']);

        return $address_fields;
    }

    /**
     * Custom checkout fields
     *
     * @param $fields
     * @return mixed
     */
    public function add_custom_checkout_field( $fields ) {
        $booking = ( function_exists('WC') && WC()->session ) ? WC()->session->get('booking', []) : [];
        $person = $booking['person_count'];
        $start = $booking['day'] . ' ' . $booking['start_time'];
        $stop = $booking['day'] . ' ' . $booking['stop_time'];

        if( $start != '' && strtotime($start) > time() && $stop != '' && strtotime($stop) > time() ) {
            $fields['billing']['billing_birthdate'] = array(
                'label' => __('Geburtstag', 'woocommerce'),
                'required' => true,
                'class' => array('form-row-wide'),
                'clear' => true,
                'type' => 'date',
            );
            $fields['billing']['start_date'] = array(
                'label' => __('Buchung - Startzeit', 'woocommerce'),
                'placeholder' => _x(date('d.m.Y H:i', strtotime($start)), 'placeholder', 'woocommerce'),
                'default' => date('d.m.Y H:i', strtotime($start)),
                'required' => true,
                'class' => array('form-row-first'),
                'clear' => true,
                'custom_attributes' => array('readonly' => 'readonly')
            );
            $fields['billing']['end_date'] = array(
                'label' => __('Buchung - Endzeit', 'woocommerce'),
                'placeholder' => _x(date('d.m.Y H:i', strtotime($stop)), 'placeholder', 'woocommerce'),
                'default' => date('d.m.Y H:i', strtotime($stop)),
                'required' => true,
                'class' => array('form-row-last'),
                'clear' => true,
                'custom_attributes' => array('readonly' => 'readonly')
            );
            $fields['billing']['person'] = array(
                'label' => __('Buchung - Personenanzahl', 'woocommerce'),
                'placeholder' => _x($person, 'placeholder', 'woocommerce'),
                'default' => $person,
                'required' => true,
                'class' => array('form-row-last d-none'),
                'clear' => true,
                'custom_attributes' => array('readonly' => 'readonly')
            );
        }

        if ( $person != '' ) {
            $fields['order']["besucher_heading"] = array(
                'type' => 'heading',
            );

            for($i = 2; $i <= $person; $i++) {
                $fields['order']["additional_person_{$i}_firstname"] = array(
                    'label' => __("Besucher {$i}", 'woocommerce'),
                    'placeholder'      => __( "Vorname", 'woocommerce' ),
                    'required'   => true,
                    'class'      => array( 'form-row-first' ),
                    'clear'      => true,
                );
                $fields['order']["additional_person_{$i}_lastname"] = array(
                    'placeholder'      => __( "Nachname", 'woocommerce' ),
                    'required'   => true,
                    'class'      => array( 'form-row-last' ),
                    'clear'      => true,
                );
                $fields['order']["additional_person_{$i}_email"] = array(
                    'placeholder'      => __( "E-Mail", 'woocommerce' ),
                    'required'   => false,
                    'class'      => array( 'form-row-first' ),
                    'clear'      => true,
                    'validate'   => array( 'email' ),
                );
                $fields['order']["additional_person_{$i}_birthdate"] = array(
                    'placeholder'      => __( "Geburtstag - Besucher {$i}", 'woocommerce' ),
                    'required'   => true,
                    'class'      => array( 'form-row-last' ),
                    'clear'      => true,
                    'type'       => 'date',
                );
            }
        }

        return $fields;
    }

    public function wesanox_after_order_processed( $order_id, $posted, $order ) {
        if ( ! $order instanceof \WC_Order ) {
            $order = wc_get_order( (int) $order_id );
            if ( ! $order ) {
                error_log('wesanox_after_order_processed: order not found for id '.$order_id);
                return;
            }
        }

        global $wpdb;

        $booking = ( function_exists('WC') && WC()->session ) ? (array) WC()->session->get('booking', []) : [];
        if (empty($booking)) return;

        $day        = $booking['day']        ?? null;
        $start_time = $booking['start_time'] ?? null;
        $stop_time  = $booking['stop_time']  ?? null;
        if (!$day || !$start_time || !$stop_time) return;

        $room_id = (int) $order->get_meta('_booking_room_id');
        if (!$room_id) {
            $roomart_id = null;
            foreach (WC()->cart->get_cart() as $item) {
                if ((int)$item['product_id'] === 75) $roomart_id = 1;
                if ((int)$item['product_id'] === 91) $roomart_id = 2;
            }
            if ($roomart_id) {
                $room_id = (int) $this->service_get_available_roomarts->wesanox_find_room($roomart_id, $day, $start_time.':00', $stop_time.':00', 30);
            }
        }
        if (!$room_id) return;

        $customer_id = (int) $order->get_customer_id(); // 0 bei Gast
        if ( $customer_id > 0 ) {
            $exists = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(1) FROM {$wpdb->prefix}wc_customer_lookup WHERE customer_id = %d",
                $customer_id
            ) );
            if ( $exists === 0 ) {
                $customer_id = null;
            }
        } else {
            $customer_id = null;
        }

        $booking_id = $this->repository_booking->insert_booking(
            $day,
            $start_time . ':00',
            $stop_time  . ':00',
            (int) $room_id,
            (int) $order->get_id(),
            $customer_id
        );

        if ( $booking_id ) {
            $order->update_meta_data('_booking_id',      $booking_id);
            $order->update_meta_data('_booking_room_id', (int)$room_id);
            $order->update_meta_data('_booking_date',    $day);
            $order->update_meta_data('_booking_from',    $start_time . ':00');
            $order->update_meta_data('_booking_to',      $stop_time  . ':00');
            $order->save();
        }

        WC()->session->__unset('booking');
        WC()->session->__unset('booking_free_room_id');
    }


    public function check_room_availability_before_order()
    {
        $booking = ( function_exists('WC') && WC()->session ) ? (array) WC()->session->get('booking', []) : [];

        $day        = $booking['day'] ?? null;           // "YYYY-MM-DD"
        $start_time = $booking['start_time'] ?? null;    // "HH:MM"
        $stop_time  = $booking['stop_time'] ?? null;     // "HH:MM"

        if (!$day || !$start_time || !$stop_time) {
            wc_add_notice(__('Bitte wähle Check-in-Tag und Start-/Endzeit neu aus.'), 'error');
            return;
        }

        $roomart_id = null;
        foreach (WC()->cart->get_cart() as $item) {
            if ((int)$item['product_id'] === 75) $roomart_id = 1;
            if ((int)$item['product_id'] === 91) $roomart_id = 2;
        }
        if ($roomart_id === null) return;

        $free_room_id = $this->service_get_available_roomarts->wesanox_find_room($roomart_id, $day, $start_time.':00', $stop_time.':00', 30);

        if (!$free_room_id) {
            wc_add_notice(__('Der gewünschte Zeitraum ist nicht mehr verfügbar. Bitte wähle einen anderen Zeitraum.'), 'error');
            return;
        }

        WC()->session->set('booking_free_room_id', (int)$free_room_id);
    }

    /**
     * Update the order meta with field value
     *
     * @param $order_id
     * @return void
     */
    public function checkout_order_meta_update( $order_id ) {
        $person = $_POST['person'];

        if ( ! empty( $_POST['billing_birthdate'] ) ) {
            $order = wc_get_order( $order_id );
            $order->update_meta_data( 'Geburtstag', sanitize_text_field( $_POST['billing_birthdate'] ) );
            $order->save_meta_data();
        }

        if ( ! empty( $_POST['end_date'] ) ) {
            $order = wc_get_order( $order_id );
            $order->update_meta_data( 'Endzeit', sanitize_text_field( $_POST['end_date'] ) );
            $order->save_meta_data();
        }

        if ( ! empty( $_POST['start_date'] ) ) {
            $order = wc_get_order( $order_id );
            $order->update_meta_data( 'Startzeit', sanitize_text_field( $_POST['start_date'] ) );
            $order->save_meta_data();
        }

        if ( ! empty( $_POST['person'] ) ) {
            $order = wc_get_order( $order_id );
            $order->update_meta_data( 'Person', sanitize_text_field( $_POST['person'] ) );
            $order->save_meta_data();
        }

        if ( $person != '' ) {
            for($i = 2; $i <= $person; $i++) {
                if ( ! empty( $_POST["additional_person_{$i}_firstname"] ) ) {
                    $order = wc_get_order( $order_id );
                    $order->update_meta_data( "Vorname - Besucher {$i}", sanitize_text_field( $_POST["additional_person_{$i}_firstname"] ) );
                    $order->save_meta_data();
                }

                if ( ! empty( $_POST["additional_person_{$i}_lastname"] ) ) {
                    $order = wc_get_order( $order_id );
                    $order->update_meta_data( "Nachname - Besucher {$i}", sanitize_text_field( $_POST["additional_person_{$i}_lastname"] ) );
                    $order->save_meta_data();
                }

                if ( ! empty( $_POST["additional_person_{$i}_email"] ) ) {
                    $order = wc_get_order( $order_id );
                    $order->update_meta_data( "E-Mail - Besucher {$i}", sanitize_email( $_POST["additional_person_{$i}_email"] ) );
                    $order->save_meta_data();
                }

                if ( ! empty( $_POST["additional_person_{$i}_birthdate"] ) ) {
                    $order = wc_get_order( $order_id );
                    $order->update_meta_data( "Geburtstag - Besucher {$i}", sanitize_text_field( $_POST["additional_person_{$i}_birthdate"] ) );
                    $order->save_meta_data();
                }
            }
        }

        if ( $_SESSION['extra_select'] != ''  ) {
            $order = wc_get_order( $order_id );
            $order->update_meta_data( 'Extra', sanitize_text_field( json_encode($_SESSION['extra_select']) ) );
            $order->save_meta_data();
        }

        session_unset();
        session_destroy();
    }

    /**
     * Display field value on the order edit page
     *
     * @param $order
     * @return void
     */
    public function checkout_order_meta_adminpanel( $order ){
        if( $order->get_meta( 'Startzeit', true ) && $order->get_meta( 'Endzeit', true) ) {
            echo '
            <p>
                <strong>' . esc_html__( 'Startzeit' ) . ':</strong> ' . esc_html( $order->get_meta( 'Startzeit', true ) ) . '
            </p>
            <p>
                <strong>' . esc_html__( 'Endzeit' ) . ':</strong> ' . esc_html( $order->get_meta( 'Endzeit', true ) ) . '
            </p>';
        }
    }

    /**
     * Functions for checking the birthday´s
     */
    public function birthday_woocommerce_checkout_process() {
        $booking = ( function_exists('WC') && WC()->session ) ? WC()->session->get('booking', []) : [];

        $person = $booking['person_count'];

        if ( isset( $_POST['billing_birthdate'] ) ) {
            $birthdate = DateTime::createFromFormat( "Y-m-d", $_POST['billing_birthdate'] );
            $now = new DateTime();

            $interval = $birthdate->diff($now);
            $age = $interval->y;

            if ( $age < 18 ) {
                wc_add_notice( __( 'Sie müssen mindestens 18 Jahre alt sein, um die Buchung zu tätigen.', 'woocommerce' ), 'error' );
            }
        }

        for($i = 2; $i <= $person; $i++) {
            if ( isset( $_POST["additional_person_{$i}_birthdate"] )  && !empty( $_POST["additional_person_{$i}_birthdate"] ) ) {
                $birthdate = DateTime::createFromFormat( "Y-m-d", $_POST["additional_person_{$i}_birthdate"] );
                $now = new DateTime();

                $interval = $birthdate->diff($now);
                $age = $interval->y;

                if ( $age < 10 ) {
                    wc_add_notice( __( 'Begleitpersonen müssen mindestens 10 Jahre alt sein, um die Buchung zu tätigen.', 'woocommerce' ), 'error' );
                }
            } elseif ( ( isset( $_POST["additional_person_{$i}_firstname"] ) && !empty( $_POST["additional_person_{$i}_firstname"] ) ) || ( isset( $_POST["additional_person_{$i}_lastname"] ) && !empty( $_POST["additional_person_{$i}_lastname"] ) ) || ( isset( $_POST["additional_person_{$i}_email"] ) && !empty( $_POST["additional_person_{$i}_email"] ) ) ) {
                if (empty( $_POST["additional_person_{$i}_birthdate"] )) {
                    wc_add_notice( __( 'Begleitpersonen müssen mindestens 10 Jahre alt sein, um die Buchung zu tätigen. Deshalb müssen Sie zu Ihrer Begleitperson ein Alter angeben.', 'woocommerce' ), 'error' );
                }
            }
        }
    }

    /**
     * Move the checkboxes to the end of the checkoutlist
     *
     * @return void
     */
    public function move_checkboxes_woocommerce() {
        remove_action( 'woocommerce_review_order_after_payment', 'woocommerce_gzd_template_render_checkout_checkboxes', 10 );
//    remove_action( 'woocommerce_before_checkout_registration_form', 'woocommerce_checkout_registration_form' , 10);

        add_action( 'woocommerce_gzd_review_order_before_submit', 'woocommerce_gzd_template_render_checkout_checkboxes', 10 );
//    add_action( 'woocommerce_after_checkout_registration_form', 'woocommerce_checkout_registration_form' , 10);
    }

    /**
     * Header for the additional Persons
     *
     * @param $no_html
     * @param $key
     * @param $args
     * @param $value
     * @return string|void
     */
    public function add_heading_to_additional_field($no_html, $key, $args, $value) {
        if ('heading' === $args['type']) {
            $html = '
            <h3 class="mt-3">' . __("Begleitpersonen", "woocommerce") . '</h3>
            <p>' . __("Hier könnt ihr bereits vorab Informationen zu eurer / n Begleitperson / en hinterlegen, um eine Wartezeit bei der Anmeldung vorzubeugen.", "woocommerce") . '</p>
            ';

            return $html;
        }
    }

    /**
     * Create the cancellable status in the cosumter login
     *
     * @param $statuses
     * @param $order
     * @return string[]
     */
    public function medi_cancellable_status ($statuses, $order){
        $time_offset    = 2 * 60 * 60;
        $abort_offset   = 48 * 60 * 60;

        $server_time = time();
        $correct_time = $server_time + $time_offset;


        $order_date = strtotime( $order->get_meta( 'Startzeit', true ) );
        $is_order_recent = ( $order_date - $abort_offset ) >= $correct_time;

        if($is_order_recent){
            // return our custom statuses
            return array('pending', 'processing', 'on-hold');
        }else{
            // return the default statuses
            return $statuses;
        }
    }

    /**
     * Remove the Downloadpage form the account page in WooCommerce
     *
     * @param $items
     * @return mixed
     */
    public function hide_downloads_account_menu_item($items) {
        // Entferne den Menüpunkt "Downloads"
        unset($items['downloads']);
        return $items;
    }

    /**
     * renamened the abort button in the account page
     *
     * @param $translated_text
     * @param $text
     * @param $domain
     * @return mixed|string
     */
    public function medi_cancel_order_button_text($translated_text, $text, $domain) {
        if ($translated_text == 'Abbrechen') { // Originaltext des Buttons in Deutsch
            $translated_text = 'Stornieren'; // Neuer Text des Buttons
        }
        return $translated_text;
    }

    /**
     * Add new columns in the account backend
     *
     * @param $columns
     * @return array
     */
    public function medi_account_orders_column_header($columns) {
        $new_columns = array();

        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            if ($key === 'order-date') {
                // Neue Spalte nach der Spalte "Bestellsumme" hinzufügen
                $new_columns['order-start-date'] = __('Startdatum', 'textdomain');
                $new_columns['order-stop-date'] = __('Enddatum', 'textdomain');
            }
        }

        return $new_columns;
    }

    /**
     * Add event / booking details to the mail
     *
     * @param $event_details
     * @return string
     */
    public function generate_ics($event_details) {
        // Zeitzone auf lokales Event setzen
        $timezone = new DateTimeZone('Europe/Berlin');

        // Startzeit in lokaler Zeitzone erstellen
        $start_time = new DateTime($event_details['start_time'], $timezone);
        // Endzeit in lokaler Zeitzone erstellen
        $end_time = new DateTime($event_details['end_time'], $timezone);

        // ICS-Inhalt erzeugen
        $ics_content = "BEGIN:VCALENDAR\r\n";
        $ics_content .= "VERSION:2.0\r\n";
        $ics_content .= "BEGIN:VEVENT\r\n";
        $ics_content .= "DTSTART;TZID=Europe/Berlin:" . $start_time->format('Ymd\THis') . "\r\n";
        $ics_content .= "DTEND;TZID=Europe/Berlin:" . $end_time->format('Ymd\THis') . "\r\n";
        $ics_content .= "SUMMARY:" . $event_details['summary'] . "\r\n";
        $ics_content .= "DESCRIPTION:" . $event_details['description'] . "\r\n";
        $ics_content .= "LOCATION:" . $event_details['location'] . "\r\n";
        $ics_content .= "END:VEVENT\r\n";
        $ics_content .= "END:VCALENDAR\r\n";

        return $ics_content;
    }

    public function add_ics_attachment_to_email($attachments, $email_id, $order, $email) {
        if ($email_id == 'customer_processing_order' || $email_id == 'customer_completed_order') {
            $event_details = array(
                'start_time' => $order->get_meta( 'Startzeit', true ),
                'end_time' => $order->get_meta( 'Endzeit', true ),
                'summary' => 'Deine Buchung bei Veen - Spa',
                'description' => 'Vielen Dank für Deine Buchung.',
                'location' => 'Harener Straße 36, 49733 Haren (Ems)'
            );

            $ics_content = $this->generate_ics($event_details);
            $upload_dir = wp_upload_dir();
            $ics_file = $upload_dir['basedir'] . '/buchung.ics';

            file_put_contents($ics_file, $ics_content);

            $attachments[] = $ics_file;
        }

        return $attachments;
    }

    /**
     * Product backend Tabs for the Veen Spa Options
     *
     * @param $tabs
     * @return mixed
     */
    public function medi_product_data_tab($tabs) {
        $tabs['veen_spa'] = array(
            'label'    => __('Veen Spa Optionen', 'woocommerce'),
            'target'   => 'veen_spa_product_data',
            'class'    => array('show_if_simple', 'show_if_variable'),
            'priority' => 100,
        );
        return $tabs;
    }

    public function medi_product_data_fields() {
        global $woocommerce, $post;

        echo '
        <div id="veen_spa_product_data" class="panel woocommerce_options_panel">
            <p class="wc-gzd-product-settings-subtitle">' . __('Veen Spa Optionen', 'woocommerce') . '</p>
            <div class="options_group">
                <p>
                    <strong>' . __('Wie sollen die Eigenschaften für den User auswählbar sein?', 'woocommerce') . '</strong>
                </p>';

        // Option
        woocommerce_wp_radio(array(
            'id'      => '_veen_spa_option_1',
            'label'   => __('Umgang mit Eigenschaften', 'woocommerce'),
            'options' => array(
                '1' => __('Eigentschaften pro Person auswählen', 'woocommerce'),
                '2' => __('Eigentschaft via Radio Button auswählen', 'woocommerce'),
                '3' => __('Anzahl des Produkts auswählbar machen', 'woocommerce'),
            ),
        ));

        echo '
            </div>
            <div class="options_group">
                <p>
                    <strong>' . __('Soll ein Produkt auch im Shopkatalog sichtbar sein?', 'woocommerce') . '</strong>
                </p>';

        // Checkbox
        woocommerce_wp_checkbox(array(
            'id'      => '_veen_spa_option_2',
            'label'   => __('Einzelverkauf', 'woocommerce'),
            'description' => __('Produkt auch außerhalb des Buchungstools zu verkaufen?', 'woocommerce'),
        ));

        echo '
            </div>
            <div class="options_group">
                <p class="wc-gzd-product-settings-subtitle">' . __('Umgang Warenkorb', 'woocommerce') . '</p>
                <p>
                    <strong>' . __('Soll ein Produkt im Warenkorb angezeigt werden? Hinweis: Dadruch sind die vorherigen Einstellungen obsulet!', 'woocommerce') . '</strong>
                </p>';

        // Checkbox
        woocommerce_wp_checkbox(array(
            'id'      => '_veen_spa_option_3',
            'label'   => __('Warenkorb', 'woocommerce'),
            'description' => __('Produkt nur um Warenkorb sichtbar machen', 'woocommerce'),
        ));

        echo '</div></div>';
    }

    public function medi_save_product_data($post_id) {
        $veen_spa_option_1 = isset($_POST['_veen_spa_option_1']) ? sanitize_text_field($_POST['_veen_spa_option_1']) : '';
        update_post_meta($post_id, '_veen_spa_option_1', $veen_spa_option_1);

        $veen_spa_option_2 = isset($_POST['_veen_spa_option_2']) ? 1 : 0;
        update_post_meta($post_id, '_veen_spa_option_2', $veen_spa_option_2);

        $veen_spa_option_2 = isset($_POST['_veen_spa_option_3']) ? 1 : 0;
        update_post_meta($post_id, '_veen_spa_option_3', $veen_spa_option_2);
    }
}