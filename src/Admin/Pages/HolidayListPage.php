<?php

declare(strict_types=1);

namespace Wesanox\Booking\Admin\Pages;

defined('ABSPATH') || exit;

use Wesanox\Booking\Application\Area\ListAreasService;
use Wesanox\Booking\Application\Holiday\DeleteHolidayService;
use Wesanox\Booking\Application\Holiday\GetHolidayService;
use Wesanox\Booking\Application\Holiday\ListHolidaysService;
use Wesanox\Booking\Application\Holiday\SaveHolidayService;
use Wesanox\Booking\Domain\Holiday\Holiday;
use Wesanox\Booking\Support\ValidationException;

final class HolidayListPage
{
    private const PAGE_SLUG    = 'holiday-settings';
    private const NONCE_SAVE   = 'wesanox_holiday_save';
    private const NONCE_DELETE = 'wesanox_holiday_delete';
    private const NONCE_FIELD  = 'wesanox_nonce';

    /** @var string[] */
    private array $errors = [];

    public function __construct(
        private ListHolidaysService  $list_service,
        private GetHolidayService    $get_service,
        private SaveHolidayService   $save_service,
        private DeleteHolidayService $delete_service,
        private ListAreasService     $list_areas_service,
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
    // POST handlers
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

        $date    = sanitize_text_field($_POST['opening_date']    ?? '');
        $from    = sanitize_text_field($_POST['opening_from']    ?? '');
        $to      = sanitize_text_field($_POST['opening_to']      ?? '');
        $closed  = !empty($_POST['opening_closed']);
        $holiday = !empty($_POST['opening_holiday']);
        $area_id = absint($_POST['area_id'] ?? 0) ?: null;

        try {
            $this->save_service->execute(
                $id,
                $date,
                $from !== '' ? $from : null,
                $to   !== '' ? $to   : null,
                $closed,
                $holiday,
                $area_id,
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
        $holiday_id = absint($_POST['holiday_id'] ?? 0);

        check_admin_referer(self::NONCE_DELETE . '_' . $holiday_id, self::NONCE_FIELD);

        $success = $this->delete_service->execute($holiday_id);

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
        $holidays = $this->list_service->execute();
        $areas    = $this->list_areas_service->execute();
        $saved    = isset($_GET['saved'])   ? absint($_GET['saved'])   : -1;
        $deleted  = isset($_GET['deleted']) ? absint($_GET['deleted']) : -1;

        require __DIR__ . '/../Views/holiday-list.php';
    }

    private function renderForm(?int $id): void
    {
        $holiday      = ($id && $id > 0) ? $this->get_service->execute($id) : null;
        $errors       = $this->errors;
        $nonce_action = self::NONCE_SAVE;
        $nonce_field  = self::NONCE_FIELD;
        $posted       = !empty($this->errors) ? $this->recoverPostedValues() : null;
        $areas        = $this->list_areas_service->execute();

        require __DIR__ . '/../Views/holiday-form.php';
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function recoverPostedValues(): Holiday
    {
        return new Holiday(
            id:              0,
            opening_date:    sanitize_text_field($_POST['opening_date']    ?? ''),
            opening_from:    sanitize_text_field($_POST['opening_from']    ?? '') ?: null,
            opening_to:      sanitize_text_field($_POST['opening_to']      ?? '') ?: null,
            opening_closed:  !empty($_POST['opening_closed']),
            opening_holiday: !empty($_POST['opening_holiday']),
            area_id:         absint($_POST['area_id'] ?? 0) ?: null,
        );
    }
}
