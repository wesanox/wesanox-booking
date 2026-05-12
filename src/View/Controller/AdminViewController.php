<?php

namespace Wesanox\Booking\View\Controller;

defined('ABSPATH') || exit;

use Wesanox\Booking\Admin\Pages\AreaListPage;
use Wesanox\Booking\Admin\Pages\BookingListPage;
use Wesanox\Booking\Admin\Pages\HolidayListPage;
use Wesanox\Booking\Admin\Pages\ItemCategoryListPage;
use Wesanox\Booking\Admin\Pages\ItemListPage;

class AdminViewController
{
    private BookingListPage      $booking_list_page;
    private AreaListPage         $area_page;
    private ItemListPage         $item_page;
    private ItemCategoryListPage $category_page;
    private HolidayListPage      $holiday_page;

    public function __construct(
        BookingListPage      $booking_list_page,
        AreaListPage         $area_page,
        ItemListPage         $item_page,
        ItemCategoryListPage $category_page,
        HolidayListPage      $holiday_page,
    ) {
        $this->booking_list_page = $booking_list_page;
        $this->area_page         = $area_page;
        $this->item_page         = $item_page;
        $this->category_page     = $category_page;
        $this->holiday_page      = $holiday_page;

        add_action('admin_menu', [$this, 'wesanox_add_booking_page']);
        add_action('admin_menu', [$this, 'wesanox_add_area_sub_page']);
        add_action('admin_menu', [$this, 'wesanox_add_room_sub_page']);
        add_action('admin_menu', [$this, 'wesanox_add_item_category_sub_page']);
        add_action('admin_menu', [$this, 'wesanox_add_holiday_sub_page']);
    }

    public function wesanox_add_booking_page(): void
    {
        add_menu_page(
            __('Buchungs - Tool', 'wesanox-booking'),
            __('Buchungen', 'wesanox-booking'),
            'manage_options',
            'admin-booking-overview',
            [$this->booking_list_page, 'render'],
            'dashicons-calendar-alt',
            89
        );
    }

    public function wesanox_add_area_sub_page(): void
    {
        add_submenu_page(
            'admin-booking-overview',
            __('Areas', 'wesanox-booking'),
            __('Areas', 'wesanox-booking'),
            'manage_options',
            'area-settings',
            [$this->area_page, 'render'],
            89
        );
    }

    public function wesanox_add_room_sub_page(): void
    {
        add_submenu_page(
            'admin-booking-overview',
            __('Items', 'wesanox-booking'),
            __('Items', 'wesanox-booking'),
            'manage_options',
            'room-settings',
            [$this->item_page, 'render'],
            89
        );
    }

    public function wesanox_add_item_category_sub_page(): void
    {
        add_submenu_page(
            'admin-booking-overview',
            __('Item-Kategorien', 'wesanox-booking'),
            __('Item-Kategorien', 'wesanox-booking'),
            'manage_options',
            'item-category-settings',
            [$this->category_page, 'render'],
            89
        );
    }

    public function wesanox_add_holiday_sub_page(): void
    {
        add_submenu_page(
            'admin-booking-overview',
            __('Feiertage', 'wesanox-booking'),
            __('Feiertage', 'wesanox-booking'),
            'manage_options',
            'holiday-settings',
            [$this->holiday_page, 'render'],
            89
        );
    }
}
