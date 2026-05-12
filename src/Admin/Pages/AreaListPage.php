<?php

declare(strict_types=1);

namespace Wesanox\Booking\Admin\Pages;

defined('ABSPATH') || exit;

use Wesanox\Booking\Application\Area\DeleteAreaService;
use Wesanox\Booking\Application\Area\GetAreaService;
use Wesanox\Booking\Application\Area\ListAreasService;
use Wesanox\Booking\Application\Area\SaveAreaService;
use Wesanox\Booking\Application\ItemCategory\ListItemCategoriesService;
use Wesanox\Booking\Application\Rate\DeleteRateService;
use Wesanox\Booking\Application\Rate\GetRateService;
use Wesanox\Booking\Application\Rate\ListRatesService;
use Wesanox\Booking\Application\Rate\SaveRateService;
use Wesanox\Booking\Application\Rate\WooCommerceProductProviderInterface;
use Wesanox\Booking\Domain\Area\Area;
use Wesanox\Booking\Infrastructure\WesanoxApi\WesanoxApiBridgeInterface;
use Wesanox\Booking\Support\ValidationException;

/**
 * Admin page controller for Area CRUD.
 * Also handles Rate CRUD within the context of an Area.
 * The area_id is always taken from the URL — never from POST data.
 */
final class AreaListPage
{
    private const PAGE_SLUG         = 'area-settings';
    private const NONCE_SAVE        = 'wesanox_area_save';
    private const NONCE_DELETE      = 'wesanox_area_delete';
    private const NONCE_RATE_SAVE   = 'wesanox_rate_save';
    private const NONCE_RATE_DELETE = 'wesanox_rate_delete';
    private const NONCE_FIELD       = 'wesanox_nonce';

    /** @var string[] */
    private array $errors = [];

    /** @var string[] */
    private array $rate_errors = [];

    public function __construct(
        private ListAreasService                    $list_service,
        private GetAreaService                      $get_service,
        private SaveAreaService                     $save_service,
        private DeleteAreaService                   $delete_service,
        private ListRatesService                    $list_rates,
        private GetRateService                      $get_rate,
        private SaveRateService                     $save_rate,
        private DeleteRateService                   $delete_rate,
        private WooCommerceProductProviderInterface $products,
        private ListItemCategoriesService           $list_item_categories,
        private ?WesanoxApiBridgeInterface          $api_bridge = null,
    ) {
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung.', 'wesanox-booking'));
        }

        $action = sanitize_key($_GET['action'] ?? '');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost($action);
            // Only reaches here on validation failure.
        }

        match ($action) {
            'create'      => $this->renderForm(null),
            'edit'        => $this->renderForm(absint($_GET['id'] ?? 0)),
            'rate_create' => $this->renderRateForm(absint($_GET['area_id'] ?? 0), null),
            'rate_edit'   => $this->renderRateForm(absint($_GET['area_id'] ?? 0), absint($_GET['rate_id'] ?? 0)),
            default       => $this->renderList(),
        };
    }

    // ── POST handlers ─────────────────────────────────────────────────────────

    private function handlePost(string $action): void
    {
        match (true) {
            $action === 'create'      => $this->handleSave(null),
            $action === 'edit'        => $this->handleSave(absint($_GET['id'] ?? 0) ?: null),
            $action === 'delete'      => $this->handleDelete(),
            $action === 'rate_create' => $this->handleRateSave(absint($_GET['area_id'] ?? 0), null),
            $action === 'rate_edit'   => $this->handleRateSave(absint($_GET['area_id'] ?? 0), absint($_GET['rate_id'] ?? 0) ?: null),
            $action === 'rate_delete' => $this->handleRateDelete(absint($_GET['area_id'] ?? 0)),
            default                   => null,
        };
    }

    private function handleSave(?int $id): void
    {
        check_admin_referer(self::NONCE_SAVE, self::NONCE_FIELD);

        $name             = sanitize_text_field($_POST['area_name'] ?? '');
        $opening          = $this->extractOpening();
        $time_settings    = $this->extractTimeSettings();
        $booking_settings = $this->extractBookingSettings();
        $api_settings     = $this->extractApiSettings();

        try {
            $this->save_service->execute($id, $name, $opening, $time_settings, $booking_settings, $api_settings);

            wp_safe_redirect(
                add_query_arg(['page' => self::PAGE_SLUG, 'saved' => '1'], admin_url('admin.php'))
            );
            exit;
        } catch (ValidationException $e) {
            $this->errors = $e->errors();
        }
    }

    private function handleDelete(): void
    {
        $area_id = absint($_POST['area_id'] ?? 0);

        check_admin_referer(self::NONCE_DELETE . '_' . $area_id, self::NONCE_FIELD);

        $success = $this->delete_service->execute($area_id);

        wp_safe_redirect(
            add_query_arg(
                ['page' => self::PAGE_SLUG, 'deleted' => $success ? '1' : '0'],
                admin_url('admin.php')
            )
        );
        exit;
    }

    /**
     * Save (create or update) a Rate within the given Area.
     * area_id always from URL.
     */
    private function handleRateSave(int $area_id, ?int $rate_id): void
    {
        if ($area_id <= 0 || !$this->get_service->execute($area_id)) {
            wp_die(esc_html__('Area nicht gefunden.', 'wesanox-booking'));
        }

        check_admin_referer(self::NONCE_RATE_SAVE, self::NONCE_FIELD);

        $variation_raw    = sanitize_text_field($_POST['wc_variation_id'] ?? '');
        $item_category_id = absint($_POST['item_category_id'] ?? 0);
        $days_raw         = is_array($_POST['days'] ?? null) ? $_POST['days'] : [];
        $days             = array_map('sanitize_key', $days_raw);

        try {
            $this->save_rate->execute(
                id:               $rate_id,
                area_id:          $area_id,          // always from URL
                item_category_id: $item_category_id,
                name:             sanitize_text_field($_POST['rate_name']   ?? ''),
                time_from:        sanitize_text_field($_POST['time_from']   ?? ''),
                time_to:          sanitize_text_field($_POST['time_to']     ?? ''),
                days:             $days,
                wc_product_id:    absint($_POST['wc_product_id'] ?? 0),
                wc_variation_id:  ($variation_raw !== '' && $variation_raw !== '0') ? absint($variation_raw) : null,
                is_active:        !empty($_POST['is_active']),
                sort_order:       absint($_POST['sort_order'] ?? 0),
            );

            wp_safe_redirect(
                add_query_arg(
                    ['page' => self::PAGE_SLUG, 'action' => 'edit', 'id' => $area_id, 'rate_saved' => '1'],
                    admin_url('admin.php')
                )
            );
            exit;
        } catch (ValidationException $e) {
            $this->rate_errors = $e->errors();
        }
    }

    /**
     * Delete a Rate — verifies it belongs to the current Area.
     */
    private function handleRateDelete(int $area_id): void
    {
        $rate_id = absint($_POST['rate_id'] ?? 0);

        check_admin_referer(self::NONCE_RATE_DELETE . '_' . $rate_id, self::NONCE_FIELD);

        $rate = $this->get_rate->execute($rate_id);
        if (!$rate || $rate->area_id !== $area_id) {
            wp_die(esc_html__('Rate nicht gefunden oder kein Zugriff.', 'wesanox-booking'));
        }

        $success = $this->delete_rate->execute($rate_id);

        wp_safe_redirect(
            add_query_arg(
                ['page' => self::PAGE_SLUG, 'action' => 'edit', 'id' => $area_id, 'rate_deleted' => $success ? '1' : '0'],
                admin_url('admin.php')
            )
        );
        exit;
    }

    // ── renderers ─────────────────────────────────────────────────────────────

    private function renderList(): void
    {
        $areas   = $this->list_service->execute();
        $saved   = isset($_GET['saved'])   ? absint($_GET['saved'])   : -1;
        $deleted = isset($_GET['deleted']) ? absint($_GET['deleted']) : -1;

        require __DIR__ . '/../Views/area-list.php';
    }

    private function renderForm(?int $id): void
    {
        $area            = ($id && $id > 0) ? $this->get_service->execute($id) : null;
        $item_categories = $this->list_item_categories->execute();
        $rates           = ($id && $id > 0) ? $this->list_rates->execute($id) : [];
        $products        = $this->products->getProducts();

        $errors      = $this->errors;
        $rate_errors = $this->rate_errors;
        $nonce_action = self::NONCE_SAVE;
        $nonce_field  = self::NONCE_FIELD;

        $rate_saved   = isset($_GET['rate_saved'])   ? absint($_GET['rate_saved'])   : -1;
        $rate_deleted = isset($_GET['rate_deleted']) ? absint($_GET['rate_deleted']) : -1;

        $posted = !empty($this->errors) ? $this->recoverPostedValues() : null;

        $api_available        = $this->api_bridge !== null && $this->api_bridge->isAvailable();
        $api_credential_options = $api_available ? ($this->api_bridge->listCredentialOptions()) : [];

        require __DIR__ . '/../Views/area-form.php';
    }

    private function renderRateForm(int $area_id, ?int $rate_id): void
    {
        if ($area_id <= 0) {
            wp_die(esc_html__('Ungültige Area.', 'wesanox-booking'));
        }

        $area = $this->get_service->execute($area_id);
        if (!$area) {
            wp_die(esc_html__('Area nicht gefunden.', 'wesanox-booking'));
        }

        $rate            = ($rate_id && $rate_id > 0) ? $this->get_rate->execute($rate_id) : null;
        $item_categories = $this->list_item_categories->execute();
        $products        = $this->products->getProducts();
        $rate_errors     = $this->rate_errors;
        $posted          = !empty($this->rate_errors) ? $this->recoverRatePostedValues() : null;

        // Pre-select category from URL (e.g. coming from "+ Rate anlegen" in a specific category row).
        $preselect_category_id = absint($_GET['category_id'] ?? 0);

        require __DIR__ . '/../Views/area-rate-form.php';
    }

    // ── form data extraction ──────────────────────────────────────────────────

    /**
     * @return array<string, array{enabled: bool, from: ?string, to: ?string}>
     */
    private function extractOpening(): array
    {
        $result      = [];
        $raw_opening = $_POST['opening'] ?? [];
        $raw_opening = is_array($raw_opening) ? $raw_opening : [];

        foreach (Area::WEEKDAYS as $day) {
            $day_data     = $raw_opening[$day] ?? [];
            $enabled      = !empty($day_data['enabled']);
            $from         = $enabled ? sanitize_text_field($day_data['from'] ?? '') : null;
            $to           = $enabled ? sanitize_text_field($day_data['to']   ?? '') : null;
            $result[$day] = [
                'enabled' => $enabled,
                'from'    => ($from !== '' ? $from : null),
                'to'      => ($to !== '' ? $to : null),
            ];
        }

        return $result;
    }

    /**
     * @return array{area_time_separator: string, area_time_sheet: string, area_time_min: string, area_time_max: string}
     */
    private function extractTimeSettings(): array
    {
        return [
            'area_time_separator' => sanitize_key($_POST['area_time_separator'] ?? '1'),
            'area_time_sheet'     => sanitize_text_field($_POST['area_time_sheet'] ?? ''),
            'area_time_min'       => sanitize_text_field($_POST['area_time_min']   ?? ''),
            'area_time_max'       => sanitize_text_field($_POST['area_time_max']   ?? ''),
        ];
    }

    /**
     * @return array{booking_type: string, redirect_url: string, title: string}
     */
    private function extractBookingSettings(): array
    {
        return [
            'booking_type' => sanitize_key($_POST['booking_type']         ?? 'standard'),
            'redirect_url' => sanitize_url($_POST['booking_redirect_url'] ?? ''),
            'title'        => sanitize_text_field($_POST['booking_title'] ?? ''),
        ];
    }

    /**
     * @return array{api_enabled: bool, credential_id: int, external_id: string}
     */
    private function extractApiSettings(): array
    {
        return [
            'api_enabled'   => !empty($_POST['api_enabled']),
            'credential_id' => absint($_POST['api_credential_id'] ?? 0),
            'external_id'   => sanitize_text_field($_POST['api_external_id'] ?? ''),
        ];
    }

    private function recoverPostedValues(): Area
    {
        $opening          = $this->extractOpening();
        $time_settings    = $this->extractTimeSettings();
        $booking_settings = $this->extractBookingSettings();
        $api_settings     = $this->extractApiSettings();

        return new Area(
            id:                    0,
            name:                  sanitize_text_field($_POST['area_name'] ?? ''),
            opening_json:          (string) json_encode($opening),
            time_settings_json:    (string) json_encode($time_settings),
            booking_settings_json: (string) json_encode($booking_settings),
            api_settings_json:     (string) json_encode($api_settings),
        );
    }

    /**
     * @return array{rate_name: string, time_from: string, time_to: string, days: string[], item_category_id: int, wc_product_id: int, wc_variation_id: ?int, is_active: bool, sort_order: int}
     */
    private function recoverRatePostedValues(): array
    {
        $variation_raw = sanitize_text_field($_POST['wc_variation_id'] ?? '');
        $days_raw      = is_array($_POST['days'] ?? null) ? $_POST['days'] : [];

        return [
            'item_category_id' => absint($_POST['item_category_id'] ?? 0),
            'rate_name'        => sanitize_text_field($_POST['rate_name']   ?? ''),
            'time_from'        => sanitize_text_field($_POST['time_from']   ?? ''),
            'time_to'          => sanitize_text_field($_POST['time_to']     ?? ''),
            'days'             => array_map('sanitize_key', $days_raw),
            'wc_product_id'    => absint($_POST['wc_product_id'] ?? 0),
            'wc_variation_id'  => ($variation_raw !== '' && $variation_raw !== '0') ? absint($variation_raw) : null,
            'is_active'        => !empty($_POST['is_active']),
            'sort_order'       => absint($_POST['sort_order'] ?? 0),
        ];
    }
}
