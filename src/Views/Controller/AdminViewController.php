<?php

namespace Wesanox\Booking\Views\Controller;

defined( 'ABSPATH' )|| exit;

use Exception;
use Wesanox\Booking\Views\Admin\Edit\EditArea;
use Wesanox\Booking\Views\Admin\Edit\EditBooking;
use Wesanox\Booking\Views\Admin\Edit\EditRoom;
use Wesanox\Booking\Views\Admin\Edit\EditHolidays;

class AdminViewController
{
    private EditArea $edit_area;
    private EditBooking $edit_booking;
    private EditRoom $edit_room;
    private EditHolidays $edit_holiday;

    public function __construct()
    {
        $this->edit_area = new EditArea();
        $this->edit_booking = new EditBooking();
        $this->edit_room = new EditRoom();
        $this->edit_holiday = new EditHolidays();

        add_action('admin_menu', [$this, 'wesanox_add_booking_page']);
        add_action('admin_menu', [$this, 'wesanox_add_area_sub_page']);
        add_action('admin_menu', [$this, 'wesanox_add_room_sub_page']);
        add_action('admin_menu', [$this, 'wesanox_add_holiday_sub_page']);
    }

    /**
     * Set the page for the plugin inside the WordPress backend
     *
     * @return void
     * @throws Exception
     */
    public function wesanox_add_booking_page() : void
    {
        if (!isset( $this->edit_booking )) {
            throw new Exception("admin_view_account wurde nicht gesetzt!");
        }

        add_menu_page (
            'Buchungs - Tool',
            'Buchungen',
            'manage_options',
            'admin-booking-overview',
            [ $this->edit_booking , 'wesanox_admin_edit_booking_render'],
            'dashicons-calendar-alt',
            89
        );
    }

    /**
     * Set the page for the plugin inside the WordPress backend
     *
     * @return void
     * @throws Exception
     */
    public function wesanox_add_area_sub_page() : void
    {
        if (!isset( $this->edit_area )) {
            throw new Exception("admin_view_account wurde nicht gesetzt!");
        }

        add_submenu_page (
            'admin-booking-overview',
            'Areas',
            'Areas',
            'manage_options',
            'area-settings',
            [ $this->edit_area , 'wesanox_admin_edit_area_render'],
            89
        );
    }

    /**
     * Set the page for the plugin inside the WordPress backend
     *
     * @return void
     * @throws Exception
     */
    public function wesanox_add_room_sub_page() : void
    {
        if (!isset( $this->edit_room )) {
            throw new Exception("admin_view_account wurde nicht gesetzt!");
        }

        add_submenu_page (
            'admin-booking-overview',
            'Räume',
            'Räume',
            'manage_options',
            'room-settings',
            [ $this->edit_room , 'wesanox_admin_edit_room_render'],
            89
        );
    }

    /**
     * Set the page for the plugin inside the WordPress backend
     *
     * @return void
     * @throws Exception
     */
    public function wesanox_add_holiday_sub_page() : void
    {
        if (!isset( $this->edit_holiday )) {
            throw new Exception("admin_view_account wurde nicht gesetzt!");
        }

        add_submenu_page (
            'admin-booking-overview',
            'Feiertage',
            'Feiertage',
            'manage_options',
            'holiday-settings',
            [ $this->edit_holiday , 'wesanox_admin_edit_holiday_render'],
            89
        );
    }
}