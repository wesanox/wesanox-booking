<?php

namespace Wesanox\Booking\Views\Admin\Edit;

use Wesanox\Booking\Boot\Data\Handler;

defined( 'ABSPATH' )|| exit;

class EditArea
{
    private string $table_name;

    protected Handler $handler;

    public function __construct()
    {
        $wpdb = $GLOBALS['wpdb'];

        $this->table_name = $wpdb->prefix . 'wesanox_areas';

        $this->handler = new Handler($wpdb);
    }

    /**
     * @return void
     */
    public function wesanox_admin_edit_area_render(): void
    {
        $tabs = [
            'areas'    => 'Areas',
            'openings' => 'Öffnungszeiten',
        ];

        $area_modal = $this->render_opening_modal() . $this->render_area_modal();

        $active = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'areas';

        if (!isset($tabs[$active])) {
            $active = 'areas';
        }

        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : 'area-settings';

        $nav = $this->render_tabs_nav($tabs, $active, $page);

        $content = match ($active) {
            'openings' => $this->render_tab_openings(),
            default    => $this->render_tab_areas(),
        };

        $html = <<<HTML
                    <div class="wrap wesanox-booking-tool-admin">
                        <h1>Buchungstool</h1>
                        <p class="mb-2">Hier kannst du Räume verwalten und anlegen</p>
                        {$nav}
                        <div class="tab-content" style="padding:20px; display:flex; flex-direction:column; gap:20px;">
                            {$content}
                        </div>
                        {$area_modal}
                    </div>
                HTML;

        echo $html;
    }

    /**
     * @param array $tabs
     * @param string $active
     * @param string $page
     * @return string
     */
    private function render_tabs_nav(array $tabs, string $active, string $page): string
    {
        $links = [];

        foreach ($tabs as $slug => $label) {
            $args = ['page' => $page];

            if ($slug !== 'areas') {
                $args['tab'] = $slug;
            }

            $url   = esc_url( add_query_arg($args, admin_url('admin.php')) );
            $class = 'nav-tab' . ($slug === $active ? ' nav-tab-active' : '');
            $links[] = sprintf(
                '<a href="%s" class="%s">%s</a>',
                $url,
                esc_attr($class),
                esc_html($label));
        }

        return '<h2 class="nav-tab-wrapper" style="margin-bottom:12px;">' . implode('', $links) . '</h2>';
    }

    /**
     * @return string
     */
    private function render_tab_areas(): string
    {
        return  '
                    <h2>Bereiche verwalten</h2>
                    <div class="d-flex flex-column gap-1">
                        ' . $this->render_area_table() . '
                        <div>
                            <button id="show_area_modal" class="button button-primary">Neuen Bereich anlegen</button>
                        </div>
                    </div>
                ';
    }

    /**
     * @return string
     */
    private function render_tab_openings(): string
    {
        return  '
                    <h2>Öffnungszeiten verwalten</h2>
                    <div class="d-flex flex-column gap-1">
                        ' . $this->render_opening_table() . ' 
                        <div>
                            <button id="show_opening_modal" class="button button-primary">Öffnungszeit hinzufügen</button>
                        </div>
                    </div>
                ';
    }

    private function render_area_table()
    {
        return '
                <table class="iedit author-self level-0 post-1 type-post status-publish format-standard hentry category-Dummy category">
                    <tr>
                        <th scope="row" class="check-column"></th>
                        <th>Bereich</th>
                        <th>Zeiteinheit</th>
                        <th>Gap zwischen den Einheiten</th>
                        <th></th>
                    </tr>
                    ' . $this->render_area_table_row() . '
                </table>';
    }

    private function render_area_table_row()
    {
        $areas = $this->handler->wesanox_get_data($this->table_name);
        $html = '';

        foreach ($areas as $area) {
            $html .= '
                    <tr class="iedit author-self level-0 post-1 type-post status-publish format-standard hentry category-Dummy category">
			            <th scope="row" class="check-column">
			                <input id="cb-select-1" type="checkbox" name="post[]" value="1">
			            </th>
			            <th>
			                ' . $area->area_name . '
			            </th>
			            <th>
			                ' . $area->area_opening . '
			            </th>
			            <th>
			                ' . $area->area_time_settings . '       
			            </th>
                        <th data-id="' . $area->id . '" data-action="area">
                            <button class="button-edit">
                                <span class="dashicons dashicons-edit"></span>
                            </button>
                            <button class="button-delete">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </th>
			        </tr>';
        }

        return $html;
    }

    private function render_area_modal()
    {
        return '
                <div id="add_area_modal" class="modal" style="display:none;">
                    <div class="modal-content-box">
                        <span class="modal-close">&times;</span>
                        <h2>Neuen Bereich hinterlegen</h2>
                        <hr class="my-2">
                        <form id="area" method="post">
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-1">              
                                <label for="area_name">Bereichsname*</label>
                                <input type="text" name="area_name" class="form-control">
                            </div>
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-1">              
                                <label for="area_time_separator">Zeiteinheit*</label>
                                <select type="text" name="area_time_separator" class="form-control">
                                    <option value="1">Minutenweise</option>
                                    <option value="2">Stundenweise</option>
                                </select>
                            </div>
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-1">              
                                <label for="area_time_sheet">Gap zwischen den Einheiten*</label>
                                <input type="text" name="area_time_sheet" class="form-control">
                            </div>
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-1">              
                                <label for="area_time_min">min Buchzeit*</label>
                                <input type="text" name="area_time_min" class="form-control">
                            </div>
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-1">              
                                <label for="area_time_max">max Buchzeit*</label>
                                <input type="text" name="area_time_max" class="form-control">
                            </div> 
                            <hr class="my-2">
                            <div class="d-flex justify-content-end mb-1">
                                <input type="submit" value="Speichern" class="button button-primary">
                            </div>
                            * Pflichtfelder
                        </form>
                    </div>
                </div>
                ';
    }

    private function render_opening_table()
    {
        return '
                <table class="iedit author-self level-0 post-1 type-post status-publish format-standard hentry category-Dummy category">
                    <tr>
                        <th scope="row" class="check-column"></th>
                        <th>Tag</th>
                        <th>Geöffnet von</th>
                        <th>Geöffnet bis</th>
                    </tr>
                </table>';
    }

    private function render_opening_modal()
    {
        return '
                <div id="add_opening_modal" class="modal" style="display:none;">
                    <div class="modal-content-box">
                        <span class="modal-close">&times;</span>
                        <h2>Neue Öffnungszeiten hinterlegen</h2>
                        <hr class="my-2">
                        <form id="opening" method="post">
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                                <label for="openings_day">Öffnungstag*</label>
                                <select name="openings_day" class="form-control">
                                    <option value="1">Montag</option>
                                    <option value="2">Dienstag</option>
                                    <option value="3">Mittwoch</option>
                                    <option value="4">Donnerstag</option>
                                    <option value="5">Freitag</option>
                                    <option value="6">Samstag</option>
                                    <option value="7">Sonntag</option>
                                </select>
                            </div>
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-1">              
                                <label for="opening_from">Geöffnet von*</label>
                                <input type="text" name="opening_from" class="form-control">
                            </div> 
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-1">              
                                <label for="opening_to">Geöffnet bis*</label>
                                <input type="text" name="opening_to" class="form-control">
                            </div> 
                            <hr class="my-2">
                            <div class="d-flex justify-content-end mb-1">
                                <input type="submit" value="Speichern" class="button button-primary">
                            </div>
                            * Pflichtfelder
                        </form>
                    </div>
                </div>
                ';
    }
}

