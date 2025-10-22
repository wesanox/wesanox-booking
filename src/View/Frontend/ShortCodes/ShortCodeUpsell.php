<?php

namespace Wesanox\Booking\View\Frontend\ShortCodes;

defined( 'ABSPATH' )|| exit;

use WC_Cart;
use WP_Query;

class ShortCodeUpsell
{
    public function __construct()
    {
        add_shortcode('wesanox-booking-upsell', [$this, 'wesanox_booking_upsell_shortcode']);

        add_action('wp_ajax_wesanox_upsell_to_card', [$this, 'wesanox_upsell_to_card']);
        add_action('wp_ajax_nopriv_wesanox_upsell_to_card', [$this, 'wesanox_upsell_to_card']);
    }

    public function wesanox_booking_upsell_shortcode()
    {
        if ( ! function_exists( 'WC' ) ) {
            wp_die();
        }

        if ( ( is_admin() || ( defined('REST_REQUEST') && REST_REQUEST ) ) && ! wp_doing_ajax() ) {
            return '';
        }

        $cart = WC()->cart ?? null;

        if ( ! $cart instanceof WC_Cart ) {
            return '';
        }

        $booking = ( function_exists('WC') && WC()->session ) ? (array) WC()->session->get('booking', []) : [];

        $person     = $booking['person_count'] ?? '';
        $duration   = $booking['how_long'] ?? '';

        if( $cart->get_cart_contents_count() != 0 && $person != '' && $duration  != '') {
            $html       = '';
            $i          = 0;
            $cart_items = $cart->get_cart();

            $extra = array(
                'post_type' => 'product',
                'posts_per_page' => 10,
                'tax_query' => array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field'    => 'slug',
                        'terms'    => 'versicherungen',
                    ),
                ),
            );

            $extra_loop = new WP_Query($extra);

            while ($extra_loop->have_posts()) :
                $extra_loop->the_post();
                $i++;

                global $product;

                $variations = $product->get_children();

                foreach ($variations as $variation_id) {
                    $variation_data = wc_get_product($variation_id);

                    foreach ($cart_items as $item) {
                        if ( $item['variation_id'] == 251 || $item['variation_id'] == 252 || $item['variation_id'] == 253 || $item['variation_id'] == 254 || $item['variation_id'] == 255 || $item['variation_id'] == 256 ) {
                            $product_id = $item['variation_id'];
                        }

                        $active = ( $item['variation_id'] == 1899 || $item['variation_id'] == 1900 || $item['variation_id'] == 1901 || $item['variation_id'] == 1902 || $item['variation_id'] == 1903 || $item['variation_id'] == 1904 ) ? ' active' : '';
                    }

                    $quant = $person * $duration;

                    switch (true) {
                        case $product_id === 251 && $variation_id === 1899:
                            $variation = 1899;
                            $price = $variation_data->get_price() * $quant;
                            break;
                        case $product_id === 252 && $variation_id === 1900:
                            $variation = 1900;
                            $price = $variation_data->get_price() * $quant;
                            break;
                        case $product_id === 253 && $variation_id === 1901:
                            $variation = 1901;
                            $price = $variation_data->get_price() * $quant;
                            break;
                        case $product_id === 254 && $variation_id === 1902:
                            $variation = 1902;
                            $price = $variation_data->get_price() * $quant;
                            break;
                        case $product_id === 255 && $variation_id === 1903:
                            $variation = 1903;
                            $price = $variation_data->get_price() * $quant;
                            break;
                        case $product_id === 256 && $variation_id === 1904:
                            $variation = 1904;
                            $price = $variation_data->get_price() * $quant;
                            break;
                        default:
                            $variation = '';
                            $price = '';
                    }

                    if ( $variation_id === $variation ) {
                        $html .= '
                        <div id="cart-upsell-box">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <h2 class="text-blue">
                                        Unsere Services
                                    </h2>
                                </div>
                                <div class="col-12 col-md-1 p-2 d-none d-md-block">
                                    <div class="checkbox-extras' . $active . '" data-product_id="' . $variation . '" data-quant="' . $quant . '">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" fill="currentColor" class="bi bi-check2" viewBox="0 0 16 16">
                                            <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="col-12 col-md-11">
                                    <h2 class="mt-0 h5">
                                        BETTER BOOK IT, BECAUSE YOU NEVER KNOW!
                                    </h2>
                                    ' . $product->get_description() . '
                                    <div class="h5 mt-4">
                                        Preis für Deine Absicherung: <strong>' . number_format($price, 2, ',', '') . ' €</strong><br>
                                        <small>inkl. MwSt.</small>
                                    </div>
                                    <button class="btn btn-primary d-block d-md-none w-100 text-center mt-5 checkbox-extras" data-product_id="' . $variation . '" data-quant="' . $quant . '">
                                        <strong>Jetzt hinzufügen</strong>
                                    </button>
                                </div>
                            </div>
                        </div>';
                    }
                }

                if($extra_loop->found_posts != $i) {
                    $html .= '<hr class="w-25 mx-auto my-5">';
                }
            endwhile;

            wp_reset_postdata();

            return $html;
        }
    }

    /**
     * Get Upsell products to card
     *
     * @return void
     * @throws Exception
     */
    function wesanox_upsell_to_card(): void
    {
        $product_id = intval($_POST['product_id']);
        $quant      = intval($_POST['quant']);

        if ( $product_id ) {
            WC()->cart->add_to_cart($product_id, $quant);
            echo 'success';
        }

        wp_die();
    }
}