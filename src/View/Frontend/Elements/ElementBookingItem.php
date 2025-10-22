<?php

namespace Wesanox\Booking\View\Frontend\Elements;

defined( 'ABSPATH' )|| exit;

use WP_Query;

use Wesanox\Booking\Service\ServiceBookingTime;
use Wesanox\Booking\Service\ServiceGetAvailableRoomarts;
use Wesanox\Booking\Service\ServiceGetHolidayPrices;

class ElementBookingItem
{
    protected ServiceBookingTime $service_booking_time;
    protected ServiceGetAvailableRoomarts $service_get_available_roomarts;
    protected ServiceGetHolidayPrices $service_get_holiday_prices;

    public function __construct()
    {
        $this->service_booking_time = new ServiceBookingTime();
        $this->service_get_available_roomarts = new ServiceGetAvailableRoomarts();
        $this->service_get_holiday_prices = new ServiceGetHolidayPrices();

        add_action('wp_ajax_element_booking_item', [$this, 'wesanox_ajax_element_booking_item']);
        add_action('wp_ajax_nopriv_element_booking_item', [$this, 'wesanox_ajax_element_booking_item']);
    }
    public function wesanox_render_element_booking_item(): string
    {
        if ( ! function_exists( 'WC' ) ) {
            return 'Kein Produkt vorhanden';
        }

        $i = 0;

        $suite = array(
            'post_type' => 'product',
            'posts_per_page' => 10,
            'product_cat' => 'wellnessraeume',
        );

        $suite_loop = new WP_Query($suite);

        $html = '
                <div data-hash="room-slide" class="swiper-slide">
                    <div class="row position-relative mx-0 w-100">
                        <div id="room-box" class="col-12 col-xl-5 offset-xl-7 pt-4 pb-5 py-lg-4 px-3 position-relative step">
                            <div id="loading-five" class="position-absolute">
                                <div class="loader"></div>
                            </div>
                            <h4 class="mb-3" data-swiper-parallax="-300" data-swiper-parallax-duration="600">Wähle Deine Suite-Kategorie</h4>
                            <div class="d-flex justify-content-center flex-wrap gap-2">';

                            while ($suite_loop ->have_posts()) :
                                $suite_loop ->the_post();

                                $i++;

                                global $product;

                                $image_id = $product->get_image_id();
                                $image_url = wp_get_attachment_image_src($image_id, 'full')[0];

                                $html .= '<div class="col bg-white mt-2 pb-3 px-0 position-relative">';

                                if ($image_url) {
                                    $html .= '
                                            <div class="w-100 overflow-hidden position-relative" style="height: 200px; max-height: 200px;">
                                                <div id="variation_message_' . $i . '" class="position-absolute w-100 h-100 d-none"></div>
                                                <img class="w-100 position-absolute bottom-0" src="' . esc_url($image_url) . '" alt="' . esc_attr($product->get_name()) . '"/>
                                            </div>';
                                }

                                    $html .= '
                                        <div class="px-2">
                                            <div class="position-absolute top-0 start-0 w-100 h-100 bg-white z-2 d-none settings-box-' . $i . '">
                                                <div class="position-absolute btn-settings-close" data-box="settings-box-' . $i . '">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#fff" class="bi bi-x" viewBox="0 0 16 16">
                                                        <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708"/>
                                                    </svg>
                                                </div>
                                                <div class="text-center mb-3 box-header">
                                                    <strong>
                                                        Ausstattung
                                                    </strong>
                                                    <br>
                                                    ' . $product->get_name() . '
                                                </div>
                                                <div class="px-2">
                                                    ' . $product->get_description() . '
                                                </div>
                                            </div>
                                            <div class="text-center pt-3">
                                            Suite                       
                                                <h2 class="mt-0 text-blue">
                                                    <span>Suite ' . $product->get_name() . '</span>
                                                </h2>
                                                <hr class="w-25 mx-auto mb-3">
                                                <div class="h5">
                                                    <strong>
                                                        <span id="variation_price_' . $i . '"></span> € <br>
                                                        <span class="h6">inkl. MwSt.</span><br>
                                                    </strong>
                                                </div>
                                                pro Person / Stunde
                                            </div>
                                            <div class="d-flex justify-content-between gap-2 mt-3">
                                                <div class="col-6 col-lg">
                                                    <button class="btn btn-primary-outline text-center mt-3 w-100 rounded-0 show-settings" data-box="settings-box-' . $i . '">Ausstattung</button> 
                                                </div>
                                                <div class="col-6 col-lg">
                                                    <button id="variation_btn_' . $i . '" class="btn btn-primary text-center mt-3 w-100 get-product_id">Buchen</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>';
                        endwhile;

                        wp_reset_postdata();

                        $html .= '
                            </div>
                            <div class="w-100 d-flex justify-content-end gap-2">
                                <a href="#how-long-time-slide" class="btn btn-primary d-flex justify-content-between align-items-center back mt-3">
                                    <span><</span> Zurück 
                                </a>
                                <a href="#extras-slide" step="6" value="extras-box" class="btn btn-primary d-flex justify-content-between align-items-center mt-3 add-to-cart-btn forward inactive">
                                    Weiter <span>></span>
                                </a>
                            </div>
                        </div>
                        <div id="price-booking" class="col-12 col-xl-5 offset-xl-7 position-absolute bottom-0 d-none justify-content-center gap-2 text-white py-2">                         
                        </div>
                    </div>
                </div>
        ';

        return $html;
    }

    public function wesanox_ajax_element_booking_item(): void
    {
        $day        = isset($_POST['day'])        ? sanitize_text_field(wp_unslash($_POST['day']))        : '';
        $start_time = isset($_POST['start_time']) ? sanitize_text_field(wp_unslash($_POST['start_time'])) : '';
        $stop_time  = isset($_POST['stop_time'])  ? sanitize_text_field(wp_unslash($_POST['stop_time']))  : '';

        if ($day === '' || $start_time === '' || $stop_time === '') {
            wp_send_json_error(['message' => 'Fehlende Parameter: day, start_time oder stop_time.']);
        }

        $startStr = trim($day . ' ' . $start_time);
        $stopStr  = trim($day . ' ' . $stop_time);

        $tz = wp_timezone();

        try {
            $startDt = new \DateTimeImmutable($startStr, $tz);
            $stopDt  = new \DateTimeImmutable($stopStr,  $tz);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => 'Ungültiges Datum/Zeit-Format', 'detail' => $e->getMessage()]);
        }

        if ($startDt === false || $stopDt === false) {
            wp_send_json_error(['message' => 'Datum konnte nicht geparst werden.']);
        }

        $day_of_week = (int) $startDt->format('w');

        $startYmd = $startDt->format('Y-m-d');
        $startHis = $startDt->format('H:i:s');
        $stopHis  = $stopDt->format('H:i:s');

        $suite_args = [
            'post_type'      => 'product',
            'posts_per_page' => 10,
            'product_cat'    => 'wellnessraeume',
        ];
        $suite_loop = new \WP_Query($suite_args);

        $medi_booking_time_string = json_decode(
            $this->service_booking_time->getBookingTimeDifferenceBetween(
                $startDt->format('Y-m-d H:i:s'),
                $stopDt->format('Y-m-d H:i:s')
            )
        );

        $variation_price_one      = '';
        $variation_id_booking_one = null;
        $available_one            = null;
        $variation_price_two      = '';
        $variation_id_booking_two = null;
        $available_two            = null;

        $opening_holiday = $this->service_get_holiday_prices->wesanox_get_day_price($startYmd);

        while ($suite_loop->have_posts()) :
            $suite_loop->the_post();

            global $product;
            if (!$product instanceof \WC_Product) {
                continue;
            }

            $variations = $product->get_children();

            foreach ($variations as $variation_id) {
                $variation_data = wc_get_product($variation_id);
                if (!$variation_data) {
                    continue;
                }

                if ($day_of_week === 0 || $day_of_week === 6 || $opening_holiday === 1) {
                    if ($variation_data->get_id() === 253) {
                        $variation_price_one      = number_format((float)$variation_data->get_price(), 2, ',', '');
                        $variation_id_booking_one = $variation_data->get_id();
                        $available_one = $this->service_get_available_roomarts->wesanox_roomart_available(
                            1, $startYmd, $startHis, $stopHis
                        );
                    }

                    if ($variation_data->get_id() === 256) {
                        $variation_price_two      = number_format((float)$variation_data->get_price(), 2, ',', '');
                        $variation_id_booking_two = $variation_data->get_id();
                        $available_two = $this->service_get_available_roomarts->wesanox_roomart_available(
                            2, $startYmd, $startHis, $stopHis
                        );
                        break;
                    }
                } else {
                    $vth  = $medi_booking_time_string->vth  ?? 0;
                    $vtmf = $medi_booking_time_string->vtmf ?? 0;
                    $nth  = $medi_booking_time_string->nth  ?? 0;
                    $ntmf = $medi_booking_time_string->ntmf ?? 0;

                    if ( ($vth > 0 || $vtmf > 0) && ($nth > 0 || $ntmf > 0) ) {
                        if ($variation_data->get_id() === 251) {
                            $variation_price_one      = '<small>bis 16:00</small> ' . number_format((float)$variation_data->get_price(), 2, ',', '') . ' €<br>';
                            $variation_id_booking_one = (string)$variation_data->get_id();
                        }
                        if ($variation_data->get_id() === 252) {
                            $variation_price_one      .= '<small>ab 16:00</small> ' . number_format((float)$variation_data->get_price(), 2, ',', '');
                            $variation_id_booking_one .= ',' . (string)$variation_data->get_id();
                            $available_one = $this->service_get_available_roomarts->wesanox_roomart_available(
                                1, $startYmd, $startHis, $stopHis
                            );
                        }
                        if ($variation_data->get_id() === 254) {
                            $variation_price_two      = '<small>bis 16:00</small> ' . number_format((float)$variation_data->get_price(), 2, ',', '') . ' €<br>';
                            $variation_id_booking_two = (string)$variation_data->get_id();
                        }
                        if ($variation_data->get_id() === 255) {
                            $variation_price_two      .= '<small>ab 16:00</small> ' . number_format((float)$variation_data->get_price(), 2, ',', '');
                            $variation_id_booking_two .= ',' . (string)$variation_data->get_id();
                            $available_two = $this->service_get_available_roomarts->wesanox_roomart_available(
                                2, $startYmd, $startHis, $stopHis
                            );
                            break;
                        }
                    } elseif ($vth > 0 || $vtmf > 0) {
                        if ($variation_data->get_id() === 251) {
                            $variation_price_one      = number_format((float)$variation_data->get_price(), 2, ',', '');
                            $variation_id_booking_one = $variation_data->get_id();
                            $available_one = $this->service_get_available_roomarts->wesanox_roomart_available(
                                1, $startYmd, $startHis, $stopHis
                            );
                        }
                        if ($variation_data->get_id() === 254) {
                            $variation_price_two      = number_format((float)$variation_data->get_price(), 2, ',', '');
                            $variation_id_booking_two = $variation_data->get_id();
                            $available_two = $this->service_get_available_roomarts->wesanox_roomart_available(
                                2, $startYmd, $startHis, $stopHis
                            );
                            break;
                        }
                    } elseif ($nth > 0 || $ntmf > 0) {
                        if ($variation_data->get_id() === 252) {
                            $variation_price_one      = number_format((float)$variation_data->get_price(), 2, ',', '');
                            $variation_id_booking_one = $variation_data->get_id();
                            $available_one = $this->service_get_available_roomarts->wesanox_roomart_available(
                                1, $startYmd, $startHis, $stopHis
                            );
                        }
                        if ($variation_data->get_id() === 255) {
                            $variation_price_two      = number_format((float)$variation_data->get_price(), 2, ',', '');
                            $variation_id_booking_two = $variation_data->get_id();
                            $available_two = $this->service_get_available_roomarts->wesanox_roomart_available(
                                2, $startYmd, $startHis, $stopHis
                            );
                        }
                    }
                }
            }
        endwhile;

        wp_reset_postdata();

        wp_send_json([
            'message'              => 'OK',
            'variation_price_one'  => $variation_price_one,
            'variation_id_one'     => $variation_id_booking_one,
            'available_one'        => $available_one,
            'variation_price_two'  => $variation_price_two,
            'variation_id_two'     => $variation_id_booking_two,
            'available_two'        => $available_two,
            'day_of_week'          => $day_of_week,
            'start'                => $startDt->format('Y-m-d H:i:s'),
            'stop'                 => $stopDt->format('Y-m-d H:i:s'),
        ]);
    }
}