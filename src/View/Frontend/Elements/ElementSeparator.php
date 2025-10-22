<?php

namespace Wesanox\Booking\View\Frontend\Elements;

defined( 'ABSPATH' )|| exit;

class ElementSeparator
{
    /**
     * @return string
     */
    public function wesanox_render_frontend_separator(): string
    {
        return '<hr>';
    }
}