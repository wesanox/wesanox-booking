<?php

namespace Wesanox\Booking\View\Admin\Helper;

defined( 'ABSPATH' )|| exit;

use Wesanox\Booking\Boot\Data\HandlerData;

class AreaHelper
{
    private string $table_name;

    protected HandlerData $handler_data;

    public function __construct()
    {
        $wpdb = $GLOBALS['wpdb'];

        $this->table_name = $wpdb->prefix . 'wesanox_areas';

        $this->handler_data = new HandlerData($wpdb);

        add_action('wp_ajax_area_insert_data_action', [$this, 'admin_area_insert_data']);
        add_action('wp_ajax_nopriv_area_insert_data_action', [$this, 'admin_area_insert_data']);
        add_action('wp_ajax_opening_insert_data_action', [$this, 'admin_opening_insert_data']);
        add_action('wp_ajax_nopriv_opening_insert_data_action', [$this, 'admin_opening_insert_data']);
    }

    /**
     * @return void
     */
    public function admin_area_insert_data() : void
    {
        if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce($_POST['_ajax_nonce'], 'wesanox_booking_nonce')) {
            wp_send_json_error(['message' => 'Ungültige Anfrage.']);
            return;
        }

        if (isset($_POST['data'])) {
            parse_str($_POST['data'], $parsed_data);

            if (empty($parsed_data['area_name']) || empty($parsed_data['area_time_separator'])) {
                wp_send_json_error(['message' => 'Pflichtfelder fehlen für den Bereich']);
                return;
            }

            $area_time_settings = [
                'area_time_separator' => sanitize_text_field($parsed_data['area_time_separator']),
                'area_time_sheet' => sanitize_text_field($parsed_data['area_time_sheet']),
                'area_time_min' => sanitize_text_field($parsed_data['area_time_min']),
                'area_time_max' => sanitize_text_field($parsed_data['area_time_max']),
            ];

            $data = [
                'area_name' => sanitize_text_field($parsed_data['area_name']),
                'area_time_settings' => json_encode($area_time_settings),
            ];


            $return = $this->handler_data->wesanox_insert_data($data, $this->table_name);


            if ($return) {
                wp_send_json_success(['message' => 'Bereich wurde erfolgreich angelegt.']);
            } else {
                wp_send_json_error(['message' => 'Fehler beim Einfügen des Bereichs.']);
            }
        } else {
            wp_send_json_error(['message' => "Fehler aufgetreten."]);
        }
    }

    /**
     * @return void
     */
    public function admin_opening_insert_data() : void
    {
        if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce($_POST['_ajax_nonce'], 'wesanox_booking_nonce')) {
            wp_send_json_error(['message' => 'Ungültige Anfrage.']);
            return;
        }

        if (isset($_POST['data'])) {
            parse_str($_POST['data'], $parsed_data);

            if (empty($parsed_data['booking_date_start']) || empty($parsed_data['booking_date_end']) || empty($parsed_data['account_id']) || empty($parsed_data['room_id'])) {
                wp_send_json_error(['message' => 'Pflichtfelder fehlen für die Buchung']);
                return;
            }

            $data = [
                'booking_notice' => sanitize_text_field($parsed_data['booking_notice']),
                'booking_date_start' => sanitize_text_field($parsed_data['booking_date_start']),
                'booking_date_end' => sanitize_text_field($parsed_data['booking_date_end']),
                'account_id' => sanitize_text_field($parsed_data['account_id']),
                'room_id' => sanitize_text_field($parsed_data['room_id']),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];


            $return = $this->handler_data->wesanox_insert_data($data, $this->table_name);


            if ($return === true) {
                wp_send_json_success(['message' => 'Buchung wurde erfolgreich angelegt.']);
            } else {
                wp_send_json_error(['message' => 'Fehler beim Einfügen der Buchung.']);
            }
        } else {
            wp_send_json_error(['message' => "Fehler aufgetreten."]);
        }
    }
}

