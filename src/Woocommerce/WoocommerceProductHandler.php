<?php

namespace Wesanox\Booking\Woocommerce;

defined( 'ABSPATH' )|| exit;

use DateTime;
use DateTimeZone;
use Wesanox\Booking\Application\Booking\ValidateBookingExtrasService;
use Wesanox\Booking\Boot\Booking\HandlerBooking;
use Wesanox\Booking\Domain\Booking\CheckoutAvailabilityDecision;
use Wesanox\Booking\Domain\Booking\DuplicateGuestDetector;
use Wesanox\Booking\Domain\Booking\SpecialsCustomerNotice;
use Wesanox\Booking\Domain\Cart\BookingCartPolicy;
use Wesanox\Booking\Service\ServiceBookingTime;
use Wesanox\Booking\Service\ServiceGetAvailableRoomarts;
use Wesanox\Booking\Service\ServiceGetAvailableTimes;
use Wesanox\Booking\Repository\RepositoryBooking;
use Wesanox\Booking\Boot\Booking;

class WoocommerceProductHandler
{
    protected ServiceBookingTime $service_booking_time;
    protected ServiceGetAvailableRoomarts $service_get_available_roomarts;
    protected RepositoryBooking $repository_booking;
    protected HandlerBooking $handler_booking;
    private BookingCartPolicy $booking_cart_policy;
    /** @var int[]|null */
    private ?array $configured_rate_product_ids = null;
    private bool $checkout_checkboxes_rendered = false;
    private bool $newsletter_checkbox_rendered = false;
    private ?bool $order_submit_template_has_newsletter = null;

    public function __construct()
    {
        $this->service_booking_time = new ServiceBookingTime();
        $this->service_get_available_roomarts = new ServiceGetAvailableRoomarts();
        $this->repository_booking = new RepositoryBooking();
        $this->handler_booking = new HandlerBooking();
        $this->booking_cart_policy = new BookingCartPolicy();

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
        add_action('woocommerce_checkout_process', [$this, 'mail_woocommerce_checkout_process']);
        add_action('woocommerce_checkout_process', [$this, 'check_room_availability_before_order']);
        add_action('woocommerce_checkout_process', [$this, 'birthday_woocommerce_checkout_process']);
        add_action('woocommerce_product_data_panels', [$this, 'medi_product_data_fields']);
        add_action('woocommerce_process_product_meta', [$this, 'medi_save_product_data']);
        add_action('woocommerce_checkout_create_order', [$this, 'merge_specials_notice_into_order'], 10, 2);
        add_action('woocommerce_checkout_create_order', [$this, 'reserve_booking_before_order_creation'], 20, 2);
        add_action('woocommerce_checkout_order_processed', [$this, 'wesanox_after_order_processed'], 20, 3);
        add_action('init', [$this, 'move_checkboxes_woocommerce'], 50);
        add_action('init', [$this, 'remove_checkout_checkboxes_outside_submit_block'], 100);
        add_action('woocommerce_before_checkout_form', [$this, 'configure_checkout_review_hooks'], 1000);
        add_action('woocommerce_checkout_update_order_review', [$this, 'configure_checkout_review_hooks'], 1000);

        add_filter('gettext', [$this, 'medi_cancel_order_button_text'], 20, 3);
        add_filter('woocommerce_checkout_fields', [$this, 'add_custom_checkout_field'], 999);
        add_filter('woocommerce_checkout_fields' , [$this, 'default_address_fields_override']);
        add_filter('woocommerce_account_menu_items', [$this, 'hide_downloads_account_menu_item'], 10, 1);
        add_filter('woocommerce_valid_order_statuses_for_cancel', [$this, 'medi_cancellable_status'], 20, 2);
        add_filter('woocommerce_account_orders_columns', [$this, 'medi_account_orders_column_header']);
        add_filter('woocommerce_email_attachments', [$this, 'add_ics_attachment_to_email'], 10, 4);
        add_filter('woocommerce_product_data_tabs', [$this, 'medi_product_data_tab']);
        add_filter('woocommerce_form_field_heading', [$this, 'add_heading_to_additional_field'], 10, 4);

//        add_filter('woocommerce_cart_needs_shipping_address', function($needs){
//            return true; // erzwingt Anzeige des "An eine andere Adresse liefern?" Bereichs
//        }, 10, 1);
    }

    /**
     * @return void
     */
    public function wesanox_update_booking_session()
    {
        if ( empty($_POST['nonce']) || ! wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['nonce']) ), 'wesanox_booking_nonce' ) ) {
            wp_send_json_error('Ungültige Anfrage (Nonce).', 403);
            return;
        }

        if (!function_exists('WC') || !WC()->session) {
            wp_send_json_error('Keine Session gefunden');
        }

        $extras_json = isset($_POST['extras']) ? wp_unslash($_POST['extras']) : '[]';
        $extras = json_decode($extras_json, true);

        if (!is_array($extras)) {
            $extras = [];
        }

        $booking = (array) WC()->session->get('booking', []);
        $booking['extras'] = json_encode($extras);

        if (isset($_POST['specials_notice'])) {
            $booking['specials_notice'] = $this->sanitize_specials_notice(
                wp_unslash($_POST['specials_notice'])
            );
        }

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
        if ( empty($_POST['nonce']) || ! wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['nonce']) ), 'wesanox_booking_nonce' ) ) {
            wp_send_json_error(['msg' => 'Ungültige Anfrage (Nonce).'], 403);
            return;
        }

        if ( ! isset($_POST['payload']) ) {
            wp_send_json_error(['msg' => 'payload missing'], 400);
        }
        $raw  = wp_unslash($_POST['payload']);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            wp_send_json_error(['msg' => 'invalid json'], 400);
        }

        $how_long = isset($data['how_long']) ? (float) $data['how_long'] : null;
        $duration_minutes = isset($data['duration_minutes']) ? absint($data['duration_minutes']) : 0;

        if ($duration_minutes <= 0 && $how_long !== null && $how_long > 0) {
            $duration_minutes = (int) round($how_long * 60);
        }

        $start_time = !empty($data['start_time']) ? sanitize_text_field($data['start_time']) : null;
        $stop_time  = !empty($data['stop_time'])  ? sanitize_text_field($data['stop_time'])  : null;

        if ($start_time && $duration_minutes > 0) {
            $calculated_stop_time = $this->calculateStopTimeFromDuration($start_time, $duration_minutes);
            if ($calculated_stop_time !== '') {
                $stop_time = $calculated_stop_time;
                $how_long = $duration_minutes / 60;
            }
        }

        $booking = [
            'person_count'      => isset($data['person_count']) ? (int)$data['person_count'] : null,
            'day'               => !empty($data['day'])         ? sanitize_text_field($data['day']) : null,
            'start_time'        => $start_time,
            'stop_time'         => $stop_time,
            'how_long'          => $how_long,
            'duration_minutes'  => $duration_minutes > 0 ? $duration_minutes : null,
            'product_id'        => !empty($data['product_id'])  ? sanitize_text_field($data['product_id']) : null,
            'area_id'           => isset($data['area_id'])      ? absint($data['area_id']) : 0,
            'extras'            => (isset($data['extras'])) ? $data['extras'] : [],
            'specials_notice'   => $this->sanitize_specials_notice($data['specials_notice'] ?? ''),
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
        // CSRF protection — nonce created in FrontendService::localizeScripts()
        if ( empty($_POST['nonce']) || ! wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['nonce']) ), 'wesanox_booking_nonce' ) ) {
            wp_send_json_error(['message' => 'Ungültige Anfrage (Nonce).'], 403);
            return;
        }

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
        $duration = isset($_POST['how_long']) ? (float) $_POST['how_long'] : (float) ($booking_session['how_long'] ?? 0);
        $duration_minutes = isset($_POST['duration_minutes'])
            ? absint($_POST['duration_minutes'])
            : absint($booking_session['duration_minutes'] ?? 0);
        $area_id = isset($_POST['area_id']) ? absint($_POST['area_id']) : (int) ($booking_session['area_id'] ?? 0);

        if ($duration_minutes <= 0 && $duration > 0) {
            $duration_minutes = (int) round($duration * 60);
        }

        if ($start_time !== '' && $duration_minutes > 0) {
            $calculated_stop_time = $this->calculateStopTimeFromDuration($start_time, $duration_minutes);
            if ($calculated_stop_time !== '') {
                $stop_time = $calculated_stop_time;
                $duration = $duration_minutes / 60;
            }
        }

        if ( $product_ids_raw === '' ) {
            wp_send_json_error(['message' => 'product_id fehlt.'], 400);
        }

        $product_ids = array_values( array_unique( array_filter( array_map('intval', explode(',', $product_ids_raw)) ) ) );

        if ( empty($product_ids) ) {
            wp_send_json_error(['message' => 'Keine gültigen Produkt-IDs.'], 400);
        }

        $configured_rate_ids = $this->configured_rate_product_ids();
        $invalid_product_ids = array_diff($product_ids, $configured_rate_ids);

        if ($configured_rate_ids === [] || $invalid_product_ids !== []) {
            wp_send_json_error(['message' => 'Der gewählte Tarif ist nicht gültig oder nicht aktiv.'], 400);
        }

        $pending_booking = [
            'person_count' => $person,
            'day' => $day,
            'start_time' => $start_time,
            'stop_time' => $stop_time,
        ];

        if (!$this->has_complete_booking_data($pending_booking)) {
            wp_send_json_error(['message' => 'Die Buchungsdaten sind unvollständig. Bitte wähle Datum, Uhrzeit und Personenzahl erneut.'], 400);
        }

        if ($this->cart_contains_configured_gift_product()) {
            wp_send_json_error([
                'message' => 'Ein Gutscheinkauf kann nicht mit einer Buchung kombiniert werden. Bitte schließe den Gutscheinkauf ab oder leere den Warenkorb.',
            ], 409);
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
        $single_product_booking = count($product_ids) === 1;

        foreach ($product_ids as $pid) {
            if ( $single_product_booking ) {
                $quantity = $person * $hours_all;
            } elseif ( in_array($pid, [251, 254], true) ) {
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
             'person_count'     => $person,
             'how_long'         => $duration,
             'duration_minutes' => $duration_minutes > 0 ? $duration_minutes : null,
             'day'              => $day,
             'start_time'       => $start_time,
             'stop_time'        => $stop_time,
             'product_id'       => implode(',', $product_ids),
             'area_id'          => $area_id,
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

        if ( ! function_exists('WC') || ! WC()->cart ) {
            wp_send_json_error('Warenkorb nicht verfügbar.');
            wp_die();
        }

        if ( WC()->cart->get_cart_contents_count() === 0 ) {
            wp_send_json_error('Warenkorb ist leer.');
            wp_die();
        }

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

        if ( ! $product_id ) {
            wp_send_json_error('Ungültige Produkt-ID.');
            wp_die();
        }

        $removed = false;

        foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
            if (
                (int) $item['product_id'] === $product_id
            ) {
                WC()->cart->remove_cart_item( $cart_item_key );
                $removed = true;
            }
        }

        if ( $removed ) {
            wp_send_json_success('Alle passenden Produkte entfernt.');
        } else {
            wp_send_json_error('Produkt nicht im Warenkorb gefunden.');
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
        // CSRF protection
        if ( empty($_POST['nonce']) || ! wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['nonce']) ), 'wesanox_booking_nonce' ) ) {
            wp_send_json_error(['message' => 'Ungültige Anfrage (Nonce).'], 403);
            return;
        }

        if ( ! isset($_POST['product_id']) ) {
            wp_send_json_error('product_id fehlt!', 400);
        }

        if ( ! function_exists('WC') || ! WC()->cart || ! WC()->session ) {
            wp_send_json_error('Warenkorb/Session nicht verfügbar.', 500);
        }

        $product_id = absint($_POST['product_id']);
        $quantity   = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1;

        if (!in_array($product_id, ValidateBookingExtrasService::allowedProductIds(), true)) {
            wp_send_json_error(['message' => 'Das gewählte Extra ist für Buchungen nicht zulässig.'], 400);
        }

        $session = WC()->session;
        $existing = (array) $session->get('booking', []);

        if (!$this->booking_cart_policy->canAddExtra(
            $this->cart_contains_booking_rate(),
            $this->has_complete_booking_data($existing),
        )) {
            wp_send_json_error([
                'message' => 'Bitte wähle zuerst einen gültigen Tarif, bevor du Extras hinzufügst.',
            ], 409);
        }

        if(!empty($_POST['variation_ids'])) {
            foreach ($_POST['variation_ids'] as $variation_id) {
                $cart_item_key = WC()->cart->add_to_cart($product_id, 1, $variation_id);

                if ( ! $cart_item_key ) {
                    wp_send_json_error('Fehler beim Hinzufügen der Variation zum Warenkorb!', 500);
                }
            }
        } else {
            $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity);

            if ( ! $cart_item_key ) {
                wp_send_json_error('Fehler beim Hinzufügen der Variation zum Warenkorb!', 500);
            }
        }

        // Werte aus POST übernehmen; fallback auf vorhandene Session-Werte
        $person_count = isset($_POST['person_count']) ? (int) $_POST['person_count'] : (int) ($existing['person_count'] ?? 0);
        $day          = isset($_POST['day'])         ? sanitize_text_field( wp_unslash($_POST['day']) )         : (string) ($existing['day'] ?? '');
        $start_time   = isset($_POST['start_time'])  ? sanitize_text_field( wp_unslash($_POST['start_time']) )  : (string) ($existing['start_time'] ?? '');
        $stop_time    = isset($_POST['stop_time'])   ? sanitize_text_field( wp_unslash($_POST['stop_time']) )   : (string) ($existing['stop_time'] ?? '');
        $how_long     = isset($_POST['how_long'])    ? (float) $_POST['how_long']                               : (float) ($existing['how_long'] ?? 0);
        $duration_minutes = isset($_POST['duration_minutes'])
            ? absint($_POST['duration_minutes'])
            : absint($existing['duration_minutes'] ?? 0);
        $area_id      = isset($_POST['area_id'])     ? absint($_POST['area_id'])                                : (int) ($existing['area_id'] ?? 0);

        if ($duration_minutes <= 0 && $how_long > 0) {
            $duration_minutes = (int) round($how_long * 60);
        }

        if ($start_time !== '' && $duration_minutes > 0) {
            $calculated_stop_time = $this->calculateStopTimeFromDuration($start_time, $duration_minutes);
            if ($calculated_stop_time !== '') {
                $stop_time = $calculated_stop_time;
                $how_long = $duration_minutes / 60;
            }
        }

        $booking = [
            'person_count'     => $person_count ?: null,
            'day'              => $day         ?: null,
            'start_time'       => $start_time  ?: null,
            'stop_time'        => $stop_time   ?: null,
            'how_long'         => $how_long    ?: null,
            'duration_minutes' => $duration_minutes > 0 ? $duration_minutes : null,
            'area_id'          => $area_id,
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
        $person = (int) ($booking['person_count'] ?? 0);
        $start = trim(($booking['day'] ?? '') . ' ' . ($booking['start_time'] ?? ''));
        $stop = trim(($booking['day'] ?? '') . ' ' . ($booking['stop_time'] ?? ''));

        $fields['billing']['billing_email']['required'] = true;

        $fields['billing']['billing_email_confirm'] = [
            'type'        => 'email',
            'label'       => __('E-Mail-Adresse bestätigen', 'your-textdomain'),
            'required'    => true,
            'class'       => ['form-row-wide'],
            'priority'    => 115,
            'clear'       => true,
            'autocomplete'=> 'email',
        ];

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

    public function merge_specials_notice_into_order( $order, $data ): void
    {
        if ( ! $order instanceof \WC_Order || ! $this->cart_contains_booking_extra() ) {
            return;
        }

        $booking = $this->current_booking_session();
        $specials_notice = $this->sanitize_specials_notice($booking['specials_notice'] ?? '');

        if ($specials_notice === '') {
            return;
        }

        $order->update_meta_data(SpecialsCustomerNotice::ORDER_META_KEY, $specials_notice);
        $order->set_customer_note(SpecialsCustomerNotice::merge(
            $order->get_customer_note(),
            $specials_notice,
        ));
    }

    public function reserve_booking_before_order_creation( $order, $data ): void
    {
        if ( ! $order instanceof \WC_Order ) {
            return;
        }

        $has_rate = $this->cart_contains_booking_rate();
        $has_extras = $this->cart_contains_booking_extra();

        if (!$has_rate && !$has_extras) {
            return;
        }

        $booking = $this->current_booking_session();
        $day        = $booking['day']        ?? null;
        $start_time = $booking['start_time'] ?? null;
        $stop_time  = $booking['stop_time']  ?? null;

        $roomart_id = $this->resolve_roomart_id_from_cart();
        $violation = $this->booking_cart_policy->violation(
            $has_rate,
            $has_extras,
            $this->has_complete_booking_data($booking),
            $roomart_id !== null,
        );

        if ($violation !== null) {
            $this->abort_checkout($this->booking_cart_violation_message($violation));
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $day)) {
            $this->abort_checkout(__('Die Buchungsdaten sind ungültig - bitte erstelle eine neue Buchung.', 'wesanox-booking'));
        }

        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string) $start_time) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string) $stop_time)) {
            $this->abort_checkout(__('Die Buchungszeit ist ungültig - bitte erstelle eine neue Buchung.', 'wesanox-booking'));
        }

        global $wpdb;

        $start_time = $this->normalizeAvailabilityTime((string) $start_time);
        $stop_time  = $this->normalizeAvailabilityTime((string) $stop_time);
        $token      = $this->current_checkout_hold_token();

        if ($token === '') {
            $token = $this->generate_checkout_hold_token();
        }

        $this->repository_booking->ensure_checkout_hold_schema();
        $this->repository_booking->cleanup_expired_checkout_holds();

        $wpdb->query('START TRANSACTION');

        try {
            $existing_hold = $this->repository_booking->find_active_checkout_hold_by_token($token, true);

            if ($existing_hold !== null && $this->checkout_hold_matches($existing_hold, (string) $day, $start_time, $stop_time)) {
                $wpdb->query('COMMIT');
                $this->remember_checkout_hold($order, (int) $existing_hold['id'], (int) $existing_hold['room_id'], (string) $day, $start_time, $stop_time, $token);
                return;
            }

            if ($existing_hold !== null) {
                $this->repository_booking->release_checkout_hold($token);
            }

            $room_id = $this->service_get_available_roomarts->wesanox_find_room_locked(
                $roomart_id,
                (string) $day,
                $start_time,
                $stop_time,
                30
            );

            if (!$room_id) {
                throw new \RuntimeException(__('Der gewünschte Zeitraum ist nicht mehr verfügbar. Bitte wähle einen anderen Zeitraum.', 'wesanox-booking'));
            }

            $booking_id = $this->repository_booking->insert_checkout_hold(
                (string) $day,
                $start_time,
                $stop_time,
                (int) $room_id,
                $this->resolve_valid_wc_customer_id($order),
                $token,
                $this->checkout_hold_expires_at()
            );

            if (!$booking_id) {
                throw new \RuntimeException(__('Die Buchung konnte nicht reserviert werden. Bitte versuche es erneut.', 'wesanox-booking'));
            }

            $wpdb->query('COMMIT');

            $this->remember_checkout_hold($order, $booking_id, (int) $room_id, (string) $day, $start_time, $stop_time, $token);
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');

            if (!$e instanceof \RuntimeException) {
                error_log('wesanox_booking_checkout_hold: ' . $e->getMessage());
            }

            $message = $e->getMessage() !== ''
                ? $e->getMessage()
                : __('Die Buchung konnte nicht reserviert werden. Bitte versuche es erneut.', 'wesanox-booking');

            $this->abort_checkout($message);
        }
    }

    public function wesanox_after_order_processed( $order_id, $posted, $order ) {
        if ( ! $order instanceof \WC_Order ) {
            $order = wc_get_order( (int) $order_id );
            if ( ! $order ) {
                error_log('wesanox_after_order_processed: order not found for id ' . $order_id);
                return;
            }
        }

        global $wpdb;

        $this->repository_booking->ensure_checkout_hold_schema();
        $this->repository_booking->cleanup_expired_checkout_holds();

        $existing_booking = $this->find_existing_booking_for_order( $order );
        if ( $existing_booking !== null ) {
            $this->complete_existing_checkout_booking( $order, $existing_booking );
            return;
        }

        $booking = $this->current_booking_session();
        if (empty($booking)) return;

        $day        = $booking['day']        ?? null;
        $start_time = $booking['start_time'] ?? null;
        $stop_time  = $booking['stop_time']  ?? null;

        if (!$day || !$start_time || !$stop_time) return;

        // Validate formats to prevent malformed data reaching the DB.
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) return;
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start_time)) return;
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $stop_time)) return;

        $start_time = $this->normalizeAvailabilityTime((string) $start_time);
        $stop_time  = $this->normalizeAvailabilityTime((string) $stop_time);
        $roomart_id = $this->resolve_roomart_id_from_cart();

        if ($roomart_id === null) return;

        $customer_id = $this->resolve_valid_wc_customer_id($order);

        // ── Atomic: lock rooms row → re-check availability → insert ──────────
        // Using FOR UPDATE inside a transaction prevents two concurrent checkouts
        // from claiming the same room (race condition fix).
        $wpdb->query('START TRANSACTION');

        $existing_booking = $this->find_existing_booking_for_order( $order, true );
        if ( $existing_booking !== null ) {
            $wpdb->query('COMMIT');
            $this->complete_existing_checkout_booking( $order, $existing_booking );
            return;
        }

        $room_id = $this->service_get_available_roomarts->wesanox_find_room_locked(
            $roomart_id,
            $day,
            $start_time,
            $stop_time,
            30
        );

        if (!$room_id) {
            $existing_booking = $this->find_existing_booking_for_order( $order, true );
            if ( $existing_booking !== null ) {
                $wpdb->query('COMMIT');
                $this->complete_existing_checkout_booking( $order, $existing_booking );
                return;
            }

            $wpdb->query('ROLLBACK');
            $order->update_status(
                'failed',
                __('Der gewünschte Zeitraum ist nicht mehr verfügbar.', 'wesanox-booking')
            );
            error_log('wesanox_after_order_processed: no room available for order ' . $order_id . ' — possible race condition or slot taken');
            WC()->session->__unset('booking');
            WC()->session->__unset('booking_free_room_id');
            return;
        }

        $booking_id = $this->repository_booking->insert_booking(
            $day,
            $start_time,
            $stop_time,
            (int) $room_id,
            (int) $order->get_id(),
            $customer_id
        );

        if (!$booking_id) {
            $wpdb->query('ROLLBACK');
            error_log('wesanox_after_order_processed: insert_booking failed for order ' . $order_id);
            return;
        }

        $wpdb->query('COMMIT');
        // ── end atomic section ───────────────────────────────────────────────

        $order->update_meta_data('_booking_id',      $booking_id);
        $order->update_meta_data('_booking_room_id', (int) $room_id);
        $order->update_meta_data('_booking_date',    $day);
        $order->update_meta_data('_booking_from',    $start_time);
        $order->update_meta_data('_booking_to',      $stop_time);
        $order->save();

        $this->clear_booking_checkout_state();
    }

    /**
     * @return array<string,mixed>|null
     */
    private function find_existing_booking_for_order( \WC_Order $order, bool $for_update = false ): ?array
    {
        $booking_id = (int) $order->get_meta( '_booking_id', true );

        if ( $booking_id > 0 ) {
            $booking = $this->repository_booking->find_by_id( $booking_id, $for_update );
            if ( $booking !== null ) {
                return $booking;
            }
        }

        return $this->repository_booking->find_by_order_id( (int) $order->get_id(), $for_update );
    }

    /**
     * @param array<string,mixed> $booking
     */
    private function complete_existing_checkout_booking( \WC_Order $order, array $booking ): void
    {
        $booking_id = (int) ( $booking['id'] ?? 0 );

        if ( $booking_id <= 0 ) {
            return;
        }

        if (!$this->repository_booking->attach_order_to_booking($booking_id, (int) $order->get_id(), $this->resolve_valid_wc_customer_id($order))) {
            error_log('wesanox_after_order_processed: booking could not be linked to order ' . $order->get_id() . ' for booking ' . $booking_id);
        }

        $order->update_meta_data( '_booking_id', $booking_id );
        $order->delete_meta_data( '_booking_hold_token' );

        if ( isset( $booking['room_id'] ) && (int) $booking['room_id'] > 0 ) {
            $order->update_meta_data( '_booking_room_id', (int) $booking['room_id'] );
        }

        if ( ! empty( $booking['booking_date'] ) ) {
            $order->update_meta_data( '_booking_date', (string) $booking['booking_date'] );
        }

        if ( ! empty( $booking['booking_from'] ) ) {
            $order->update_meta_data( '_booking_from', (string) $booking['booking_from'] );
        }

        if ( ! empty( $booking['booking_to'] ) ) {
            $order->update_meta_data( '_booking_to', (string) $booking['booking_to'] );
        }

        $order->save();
        $this->clear_booking_checkout_state();
    }

    private function clear_booking_checkout_state(): void
    {
        if ( ! function_exists( 'WC' ) ) {
            return;
        }

        $woocommerce = WC();

        if ( ! is_object( $woocommerce ) ) {
            return;
        }

        if ( ! empty( $woocommerce->session ) ) {
            $woocommerce->session->__unset( 'booking' );
            $woocommerce->session->__unset( 'booking_free_room_id' );
            $woocommerce->session->__unset( 'booking_hold_id' );
            $woocommerce->session->__unset( 'booking_hold_token' );
        }

        if ( ! empty( $woocommerce->cart ) ) {
            $woocommerce->cart->empty_cart();
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function current_booking_session(): array
    {
        if (!function_exists('WC') || !WC()->session) {
            return [];
        }

        $booking = WC()->session->get('booking', []);

        return is_array($booking) ? $booking : [];
    }

    private function current_checkout_hold_token(): string
    {
        if (!function_exists('WC') || !WC()->session) {
            return '';
        }

        return (string) WC()->session->get('booking_hold_token', '');
    }

    private function remember_checkout_hold(
        \WC_Order $order,
        int $booking_id,
        int $room_id,
        string $day,
        string $start_time,
        string $stop_time,
        string $token
    ): void {
        $order->update_meta_data('_booking_id', $booking_id);
        $order->update_meta_data('_booking_hold_token', $token);
        $order->update_meta_data('_booking_room_id', $room_id);
        $order->update_meta_data('_booking_date', $day);
        $order->update_meta_data('_booking_from', $start_time);
        $order->update_meta_data('_booking_to', $stop_time);

        if (function_exists('WC') && WC()->session) {
            WC()->session->set('booking_hold_id', $booking_id);
            WC()->session->set('booking_hold_token', $token);
        }
    }

    /**
     * @param array<string,mixed> $hold
     */
    private function checkout_hold_matches(array $hold, string $day, string $start_time, string $stop_time): bool
    {
        return (string) ($hold['booking_date'] ?? '') === $day
            && $this->normalizeAvailabilityTime((string) ($hold['booking_from'] ?? '')) === $start_time
            && $this->normalizeAvailabilityTime((string) ($hold['booking_to'] ?? '')) === $stop_time;
    }

    private function generate_checkout_hold_token(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable) {
            return str_replace('.', '', uniqid('hold_', true));
        }
    }

    private function checkout_hold_expires_at(): string
    {
        $minutes = $this->checkout_hold_minutes();
        $timezone = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone(date_default_timezone_get());

        return (new \DateTimeImmutable('now', $timezone))
            ->modify('+' . $minutes . ' minutes')
            ->format('Y-m-d H:i:s');
    }

    private function checkout_hold_minutes(): int
    {
        $minutes = 15;

        if (function_exists('apply_filters')) {
            $minutes = (int) apply_filters('wesanox_booking_checkout_hold_minutes', $minutes);
        }

        return max(1, $minutes);
    }

    private function resolve_roomart_id_from_cart(): ?int
    {
        if (!function_exists('WC') || !WC()->cart) {
            return null;
        }

        $configured_rate_ids = $this->configured_rate_product_ids();

        foreach (WC()->cart->get_cart() as $item) {
            $product_id = (int) ($item['product_id'] ?? 0);
            $variation_id = (int) ($item['variation_id'] ?? 0);

            if (!in_array($product_id, $configured_rate_ids, true)
                && !in_array($variation_id, $configured_rate_ids, true)) {
                continue;
            }

            $roomart_id = $this->resolve_roomart_id_for_rate_product($product_id);

            if ($roomart_id !== null) {
                return $roomart_id;
            }
        }

        return null;
    }

    private function resolve_roomart_id_for_rate_product(int $product_id): ?int
    {
        global $wpdb;

        if ($product_id <= 0) {
            return null;
        }

        $parent_id = function_exists('wp_get_post_parent_id') ? (int) wp_get_post_parent_id($product_id) : 0;
        $product_id = $parent_id > 0 ? $parent_id : $product_id;

        $roomart_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT roomart.id
             FROM {$wpdb->prefix}wesanox_roomarts roomart
             INNER JOIN {$wpdb->posts} product
                ON LOWER(TRIM(product.post_title)) = LOWER(TRIM(roomart.roomart_name))
             WHERE product.ID = %d
             LIMIT 1",
            $product_id
        ));

        if ($roomart_id > 0) {
            return $roomart_id;
        }

        $legacy_map = [75 => 1, 91 => 2];

        if (function_exists('apply_filters')) {
            $legacy_map = (array) apply_filters('wesanox_booking_roomart_product_map', $legacy_map);
        }

        $roomart_id = (int) ($legacy_map[$product_id] ?? 0);

        return $roomart_id > 0 ? $roomart_id : null;
    }

    private function resolve_valid_wc_customer_id(\WC_Order $order): ?int
    {
        global $wpdb;

        $customer_id = (int) $order->get_customer_id();

        if ($customer_id <= 0) {
            return null;
        }

        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM {$wpdb->prefix}wc_customer_lookup WHERE customer_id = %d",
            $customer_id
        ));

        return $exists > 0 ? $customer_id : null;
    }

    private function abort_checkout(string $message): void
    {
        throw new \Exception($message);
    }


    public function check_room_availability_before_order()
    {
        $booking = $this->current_booking_session();

        $has_rate = $this->cart_contains_booking_rate();
        $has_extras = $this->cart_contains_booking_extra();

        if (!$has_rate && !$has_extras) {
            return;
        }

        $day        = (string) ($booking['day'] ?? '');
        $start_time = (string) ($booking['start_time'] ?? '');
        $stop_time  = (string) ($booking['stop_time'] ?? '');
        $area_id    = (int) ($booking['area_id'] ?? 0);
        $roomart_id = $this->resolve_roomart_id_from_cart();

        $violation = $this->booking_cart_policy->violation(
            $has_rate,
            $has_extras,
            $this->has_complete_booking_data($booking),
            $roomart_id !== null,
        );

        if ($violation !== null) {
            $this->add_checkout_error($this->booking_cart_violation_message($violation));
            return;
        }

        /**
         * @TODO api_call_requests should be private!
         */
        $local_start_time = $this->normalizeAvailabilityTime($start_time);
        $local_stop_time  = $this->normalizeAvailabilityTime($stop_time);
        $free_room_id = $this->service_get_available_roomarts->wesanox_find_room($roomart_id, $day, $local_start_time, $local_stop_time, 30);
        $availability_query = $this->buildAvailabilityQuery($roomart_id, $day, $start_time, $stop_time, $area_id);
        $free_room_id_api = null;

        if ($availability_query !== null) {
            $free_room_id_api = $this->handler_booking->api_call_requests(
                'bookings/get-available-roomart?' . http_build_query($availability_query)
            );
        } else {
            error_log('wesanox-booking: Externe Verfügbarkeitsprüfung übersprungen, da Buchungszeit ungültig ist.');
        }

        $violation = CheckoutAvailabilityDecision::violation(
            $free_room_id_api,
            $this->local_opening_window($day, $area_id),
            $local_start_time,
            $local_stop_time,
        );

        if ($violation === CheckoutAvailabilityDecision::OUTSIDE_OPENING_HOURS) {
            $this->add_checkout_error(__('Zu dieser Uhrzeit ist der Spa-Bereich geschlossen. Bitte wähle eine andere Uhrzeit.', 'wesanox-booking'));
            return;
        }

        if ($violation === CheckoutAvailabilityDecision::UNAVAILABLE) {
            $this->add_checkout_error(__('Der gewünschte Zeitraum ist nicht mehr verfügbar. Bitte wähle einen anderen Zeitraum.', 'wesanox-booking'));
            return;
        }

        if (!$free_room_id && !$free_room_id_api) {
            $this->add_checkout_error(__('Der gewünschte Zeitraum ist nicht mehr verfügbar. Bitte wähle einen anderen Zeitraum.', 'wesanox-booking'));
            return;
        }

        if (!$free_room_id) {
            $this->add_checkout_error(__('Der gewünschte Zeitraum ist nicht mehr verfügbar. Bitte wähle einen anderen Zeitraum.', 'wesanox-booking'));
            return;
        }

        WC()->session->set('booking_free_room_id', (int) $free_room_id);
    }

    /**
     * Local fallback when the backend cannot be reached: the synchronised opening window of the day.
     *
     * @return array<string, mixed>|null
     */
    private function local_opening_window(string $day, int $area_id): ?array
    {
        if ($day === '') {
            return null;
        }

        return ServiceGetAvailableTimes::get_opening_window($day, $area_id > 0 ? $area_id : null);
    }

    /** @return int[] */
    private function configured_rate_product_ids(): array
    {
        if ($this->configured_rate_product_ids !== null) {
            return $this->configured_rate_product_ids;
        }

        global $wpdb;

        $table = $wpdb->prefix . 'wesanox_rates';
        $rows = $wpdb->get_results(
            "SELECT woocommerce_product_id, woocommerce_variation_id
             FROM `{$table}`
             WHERE is_active = 1 AND deleted_at IS NULL",
            ARRAY_A
        );

        $ids = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            $ids[] = (int) ($row['woocommerce_product_id'] ?? 0);
            $ids[] = (int) ($row['woocommerce_variation_id'] ?? 0);
        }

        $ids = array_values(array_unique(array_filter($ids)));

        if (function_exists('apply_filters')) {
            $ids = array_map('intval', (array) apply_filters('wesanox_booking_rate_product_ids', $ids));
        }

        $this->configured_rate_product_ids = array_values(array_unique(array_filter($ids)));

        return $this->configured_rate_product_ids;
    }

    private function cart_contains_booking_rate(): bool
    {
        if (!function_exists('WC') || !WC()->cart) {
            return false;
        }

        $rate_ids = $this->configured_rate_product_ids();

        foreach (WC()->cart->get_cart() as $cart_item) {
            if (in_array((int) ($cart_item['product_id'] ?? 0), $rate_ids, true)
                || in_array((int) ($cart_item['variation_id'] ?? 0), $rate_ids, true)) {
                return true;
            }
        }

        return false;
    }

    private function cart_contains_booking_extra(): bool
    {
        if (!function_exists('WC') || !WC()->cart) {
            return false;
        }

        $extra_ids = ValidateBookingExtrasService::allowedProductIds();

        foreach (WC()->cart->get_cart() as $cart_item) {
            if (in_array((int) ($cart_item['product_id'] ?? 0), $extra_ids, true)) {
                return true;
            }
        }

        return false;
    }

    private function cart_contains_configured_gift_product(): bool
    {
        $gift_product_id = function_exists('get_option')
            ? (int) get_option('wesan_coupon_gift_product_id', 0)
            : 0;

        if ($gift_product_id <= 0 || !function_exists('WC') || !WC()->cart) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $cart_item) {
            if ((int) ($cart_item['product_id'] ?? 0) === $gift_product_id
                || (int) ($cart_item['variation_id'] ?? 0) === $gift_product_id) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $booking */
    private function has_complete_booking_data(array $booking): bool
    {
        $day = (string) ($booking['day'] ?? '');
        $start_time = (string) ($booking['start_time'] ?? '');
        $stop_time = (string) ($booking['stop_time'] ?? '');
        $person_count = (int) ($booking['person_count'] ?? 0);

        return $person_count > 0
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1
            && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start_time) === 1
            && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $stop_time) === 1;
    }

    private function sanitize_specials_notice(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return trim(sanitize_textarea_field((string) $value));
    }

    private function booking_cart_violation_message(string $violation): string
    {
        return match ($violation) {
            BookingCartPolicy::MISSING_RATE => __('Zu den gewählten Extras fehlt ein Buchungstarif. Bitte wähle zuerst eine Suite und einen Tarif.', 'wesanox-booking'),
            BookingCartPolicy::MISSING_ROOMART => __('Der gewählte Tarif konnte keiner Raumart zugeordnet werden. Bitte wähle den Tarif erneut.', 'wesanox-booking'),
            default => __('Die Buchungsdaten sind unvollständig. Bitte erstelle die Buchung erneut.', 'wesanox-booking'),
        };
    }

    private function add_checkout_error(string $message): void
    {
        if (!function_exists('wc_add_notice')) {
            return;
        }

        if (!function_exists('wc_has_notice') || !wc_has_notice($message, 'error')) {
            wc_add_notice($message, 'error');
        }
    }

    /**
     * Build the query expected by the external Laravel availability endpoint.
     *
     * @return array<string, int|string>|null
     */
    private function buildAvailabilityQuery(int $roomart_id, string $day, string $start_time, string $stop_time, int $area_id): ?array
    {
        $from = $this->createAvailabilityDateTime($day, $start_time);
        $to   = $this->createAvailabilityDateTime($day, $stop_time);

        if (!$from || !$to) {
            return null;
        }

        if ($to <= $from) {
            $to = $to->modify('+1 day');
        }

        $query = [
            'item_category_id' => $roomart_id,
            'from'             => $from->format('Y-m-d H:i:s'),
            'to'               => $to->format('Y-m-d H:i:s'),
        ];

        if ($area_id > 0) {
            $query['area_id'] = $area_id;
        }

        return $query;
    }

    private function createAvailabilityDateTime(string $day, string $time): ?\DateTimeImmutable
    {
        $day  = trim($day);
        $time = $this->normalizeAvailabilityTime($time);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            return null;
        }

        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($day . ' ' . $time, wp_timezone());
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeAvailabilityTime(string $time): string
    {
        $time = trim($time);

        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time . ':00';
        }

        return $time;
    }

    /**
     * Update the order meta with field value
     *
     * @param $order_id
     * @return void
     */
    public function checkout_order_meta_update( $order_id ) {
        $booking = ( function_exists('WC') && WC()->session ) ? (array) WC()->session->get('booking', []) : [];

        $person = isset($_POST['person']) ? absint($_POST['person']) : 0;

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

        if ( isset($booking['extras'])  ) {
            $order = wc_get_order( $order_id );
            $order->update_meta_data( 'Extra', sanitize_text_field( $booking['extras'] ) );
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

        $person = (int) ($booking['person_count'] ?? 0);

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

        $this->validate_unique_guests( $person );
    }

    /**
     * Each guest must be entered only once, otherwise the backend merges them
     * and the booking ends up with fewer guests than booked (AEA-68).
     */
    private function validate_unique_guests( int $person ): void
    {
        $posted = static fn ( string $key ): string => isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';

        $guests = [
            1 => [
                'firstname' => $posted( 'billing_first_name' ),
                'lastname'  => $posted( 'billing_last_name' ),
                'email'     => $posted( 'billing_email' ),
            ],
        ];

        for ( $i = 2; $i <= $person; $i++ ) {
            $guests[ $i ] = [
                'firstname' => $posted( "additional_person_{$i}_firstname" ),
                'lastname'  => $posted( "additional_person_{$i}_lastname" ),
                'email'     => $posted( "additional_person_{$i}_email" ),
            ];
        }

        foreach ( ( new DuplicateGuestDetector() )->duplicates( $guests ) as [ $first, $second ] ) {
            $first_label = $first === 1 ? __( 'Rechnungsadresse', 'wesanox-booking' ) : sprintf( __( 'Besucher %d', 'wesanox-booking' ), $first );

            wc_add_notice(
                sprintf(
                    /* translators: 1: first guest label, 2: second guest number */
                    __( '%1$s und Besucher %2$d haben denselben Namen. Bitte gib für jede Person ihren eigenen Namen an.', 'wesanox-booking' ),
                    $first_label,
                    $second
                ),
                'error'
            );
        }
    }

    /**
     * Keep payment methods before the booking summary and legal checkboxes
     * directly before the submit button, even when Germanized disables its
     * checkout adjustments for compatibility reasons.
     */
    public function configure_checkout_review_hooks(...$args): void
    {
        $this->move_payment_methods_before_order_review();
        $this->move_order_submit_after_booking_summary();
        $this->configure_order_button_visibility();
        $this->remove_checkout_checkboxes_outside_submit_block();
        $this->move_checkboxes_woocommerce();
    }

    private function move_payment_methods_before_order_review(): void
    {
        $this->move_checkout_order_review_callback('woocommerce_checkout_payment', 10);
        $this->move_checkout_order_review_callback('woocommerce_order_review', 20);
    }

    private function move_checkout_order_review_callback(string $callback, int $priority): void
    {
        $current_priority = has_action('woocommerce_checkout_order_review', $callback);

        while ($current_priority !== false) {
            remove_action('woocommerce_checkout_order_review', $callback, $current_priority);
            $current_priority = has_action('woocommerce_checkout_order_review', $callback);
        }

        add_action('woocommerce_checkout_order_review', $callback, $priority);
    }

    private function move_order_submit_after_booking_summary(): void
    {
        if (!function_exists('woocommerce_gzd_template_order_submit')) {
            return;
        }

        $this->move_checkout_order_review_callback('woocommerce_gzd_template_order_submit', 30);
    }

    private function configure_order_button_visibility(): void
    {
        if (!function_exists('woocommerce_gzd_template_order_submit')
            || !function_exists('woocommerce_gzd_template_set_order_button_show_filter')
        ) {
            return;
        }

        if (function_exists('woocommerce_gzd_template_set_order_button_remove_filter')) {
            remove_action('woocommerce_review_order_before_submit', 'woocommerce_gzd_template_set_order_button_remove_filter', 1500);
        }

        remove_action('woocommerce_gzd_review_order_before_submit', 'woocommerce_gzd_template_set_order_button_show_filter', 1500);
        add_action('woocommerce_gzd_review_order_before_submit', 'woocommerce_gzd_template_set_order_button_show_filter', 1500);
    }

    /**
     * Move the Germanized checkout checkboxes to the final submit block.
     *
     * @return void
     */
    public function move_checkboxes_woocommerce(): void {
        $this->remove_checkout_checkbox_callback('woocommerce_review_order_after_payment');
        $this->remove_checkout_checkbox_callback('woocommerce_review_order_before_payment');
        $this->remove_checkout_checkbox_callback('woocommerce_checkout_order_review');
        $this->remove_checkout_checkbox_callback('woocommerce_gzd_review_order_before_submit');
        $this->remove_checkout_checkbox_callback('woocommerce_review_order_before_submit');

        remove_action('woocommerce_review_order_before_submit', [$this, 'render_checkout_checkboxes'], 10);
        remove_action('woocommerce_gzd_review_order_before_submit', [$this, 'render_checkout_checkboxes'], 10);

        $target_hook = function_exists('woocommerce_gzd_template_order_submit')
            ? 'woocommerce_gzd_review_order_before_submit'
            : 'woocommerce_review_order_before_submit';

        add_action($target_hook, [$this, 'render_checkout_checkboxes'], 10);
    }

    public function remove_checkout_checkboxes_outside_submit_block(): void
    {
        $this->remove_checkout_checkbox_callback('woocommerce_review_order_after_payment');
        $this->remove_checkout_checkbox_callback('woocommerce_review_order_before_payment');
        $this->remove_checkout_checkbox_callback('woocommerce_checkout_order_review');
        $this->remove_hook_callbacks_by_method('woocommerce_after_checkout_billing_form', 'register_checkout_form_newsletter');
    }

    private function remove_checkout_checkbox_callback(string $hook): void
    {
        $current_priority = has_action($hook, 'woocommerce_gzd_template_render_checkout_checkboxes');

        while ($current_priority !== false) {
            remove_action($hook, 'woocommerce_gzd_template_render_checkout_checkboxes', $current_priority);
            $current_priority = has_action($hook, 'woocommerce_gzd_template_render_checkout_checkboxes');
        }
    }

    private function remove_hook_callbacks_by_method(string $hook, string $method_name): void
    {
        global $wp_filter;

        if (!isset($wp_filter[$hook]) || !$wp_filter[$hook] instanceof \WP_Hook) {
            return;
        }

        foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $function = $callback['function'] ?? null;

                if (!is_array($function) || ($function[1] ?? null) !== $method_name) {
                    continue;
                }

                remove_action($hook, $function, (int) $priority);
            }
        }
    }

    public function render_checkout_checkboxes(): void
    {
        $this->render_newsletter_checkbox_fallback();

        if ($this->checkout_checkboxes_rendered) {
            return;
        }

        if (!function_exists('woocommerce_gzd_template_render_checkout_checkboxes')) {
            return;
        }

        $this->checkout_checkboxes_rendered = true;
        woocommerce_gzd_template_render_checkout_checkboxes();
    }

    private function render_newsletter_checkbox_fallback(): void
    {
        if ($this->newsletter_checkbox_rendered) {
            return;
        }

        if ($this->order_submit_template_renders_newsletter_checkbox()) {
            $this->enqueue_cleverreach_checkout_script();
            return;
        }

        $checkbox_class = '\CleverReach\WooCommerce\ViewModel\NewsletterCheckbox\Newsletter_Checkbox';
        $subscriber_class = '\CleverReach\WooCommerce\Components\Repositories\Customers\Subscriber_Repository';
        $abandoned_cart_class = '\CleverReach\WooCommerce\ViewModel\Dashboard\Abandoned_Cart';

        if (!class_exists($checkbox_class) || !class_exists($subscriber_class) || !class_exists($abandoned_cart_class)) {
            return;
        }

        $newsletter_checkbox_view_model = new $checkbox_class();

        if ($newsletter_checkbox_view_model->get_newsletter_checkbox_disabled()) {
            return;
        }

        $config = $checkbox_class::get_config();
        $status_field = isset($config['newsletterStatusField']) ? (string) $config['newsletterStatusField'] : '';

        if ($status_field === '') {
            return;
        }

        if (function_exists('WC') && WC()->session) {
            WC()->session->set('from_profile', false);
        }

        $abandoned_cart_view_model = new $abandoned_cart_class();
        $current_user = wp_get_current_user();
        $current_user_id = (int) $current_user->ID;
        $cr_checked = get_user_meta($current_user_id, $subscriber_class::get_newsletter_column(), true) === '1';

        $this->newsletter_checkbox_rendered = true;
        $this->enqueue_cleverreach_checkout_script();
        ?>
        <p class="form-row form-row-wide create-account d-flex justify-content-start">
            <input type="hidden" id="crNewsletterStatusField" value="<?php echo esc_attr($status_field); ?>">
            <input type="hidden" id="crSubscribeUrl" value="<?php echo esc_attr($config['subscribeUrl'] ?? ''); ?>">
            <input type="hidden" id="crUndoUrl" value="<?php echo esc_attr($config['undoUrl'] ?? ''); ?>">
            <input type="hidden" id="isAbandonedCartEnabled" value="<?php echo esc_attr((string) $abandoned_cart_view_model->is_ac_function_enabled()); ?>">
            <input type="hidden" id="acTime" value="<?php echo esc_attr((string) $abandoned_cart_view_model->get_ac_time()); ?>">
            <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox me-2">
                <input type="checkbox"
                       class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox"
                       name="<?php echo esc_attr($status_field); ?>"
                       id="<?php echo esc_attr($status_field); ?>"
                       value="1"
                    <?php checked($cr_checked, true); ?>>
            </label>
            <span class="mt-1">
                <?php echo esc_html__('Immer auf dem neuesten Stand bleiben? Abonniere unseren Newsletter und erfahre als erste über neue Aktionen und Rabatte. Der Newsletter erscheint max. 12-mal im Jahr und du kannst dich jederzeit abmelden. ', 'cleverreach-wc'); ?>
            </span>
        </p>
        <?php
    }

    private function order_submit_template_renders_newsletter_checkbox(): bool
    {
        if ($this->order_submit_template_has_newsletter !== null) {
            return $this->order_submit_template_has_newsletter;
        }

        $this->order_submit_template_has_newsletter = false;

        if (!function_exists('woocommerce_gzd_template_order_submit') || !function_exists('wc_locate_template')) {
            return false;
        }

        $default_path = '';

        if (function_exists('WC_germanized')) {
            $default_path = trailingslashit(WC_germanized()->plugin_path()) . 'templates/';
        }

        $template = wc_locate_template('checkout/order-submit.php', 'woocommerce-germanized/', $default_path);

        if (!$template || !is_readable($template)) {
            return false;
        }

        $template_contents = file_get_contents($template);

        if (!is_string($template_contents)) {
            return false;
        }

        $this->order_submit_template_has_newsletter = strpos($template_contents, 'crNewsletterStatusField') !== false
            || strpos($template_contents, 'Newsletter_Checkbox') !== false;

        return $this->order_submit_template_has_newsletter;
    }

    private function enqueue_cleverreach_checkout_script(): void
    {
        $shop_helper_class = 'CleverReach\WooCommerce\Components\Util\Shop_Helper';
        $frontend_controller_class = 'CleverReach\WooCommerce\Controllers\Clever_Reach_Frontend_Controller';

        if (!class_exists($shop_helper_class) || !class_exists($frontend_controller_class)) {
            return;
        }

        $version = defined($frontend_controller_class . '::SCRIPT_VERSION')
            ? constant($frontend_controller_class . '::SCRIPT_VERSION')
            : null;

        wp_enqueue_script(
            'cr_checkout_form_newsletter_checkbox_js',
            $shop_helper_class::get_clever_reach_base_url('/resources/') . 'js/cleverreach.checkout-form-newsletter-checkbox.js',
            ['jquery'],
            $version,
            true
        );
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
        $booking = ( function_exists('WC') && WC()->session ) ? (array) WC()->session->get('booking', []) : [];

        if ('heading' === $args['type'] && (int) ($booking['person_count'] ?? 0) > 1) {
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

    public function mail_woocommerce_checkout_process() {
        $email  = isset($_POST['billing_email']) ? sanitize_email(wp_unslash($_POST['billing_email'])) : '';
        $email2 = isset($_POST['billing_email_confirm']) ? sanitize_email(wp_unslash($_POST['billing_email_confirm'])) : '';

        if ($email && $email2 && strtolower($email) !== strtolower($email2)) {
            wc_add_notice(__('Die E-Mail-Adressen stimmen nicht überein.'), 'error');
        }
    }

    private function calculateStopTimeFromDuration(string $start_time, int $duration_minutes): string
    {
        if ($duration_minutes <= 0) {
            return '';
        }

        if (!preg_match('/^(\d{1,2}):([0-5]\d)(?::[0-5]\d)?$/', $start_time, $matches)) {
            return '';
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];

        if ($hours < 0 || $hours > 23) {
            return '';
        }

        $total = ($hours * 60) + $minutes + $duration_minutes;

        if ($total === 24 * 60) {
            return '24:00';
        }

        $total %= 24 * 60;

        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }
}
