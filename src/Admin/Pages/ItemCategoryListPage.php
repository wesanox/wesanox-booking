<?php

declare(strict_types=1);

namespace Wesanox\Booking\Admin\Pages;

defined('ABSPATH') || exit;

use Wesanox\Booking\Application\ItemCategory\DeleteItemCategoryService;
use Wesanox\Booking\Application\ItemCategory\GetItemCategoryService;
use Wesanox\Booking\Application\ItemCategory\ListItemCategoriesService;
use Wesanox\Booking\Application\ItemCategory\SaveItemCategoryService;
use Wesanox\Booking\Support\ValidationException;

/**
 * Admin page controller for ItemCategory CRUD.
 */
final class ItemCategoryListPage
{
    private const PAGE_SLUG   = 'item-category-settings';
    private const NONCE_SAVE   = 'wesanox_item_category_save';
    private const NONCE_DELETE = 'wesanox_item_category_delete';
    private const NONCE_FIELD  = 'wesanox_nonce';

    /** @var string[] */
    private array $errors = [];

    public function __construct(
        private ListItemCategoriesService   $list_service,
        private GetItemCategoryService      $get_service,
        private SaveItemCategoryService     $save_service,
        private DeleteItemCategoryService   $delete_service,
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

        $name = sanitize_text_field($_POST['category_name'] ?? '');

        try {
            $this->save_service->execute($id, $name);

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
        $category_id = absint($_POST['category_id'] ?? 0);

        check_admin_referer(self::NONCE_DELETE . '_' . $category_id, self::NONCE_FIELD);

        $success = $this->delete_service->execute($category_id);

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
        $categories = $this->list_service->execute();
        $saved      = isset($_GET['saved'])   ? absint($_GET['saved'])   : -1;
        $deleted    = isset($_GET['deleted']) ? absint($_GET['deleted']) : -1;

        require __DIR__ . '/../Views/item-category-list.php';
    }

    private function renderForm(?int $id): void
    {
        $category    = ($id && $id > 0) ? $this->get_service->execute($id) : null;
        $errors      = $this->errors;
        $nonce_action = self::NONCE_SAVE;
        $nonce_field  = self::NONCE_FIELD;

        // Retain user input on validation failure.
        $posted_name = !empty($this->errors) ? sanitize_text_field($_POST['category_name'] ?? '') : null;

        require __DIR__ . '/../Views/item-category-form.php';
    }
}
