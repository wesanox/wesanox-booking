<?php

declare(strict_types=1);

namespace Wesanox\Booking\Admin\Pages;

defined('ABSPATH') || exit;

use Wesanox\Booking\Application\Area\ListAreasService;
use Wesanox\Booking\Application\Item\DeleteItemService;
use Wesanox\Booking\Application\Item\GetItemService;
use Wesanox\Booking\Application\Item\ListItemsService;
use Wesanox\Booking\Application\Item\SaveItemService;
use Wesanox\Booking\Application\ItemCategory\ListItemCategoriesService;
use Wesanox\Booking\Domain\Item\Item;
use Wesanox\Booking\Support\ValidationException;

/**
 * Admin page controller for Item CRUD.
 */
final class ItemListPage
{
    private const PAGE_SLUG   = 'room-settings';
    private const NONCE_SAVE   = 'wesanox_item_save';
    private const NONCE_DELETE = 'wesanox_item_delete';
    private const NONCE_FIELD  = 'wesanox_nonce';

    /** @var string[] */
    private array $errors = [];

    public function __construct(
        private ListItemsService          $list_service,
        private GetItemService            $get_service,
        private SaveItemService           $save_service,
        private DeleteItemService         $delete_service,
        private ListAreasService          $list_areas_service,
        private ListItemCategoriesService $list_categories_service,
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

    // -------------------------------------------------------------------------
    // POST Handlers
    // -------------------------------------------------------------------------

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

        $name             = sanitize_text_field($_POST['item_name'] ?? '');
        $area_id_raw      = $_POST['area_id'] ?? '';
        $area_id          = ($area_id_raw !== '' && (int) $area_id_raw > 0) ? (int) $area_id_raw : null;
        $category_id_raw  = $_POST['item_category_id'] ?? '';
        $category_id      = ($category_id_raw !== '' && (int) $category_id_raw > 0) ? (int) $category_id_raw : null;
        $inactive         = !empty($_POST['inactive']);
        $inactiv_from     = sanitize_text_field($_POST['inactiv_from'] ?? '');
        $inactiv_to       = sanitize_text_field($_POST['inactiv_to'] ?? '');
        $inactiv_note     = sanitize_textarea_field($_POST['inactiv_note'] ?? '');

        try {
            $this->save_service->execute(
                id:               $id,
                name:             $name,
                area_id:          $area_id,
                item_category_id: $category_id,
                inactive:         $inactive,
                inactiv_from:     $inactiv_from !== '' ? $inactiv_from : null,
                inactiv_to:       $inactiv_to !== '' ? $inactiv_to : null,
                inactiv_note:     $inactiv_note !== '' ? $inactiv_note : null,
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
        $item_id = absint($_POST['item_id'] ?? 0);

        check_admin_referer(self::NONCE_DELETE . '_' . $item_id, self::NONCE_FIELD);

        $success = $this->delete_service->execute($item_id);

        wp_safe_redirect(
            add_query_arg(
                ['page' => self::PAGE_SLUG, 'deleted' => $success ? '1' : '0'],
                admin_url('admin.php')
            )
        );
        exit;
    }

    // -------------------------------------------------------------------------
    // Renderers
    // -------------------------------------------------------------------------

    private function renderList(): void
    {
        // Filter params
        $filter_area     = isset($_GET['filter_area'])     ? absint($_GET['filter_area'])     : null;
        $filter_category = isset($_GET['filter_category']) ? absint($_GET['filter_category']) : null;
        $filter_inactive_raw = $_GET['filter_inactive'] ?? '';
        $filter_inactive = match ($filter_inactive_raw) {
            '1'     => true,
            '0'     => false,
            default => null,
        };

        $items      = $this->list_service->execute($filter_area ?: null, $filter_category ?: null, $filter_inactive);
        $areas      = $this->list_areas_service->execute();
        $categories = $this->list_categories_service->execute();
        $saved      = isset($_GET['saved'])   ? absint($_GET['saved'])   : -1;
        $deleted    = isset($_GET['deleted']) ? absint($_GET['deleted']) : -1;

        require __DIR__ . '/../Views/item-list.php';
    }

    private function renderForm(?int $id): void
    {
        $item         = ($id && $id > 0) ? $this->get_service->execute($id) : null;
        $areas        = $this->list_areas_service->execute();
        $categories   = $this->list_categories_service->execute();
        $errors       = $this->errors;
        $nonce_action  = self::NONCE_SAVE;
        $nonce_field   = self::NONCE_FIELD;

        // Retain user input on validation failure.
        $posted = !empty($this->errors) ? $this->recoverPostedItem() : null;

        require __DIR__ . '/../Views/item-form.php';
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function recoverPostedItem(): Item
    {
        $area_id_raw     = $_POST['area_id'] ?? '';
        $category_id_raw = $_POST['item_category_id'] ?? '';

        return new Item(
            id:               0,
            name:             sanitize_text_field($_POST['item_name'] ?? ''),
            area_id:          ($area_id_raw !== '' && (int) $area_id_raw > 0) ? (int) $area_id_raw : null,
            item_category_id: ($category_id_raw !== '' && (int) $category_id_raw > 0) ? (int) $category_id_raw : null,
            inactive:         !empty($_POST['inactive']),
            inactiv_from:     sanitize_text_field($_POST['inactiv_from'] ?? '') ?: null,
            inactiv_to:       sanitize_text_field($_POST['inactiv_to'] ?? '') ?: null,
            inactiv_note:     sanitize_textarea_field($_POST['inactiv_note'] ?? '') ?: null,
        );
    }
}
