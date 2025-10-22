<?php

namespace Wesanox\Booking\View\Frontend\Elements;

defined( 'ABSPATH' )|| exit;

use WP_Query;

class ElementBookingExtras
{
    public function __construct()
    {
        add_action('wp_ajax_element_booking_extras', [$this, 'wesanox_ajax_element_booking_extras']);
        add_action('wp_ajax_nopriv_element_booking_extras', [$this, 'wesanox_ajax_element_booking_extras']);
    }

    public function wesanox_render_element_booking_extras()
    {
        if (!function_exists('WC')) {
            return;
        }

        $html           = '';
        $html_cat       = '';
        $html_img       = '';
        $html_product   = '';

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

        while ($extra_loop->have_posts()) {
            $extra_loop->the_post();

            global $product;

            $booking = ( function_exists('WC') && WC()->session ) ? WC()->session->get('booking', []) : [];

            $extras = (isset($booking['extras']) && !is_array($booking['extras'])) ? json_decode($booking['extras']) : '';
            $product_id = $product->get_id();

            if(is_array($extras)) {
                foreach ($extras as $select) {
                    if($product_id == $select->product_id) {
                        $css_class = ' active';
                        $modal_content = ' data-bs-target="#product_modal_' . $product_id . '"';
                    } else {
                        $css_class = '';
                        $modal_content = ' data-bs-toggle="modal" data-bs-target="#product_modal_' . $product_id . '"';
                    }
                }
            } else {
                $css_class = '';
                $modal_content = ' data-bs-toggle="modal" data-bs-target="#product_modal_' . $product_id . '"';
            }

            $image_id = $product->get_image_id();
            $image_url = wp_get_attachment_image_src($image_id, 'full')[0];

            $terms = get_the_terms($product->get_id(), 'product_cat');

            $person_content = ( $product_id != 95 && $product_id != 96) ? ' pro Person': ' für 2 Personen';

            if (!empty($terms) && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    if ($term->parent == 0) {
                        $html_cat = '<span class="main-category">' . str_replace('Extras', 'Wellnesspaket', $term->name) . '</span>';
                        break;
                    }
                }
            }

            if ($image_url) {
                $html_img = '
                    <div class="w-100 overflow-hidden position-relative rounded-circle mx-auto my-5" style="max-width: 250px; height: 250px; max-height: 250px;">
                        <img class="w-100" src="' . esc_url($image_url) . '" alt="' . esc_attr($product->get_name()) . '"/>
                    </div>';
            }

            $html_product .= '
                <div id="extra-box-select-' . $product_id . '" class="position-relative col-12 col-lg-6 col-xl-4 mt-2 pb-3 px-1 extra-box-select' . $css_class . '"' . $modal_content . '  value-product="' . $product_id . '" data-swiper-parallax="-300" data-swiper-parallax-duration="600">
                    <div class="bg-white px-2 py-3 h-100">
                        <div class="text-center">
                            <div class="row px-2">
                                <div class="col-9 col-lg-10 text-start">
                                    ' . $html_cat . '
                                    <h2 class="my-0">
                                       <span>' . $product->get_name() . '</span>
                                    </h2>
                                </div>
                                <div class="col-3 col-lg-2 d-flex justify-content-end align-items-center">
                                    <div class="extra-box-select__circle d-flex justify-content-center align-items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="currentColor" class="bi bi-check2" viewBox="0 0 16 16">
                                            <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            ' . $html_img . '
                            <div class="px-2">' . $product->get_description() . '</div><hr class="w-25 mx-auto mb-3">
                                <div class="h5">
                                    <strong>
                                        ' . number_format($product->get_price(), 2, ',', '') . ' €*<br>
                                        <small>' . $person_content . '</small><br>
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </div>';
        }

        $html .= '
                <div data-hash="extras-slide" class="swiper-slide">
                    <div class="row position-relative mx-0">
                        <div id="extras-box" class="col-12 pt-4 pb-5 py-lg-4 px-3 step">
                            <h4 class="mb-3" data-swiper-parallax="-300" data-swiper-parallax-duration="600">Wähle Deine Extras</h4>
                            <div class="row px-2"> 
                                ' . $html_product . ' 
                               
                                <div class="w-100 d-flex justify-content-end gap-2">
                                    <a href="#room-slide" class="btn btn-primary d-flex justify-content-between align-items-center back mt-3">
                                        <span><</span> Zurück 
                                    </a>
                                    <a href="' .  esc_url(wc_get_cart_url()) . '" class="btn btn-primary d-flex justify-content-between align-items-center forward mt-3">
                                        Weiter <span>></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div id="price-variation" class="w-100 d-none position-absolute bottom-0 justify-content-center text-white py-2">
                        </div>';

        wp_reset_postdata();

        return $html;
    }

    public function wesanox_ajax_element_booking_extras(): void
    {
        $product_id = intval(sanitize_text_field($_POST['product_id']));
        $person_count = intval(sanitize_text_field($_POST['person_count']));
        $variation  = wc_get_product($product_id);

        if (!$variation ) {
            wp_send_json_error('Product not found' . $product_id);
            exit;
        }

        $attributes = $variation->get_attributes();

        if (!empty($attributes)) {
            $html_attr = '';

            if( get_post_meta($product_id, '_veen_spa_option_1', true) != '2' ) {
                foreach ($attributes as $attribute) {
                    $html_attr .= '<select class="product_attributes">';

                    foreach ($attribute->get_options() as $attr) {
                        $html_attr .= '<option value="' . esc_html($attr) . '">' . esc_html($attr) . '</option>';
                    }

                    $html_attr .= '</select>';
                }

                $j = 1;

                for ($i = 0; $i < $person_count; $i++) {
                    $html .= '
                        <div class="row my-3">
                            <div class="col-4 d-flex align-items-center">' . $j . ' Person : </div>
                            <div class="col-8">' . $html_attr . '</div>
                        </div>';

                    $j++;
                }
            } else {
                foreach ($attributes as $attribute) {

                    foreach ($attribute->get_options() as $key => $attr) {
                        $checked = ($key == 0) ? '  checked' : '';

                        $html .= '
                        <div class="form-check d-flex align-items-center gap-2 mb-2 h5">
                            <input class="form-check-input" type="radio" name="attribute_radio_' . $product_id . '" value="' . esc_html($attr) . '"' . $checked . '>
                            <label class="form-check-label" for="radio_' . esc_html($attr) . '">
                                ' . esc_html($attr) . '
                            </label>
                        </div>';
                    }
                }
            }
        }


        $response = array(
            'message' => 'AJAX-Request erfolgreich abgefangen',
            'html'    => $html,
        );

        wp_send_json($response);
    }
}