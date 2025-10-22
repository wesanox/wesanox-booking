<?php

namespace Wesanox\Booking\View\Frontend\Elements;

defined( 'ABSPATH' )|| exit;

use WP_Query;

class ElementBookingModal
{
    public function __construct()
    {

    }

    public function wesanox_render_element_booking_modal()
    {
        if (!function_exists('WC')) {
            return;
        }

        $person = ( isset ( $_SESSION['person'] ) ) ? $_SESSION['person'] : '';

        $extra = array(
            'post_type' => 'product',
            'posts_per_page' => 10,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'slug',
                    'terms' => array('extras', 'vorbestellung'),
                ),
            ),
        );

        $extra_loop = new WP_Query($extra);

        $html       = '';

        while ($extra_loop->have_posts()) {
            $extra_loop->the_post();

            global $product;

            $image_id = $product->get_image_id();
            $image_url = wp_get_attachment_image_src($image_id, 'full')[0];

            $product_id = $product->get_id();

            $content_person = ( get_post_meta($product_id, '_veen_spa_option_1', true) === '2' ) ? '2 Personen' : 'Person';

            $html.= '
                <div class="modal fade" id="product_modal_' . $product_id . '" tabindex="-1" aria-labelledby="product_modal_' . $product_id . '_label" aria-hidden="true" style="background-color: rgba(0,0,0,.4);">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content z-1">
                            <div class="modal-header border-0 position-relative">
                                <div class="w-100 mb-3 position-absolute image-box__var">
                                    <img src="' . htmlspecialchars($image_url) . '" alt="' . esc_attr($product->get_name()) . '">
                                </div>
                                <button type="button" class="position-absolute border-0 rounded-0 modal-close" data-bs-dismiss="modal" aria-label="Close">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" fill="currentColor" class="bi bi-x" viewBox="0 0 16 16">
                                        <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708"/>
                                    </svg>                             
                                </button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-12 mb-5">
                                                
                                                <div class="h4">
                                                    ' . $product->get_name() . '
                                                </div>
                                                <div class="h6">
                                                    Preis: ' . number_format($product->get_price(), 2, ',', '') . ' € / ' .  $content_person . ' / inkl. MwSt.
                                                </div>
                                                <div class="mb-5">
                                                    ' . $product->get_description() . '
                                                </div>';

                                                if( get_post_meta($product_id, '_veen_spa_option_1', true) === '3' ) {
                                                    $value =  ( $person != '' ) ? $person : 2;
                                                    $html.= '
                                                        <div class="d-flex justify-content-between my-5 modal-price">
                                                            <div class="button-person minus">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor" class="bi bi-dash" viewBox="0 0 16 16">
                                                                    <path d="M4 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7A.5.5 0 0 1 4 8"/>
                                                                </svg>
                                                            </div>
                                                            <div class="position-relative">
                                                                <span class="position-absolute person-count" value="' . $value . '">
                                                                    ' . $value . '
                                                                </span>
                                                            </div>
                                                            <div class="button-person plus inactive">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor" class="bi bi-plus" viewBox="0 0 16 16">
                                                                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
                                                                </svg>
                                                            </div>
                                                        </div>';
                                                    }

                                                    $html.= '
                                                <div id="modal-extra-box_' . $product->get_id() . '">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-primary add-var-to-cart-btn" data-bs-dismiss="modal"  data-variation_id="' . $product->get_id() . '">wählen</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>';

        }

        wp_reset_postdata();

        return $html;
    }
}