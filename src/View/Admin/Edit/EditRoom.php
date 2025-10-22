<?php

namespace Wesanox\Booking\View\Admin\Edit;

defined( 'ABSPATH' )|| exit;

class EditRoom
{
    /**
     * @return void
     */
    public function wesanox_admin_edit_room_render(): void
    {
        $tabs = [
            'rooms'    => 'Räume',
            'roomarts' => 'Raumarten',
            'settings' => 'Einstellungen',
        ];

        $active = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'rooms';

        if (!isset($tabs[$active])) {
            $active = 'rooms';
        }

        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : 'room-settings';

        $nav = $this->render_tabs_nav($tabs, $active, $page);

        $content = match ($active) {
            'settings' => $this->render_tab_settings(),
            'roomarts' => $this->render_tab_roomarts(),
            default    => $this->render_tab_rooms(),
        };

        $html = <<<HTML
                    <div class="wrap medi-booking-tool-admin">
                        <h1>Buchungstool</h1>
                        <p class="mb-2">Hier kannst du Räume verwalten und anlegen</p>
                        {$nav}
                        <div class="tab-content" style="padding:20px; display:flex; flex-direction:column; gap:20px;">
                            {$content}
                        </div>
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

            if ($slug !== 'rooms') {
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
    private function render_tab_rooms(): string
    {
        return  <<<HTML
                    <h2>Räume verwalten</h2>
                    <div>
                        <button id="show_booking_modal" class="button button-primary">Neuer Raum</button>
                    </div>
                HTML;
    }

    /**
     * @return string
     */
    private function render_tab_roomarts(): string
    {
        return  <<<HTML
                    <h2>Raumarten verwalten</h2>
                    <div>
                        <button id="show_booking_modal" class="button button-primary">Neue Raumart</button>
                    </div>
                HTML;
    }

    /**
     * @return string
     */
    private function render_tab_settings(): string
    {
        return  <<<HTML
                    <h2>Einstellungen</h2>
                HTML;
    }
}