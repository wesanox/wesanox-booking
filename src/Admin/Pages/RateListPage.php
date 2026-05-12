<?php

declare(strict_types=1);

namespace Wesanox\Booking\Admin\Pages;

defined('ABSPATH') || exit;

use Wesanox\Booking\Application\Area\ListAreasService;
use Wesanox\Booking\Application\Rate\DeleteRateService;
use Wesanox\Booking\Application\Rate\GetRateService;
use Wesanox\Booking\Application\Rate\ListRatesService;
use Wesanox\Booking\Application\Rate\SaveRateService;
use Wesanox\Booking\Application\Rate\WooCommerceProductProviderInterface;
use Wesanox\Booking\Support\ValidationException;

/**
 * Admin page controller for Rate CRUD.
 */
final class RateListPage
{
    private const PAGE_SLUG    = 'rate-settings';
    private const NONCE_SAVE   = 'wesanox_rate_save';
    private const NONCE_DELETE = 'wesanox_rate_delete';
    private const NONCE_FIELD  = 'wesanox_nonce';

    /** @var string[] */
    private array $errors = [];

    public function __construct(
        private ListRatesService                   $list_service,
        private GetRateService                     $get_service,
        private SaveRateService                    $save_service,
        private DeleteRateService                  $delete_service,
        private ListAreasService                   $list_areas,
        private WooCommerceProductProviderInterface $products,
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
        }

        match ($action) {
            'create' => $this->renderForm(null),
            'edit'   => $this->renderForm(absint($_GET['id'] ?? 0)),
            default  => $this->renderList(),
        };
    }

    // ── POST handlers ─────────────────────────────────────────────────────────

    private function handlePost(string $action): void
    {
        if ($action === 'create') {
            $this->handleSave(null);
        } elseif ($action === 'edit') {
            $this->handleSave(absint($_GET['id'] ?? 0) ?: null);
        } elseif ($action === 'delete') {
            $this->handleDelete();
        }
    }

    private function handleSave(?int $id): void
    {
        check_admin_referer(self::NONCE_SAVE, self::NONCE_FIELD);

        $variation_raw = sanitize_text_field($_POST['wc_variation_id'] ?? '');

        $days_raw = isset($_POST['days']) && is_array($_POST['days'])
            ? array_map('sanitize_key', $_POST['days'])
            : [];

        try {
            $this->save_service->execute(
                id:               $id,
                area_id:          absint($_POST['area_id']       ?? 0),
                item_category_id: 0,
                name:             sanitize_text_field($_POST['rate_name'] ?? ''),
                time_from:        sanitize_text_field($_POST['time_from'] ?? ''),
                time_to:          sanitize_text_field($_POST['time_to']   ?? ''),
                days:             $days_raw,
                wc_product_id:    absint($_POST['wc_product_id']  ?? 0),
                wc_variation_id:  ($variation_raw !== '' && $variation_raw !== '0') ? absint($variation_raw) : null,
                is_active:        !empty($_POST['is_active']),
                sort_order:       absint($_POST['sort_order'] ?? 0),
            );

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
        $rate_id = absint($_POST['rate_id'] ?? 0);
        check_admin_referer(self::NONCE_DELETE . '_' . $rate_id, self::NONCE_FIELD);

        $success = $this->delete_service->execute($rate_id);

        wp_safe_redirect(
            add_query_arg(
                ['page' => self::PAGE_SLUG, 'deleted' => $success ? '1' : '0'],
                admin_url('admin.php')
            )
        );
        exit;
    }

    // ── renderers ─────────────────────────────────────────────────────────────

    private function renderList(): void
    {
        $filter_area = isset($_GET['filter_area']) ? absint($_GET['filter_area']) : 0;

        $rates   = $this->list_service->execute($filter_area > 0 ? $filter_area : null);
        $areas   = $this->list_areas->execute();
        $saved   = isset($_GET['saved'])   ? absint($_GET['saved'])   : -1;
        $deleted = isset($_GET['deleted']) ? absint($_GET['deleted']) : -1;

        require __DIR__ . '/../Views/rate-list.php';
    }

    private function renderForm(?int $id): void
    {
        $rate         = ($id && $id > 0) ? $this->get_service->execute($id) : null;
        $areas        = $this->list_areas->execute();
        $products     = $this->products->getProducts();
        $errors       = $this->errors;
        $nonce_action = self::NONCE_SAVE;
        $nonce_field  = self::NONCE_FIELD;

        $posted = !empty($this->errors) ? $this->recoverPostedValues() : null;

        require __DIR__ . '/../Views/rate-form.php';
    }

    private function recoverPostedValues(): array
    {
        $variation_raw = sanitize_text_field($_POST['wc_variation_id'] ?? '');

        return [
            'area_id'          => absint($_POST['area_id']       ?? 0),
            'rate_name'        => sanitize_text_field($_POST['rate_name'] ?? ''),
            'time_from'        => sanitize_text_field($_POST['time_from'] ?? ''),
            'time_to'          => sanitize_text_field($_POST['time_to']   ?? ''),
            'days'             => isset($_POST['days']) && is_array($_POST['days'])
                                      ? array_map('sanitize_key', $_POST['days'])
                                      : [],
            'wc_product_id'    => absint($_POST['wc_product_id']  ?? 0),
            'wc_variation_id'  => ($variation_raw !== '' && $variation_raw !== '0') ? absint($variation_raw) : null,
            'is_active'        => !empty($_POST['is_active']),
            'sort_order'       => absint($_POST['sort_order'] ?? 0),
        ];
    }
}
