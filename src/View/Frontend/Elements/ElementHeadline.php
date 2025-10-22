<?php

namespace Wesanox\Booking\View\Frontend\Elements;

defined( 'ABSPATH' )|| exit;

class ElementHeadline
{
    /**
     * Render the headline
     *
     * @param string $headline
     * @param $tags
     * @param string $styleClass
     * @param string $align
     * @return string
     */
    public function wesanox_render_frontend_headline(string $headline, $tags, string $styleClass, string $align = ''): string
    {
        return $this->get_headline($headline, $tags, $styleClass, $align);
    }

    /**
     * Get the headline with tags, css classes and align
     *
     * @param string $headline
     * @param $tags
     * @param string $styleClass
     * @param string $align
     *
     * @return string
     */
    private function get_headline(string $headline, $tags, string $styleClass, string $align = ''): string
    {
        $tag = $this->get_tag($tags);
        $styleClass = $this->get_style_class($styleClass, $align);

        return ($headline) ? '<' . $tag . $styleClass . '>' . $headline . '</' . $tag . '>' : '';
    }

    /**
     * Get the html headline tag
     *
     * @param $tags
     *
     * @return string
     */
    private function get_tag($tags): string
    {
        switch ($tags) {
            case "1":
                $tag = "h1";
                break;
            case "2":
                $tag = "h2";
                break;
            case "3":
                $tag = "h3";
                break;
            case "4":
                $tag = "h4";
                break;
            case "5":
                $tag = "h5";
                break;
            case "6":
                $tag = "h6";
                break;
            case "7":
                $tag = "p";
                break;
            default:
                $tag = "div";
                break;
        }

        return $tag;
    }

    /**
     * Get the classes to style the headline
     *
     * @param string $styleClass
     * @param string $align
     *
     * @return string
     */
    private function get_style_class(string $styleClass, string $align): string
    {
        $align = $this->get_text_align($align);

        switch (true) {
            case ($styleClass != '' && $align != '') :
                $css_class = ' class="' . $styleClass . $align . '"';
                break;
            case ($styleClass != '') :
                $css_class = ' class="' . $styleClass . '"';
                break;
            case ($align != '') :
                $css_class = ' class="' . $align . '"';
                break;
            default :
                $css_class = '';
        }

        return $css_class;
    }

    /**
     * Get the CSS class for text alignment based on the alignment code.
     *
     * @param string $align The alignment code ( 1 = start, 2 = center, 3 = end )
     *
     * @return string
     */
    private function get_text_align(string $align): string
    {
        switch ($align) {
            case "1":
                $text_align = " text-start";
                break;
            case "2":
                $text_align = " text-center";
                break;
            case "3":
                $text_align = " text-end";
                break;
            default:
                $text_align = "";
        }

        return $text_align;
    }
}