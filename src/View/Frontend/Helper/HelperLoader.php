<?php

namespace Wesanox\Booking\View\Frontend\Helper;

defined( 'ABSPATH' )|| exit;

class HelperLoader
{
    public function wesanox_render_frontend_loader(): string
    {
        return '
            <div id="loading" class="position-absolute" style="display: none;">
                <div class="loader"></div>
            </div>';
    }
}
