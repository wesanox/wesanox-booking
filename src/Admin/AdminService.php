<?php

declare(strict_types=1);

namespace Wesanox\Booking\Admin;

defined('ABSPATH') || exit;

use Wesanox\Booking\Admin\Pages\AreaListPage;
use Wesanox\Booking\Admin\Pages\BookingListPage;
use Wesanox\Booking\Admin\Pages\HolidayListPage;
use Wesanox\Booking\Admin\Pages\ItemCategoryListPage;
use Wesanox\Booking\Admin\Pages\ItemListPage;
use Wesanox\Booking\Application\Area\DeleteAreaService;
use Wesanox\Booking\Application\Area\GetAreaService;
use Wesanox\Booking\Application\Area\ListAreasService;
use Wesanox\Booking\Application\Area\SaveAreaService;
use Wesanox\Booking\Application\Booking\CancelBookingService;
use Wesanox\Booking\Application\Booking\GetBookingService;
use Wesanox\Booking\Application\Booking\ListBookingsService;
use Wesanox\Booking\Application\Holiday\DeleteHolidayService;
use Wesanox\Booking\Application\Holiday\GetHolidayService;
use Wesanox\Booking\Application\Holiday\ListHolidaysService;
use Wesanox\Booking\Application\Holiday\SaveHolidayService;
use Wesanox\Booking\Application\Item\DeleteItemService;
use Wesanox\Booking\Application\Item\GetItemService;
use Wesanox\Booking\Application\Item\ListItemsService;
use Wesanox\Booking\Application\Item\SaveItemService;
use Wesanox\Booking\Application\ItemCategory\DeleteItemCategoryService;
use Wesanox\Booking\Application\ItemCategory\GetItemCategoryService;
use Wesanox\Booking\Application\ItemCategory\ListItemCategoriesService;
use Wesanox\Booking\Application\ItemCategory\SaveItemCategoryService;
use Wesanox\Booking\Application\Rate\DeleteRateService;
use Wesanox\Booking\Application\Rate\GetRateService;
use Wesanox\Booking\Application\Rate\ListRatesService;
use Wesanox\Booking\Application\Rate\SaveRateService;
use Wesanox\Booking\Infrastructure\Area\WordPressAreaRepository;
use Wesanox\Booking\Infrastructure\Booking\WordPressBookingRepository;
use Wesanox\Booking\Infrastructure\Holiday\WordPressHolidayRepository;
use Wesanox\Booking\Infrastructure\Item\WordPressItemRepository;
use Wesanox\Booking\Infrastructure\ItemCategory\WordPressItemCategoryRepository;
use Wesanox\Booking\Infrastructure\Rate\WooCommerceProductProvider;
use Wesanox\Booking\Infrastructure\Rate\WordPressRateRepository;
use Wesanox\Booking\Application\Integration\CheckAreaApiConnectionService;
use Wesanox\Booking\Infrastructure\WesanoxApi\WesanoxApiBridge;
use Wesanox\Booking\View\Admin\Helper\AreaHelper;
use Wesanox\Booking\View\Controller\AdminViewController;

final class AdminService
{
    public function register(): void
    {
        // --- wesanox-api plugin availability notice ---
        add_action('admin_notices', [$this, 'maybeShowApiPluginNotice']);

        // --- Booking ---
        $booking_repo = new WordPressBookingRepository();
        $booking_page = new BookingListPage(
            new ListBookingsService($booking_repo),
            new GetBookingService($booking_repo),
            new CancelBookingService($booking_repo),
        );

        // --- Shared infrastructure ---
        $wc_products         = new WooCommerceProductProvider();
        $area_repo           = new WordPressAreaRepository();
        $rate_repo           = new WordPressRateRepository();
        $item_category_repo  = new WordPressItemCategoryRepository();

        // --- Wesanox API bridge (optional — safe when wesanox-api plugin absent) ---
        $api_bridge = new WesanoxApiBridge();

        // --- Areas (includes Rate management) ---
        $area_page = new AreaListPage(
            new ListAreasService($area_repo),
            new GetAreaService($area_repo),
            new SaveAreaService($area_repo),
            new DeleteAreaService($area_repo),
            new ListRatesService($rate_repo),
            new GetRateService($rate_repo),
            new SaveRateService($rate_repo, $wc_products, $item_category_repo),
            new DeleteRateService($rate_repo),
            $wc_products,
            new ListItemCategoriesService($item_category_repo),
            $api_bridge,
        );

        // --- Item Categories ---
        $item_category_repo = new WordPressItemCategoryRepository();
        $category_page      = new ItemCategoryListPage(
            new ListItemCategoriesService($item_category_repo),
            new GetItemCategoryService($item_category_repo),
            new SaveItemCategoryService($item_category_repo),
            new DeleteItemCategoryService($item_category_repo),
        );

        // --- Items ---
        $item_repo = new WordPressItemRepository();
        $item_page = new ItemListPage(
            new ListItemsService($item_repo),
            new GetItemService($item_repo),
            new SaveItemService($item_repo),
            new DeleteItemService($item_repo),
            new ListAreasService($area_repo),
            new ListItemCategoriesService($item_category_repo),
        );

        // --- Holidays ---
        $holiday_repo = new WordPressHolidayRepository();
        $holiday_page = new HolidayListPage(
            new ListHolidaysService($holiday_repo),
            new GetHolidayService($holiday_repo),
            new SaveHolidayService($holiday_repo),
            new DeleteHolidayService($holiday_repo),
            new ListAreasService($area_repo),
        );

        new AdminViewController($booking_page, $area_page, $item_page, $category_page, $holiday_page);
        new AreaHelper();

        // AJAX: load WooCommerce variations for a given product (admin-only).
        add_action('wp_ajax_wesanox_get_product_variations', function () use ($wc_products): void {
            if (!check_ajax_referer('wesanox_booking_nonce', '_ajax_nonce', false)) {
                wp_send_json_error(['message' => 'Ungültige Anfrage.'], 403);
                return;
            }

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung.'], 403);
                return;
            }

            $product_id = absint($_POST['product_id'] ?? 0);

            if ($product_id <= 0) {
                wp_send_json_error(['message' => 'Ungültige Produkt-ID.'], 400);
                return;
            }

            wp_send_json_success($wc_products->getVariations($product_id));
        });

        // AJAX: test API connection for an area (admin-only).
        add_action('wp_ajax_wesanox_test_api_connection', function () use ($api_bridge): void {
            if (!check_ajax_referer('wesanox_booking_nonce', '_ajax_nonce', false)) {
                wp_send_json_error(['message' => 'Ungültige Anfrage.'], 403);
                return;
            }

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung.'], 403);
                return;
            }

            $credential_id = absint($_POST['credential_id'] ?? 0);

            if ($credential_id <= 0) {
                wp_send_json_error(['message' => 'Ungültige Credential-ID.'], 400);
                return;
            }

            $service  = new CheckAreaApiConnectionService($api_bridge);
            $response = $service->execute($credential_id);

            if ($response->isOk()) {
                wp_send_json_success(['message' => 'Verbindung erfolgreich.']);
            } else {
                wp_send_json_error(['message' => $response->message ?: 'Verbindung fehlgeschlagen.']);
            }
        });

        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function maybeShowApiPluginNotice(): void
    {
        // Only show on wesanox-booking admin pages.
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'wesanox') === false) {
            return;
        }

        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (!is_plugin_active('wesanox-api/wesanox-api.php')) {
            echo '<div class="notice notice-warning">'
               . '<p><strong>Wesanox Booking:</strong> Das Plugin <em>wesanox-api</em> ist nicht aktiv. '
               . 'Die API-Integrations-Funktion steht erst nach Aktivierung zur Verfügung.</p>'
               . '</div>';
        }
    }

    public function enqueueAssets(): void
    {
        $plugin_dir = plugin_dir_path(dirname(__DIR__));

        wp_enqueue_style(
            'wesanox-booking-admin-css',
            plugins_url('styles/admin/styles.css', dirname(__DIR__))
        );

        wp_enqueue_script(
            'wesanox-booking-admin-function',
            plugins_url('scripts/functions/_functions.js', dirname(__DIR__)),
            ['jquery'],
            filemtime($plugin_dir . 'scripts/functions/_functions.js'),
            true
        );

        wp_enqueue_script(
            'wesanox-booking-admin',
            plugins_url('scripts/admin/scripts.js', dirname(__DIR__)),
            ['jquery'],
            filemtime($plugin_dir . 'scripts/admin/scripts.js'),
            true
        );

        wp_localize_script('wesanox-booking-admin', 'ajax_object', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('wesanox_booking_nonce'),
        ]);
    }
}
