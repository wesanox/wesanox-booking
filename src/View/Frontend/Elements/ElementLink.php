<?php

namespace Wesanox\Booking\View\Frontend\Elements;

defined( 'ABSPATH' )|| exit;

class ElementLink
{
    /**
     * @param $link
     * @param $text
     * @param $aria
     * @return string
     */
    public function wesanox_render_frontend_link($link, $text, $aria = ''): string
    {
        return '<a href="' . $link . '" title="' . $aria . '" aria-label="' . $aria . '">' . $text . '</a>';
    }
}