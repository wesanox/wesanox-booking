<?php

namespace Wesanox\Booking\View\Frontend\Elements;

defined( 'ABSPATH' )|| exit;

use WP_Query;

use Wesanox\Booking\Application\Booking\FindNextAvailableSlotService;
use Wesanox\Booking\Application\Rate\FindRateForBookingService;
use Wesanox\Booking\Domain\Rate\Rate;
use Wesanox\Booking\Infrastructure\Holiday\WordPressHolidayRepository;
use Wesanox\Booking\Infrastructure\Rate\WordPressRateRepository;
use Wesanox\Booking\Service\ServiceBookingTime;
use Wesanox\Booking\Service\ServiceGetAvailableRoomarts;
use Wesanox\Booking\Service\ServiceGetHolidayPrices;
use Wesanox\Booking\Service\ServiceGetNextAvailableRoomarts;

class ElementBookingItem
{
    protected ServiceBookingTime $service_booking_time;
    protected ServiceGetAvailableRoomarts $service_get_available_roomarts;
    protected ServiceGetHolidayPrices $service_get_holiday_prices;
    protected FindRateForBookingService $rate_service;
    protected FindNextAvailableSlotService $next_slot_service;

    public function __construct()
    {
        $this->service_booking_time           = new ServiceBookingTime();
        $this->service_get_available_roomarts = new ServiceGetAvailableRoomarts();
        $this->service_get_holiday_prices     = new ServiceGetHolidayPrices();
        $this->rate_service                   = new FindRateForBookingService(
            new WordPressRateRepository(),
            new WordPressHolidayRepository()
        );
        $this->next_slot_service              = new FindNextAvailableSlotService(
            new ServiceGetNextAvailableRoomarts()
        );

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
            return;
        }

        $startStr = trim($day . ' ' . $start_time);
        $stopStr  = trim($day . ' ' . $stop_time);
        $tz       = wp_timezone();

        try {
            $startDt = new \DateTimeImmutable($startStr, $tz);
            $stopDt  = new \DateTimeImmutable($stopStr,  $tz);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => 'Ungültiges Datum/Zeit-Format', 'detail' => $e->getMessage()]);
            return;
        }

        $day_of_week = (int) $startDt->format('w');
        $startYmd    = $startDt->format('Y-m-d');
        $startHis    = $startDt->format('H:i:s');
        $stopHis     = $stopDt->format('H:i:s');
        $start_hi    = substr($startHis, 0, 5); // HH:MM
        $stop_hi     = substr($stopHis,  0, 5);

        // Calculate how much of the booking falls before and after 16:00.
        $booking_time = json_decode(
            $this->service_booking_time->getBookingTimeDifferenceBetween(
                $startDt->format('Y-m-d H:i:s'),
                $stopDt->format('Y-m-d H:i:s')
            )
        );
        $has_vt = ($booking_time->vth ?? 0) > 0 || ($booking_time->vtmf ?? 0) > 0;
        $has_nt = ($booking_time->nth ?? 0) > 0 || ($booking_time->ntmf ?? 0) > 0;

        // ── Rate table lookup ────────────────────────────────────────────────────
        // Try to find rates via the rates table (area_id 1 = Suite 1, area_id 2 = Suite 2).
        // For bookings spanning 16:00, look up VT (before) and NT (after) segments separately.
        if ($has_vt && $has_nt) {
            $rate_1_vt = $this->rate_service->execute(1, 0, $start_hi, '16:00', $startYmd);
            $rate_1_nt = $this->rate_service->execute(1, 0, '16:00',   $stop_hi, $startYmd);
            $rate_2_vt = $this->rate_service->execute(2, 0, $start_hi, '16:00', $startYmd);
            $rate_2_nt = $this->rate_service->execute(2, 0, '16:00',   $stop_hi, $startYmd);
        } elseif ($has_vt) {
            $rate_1_vt = $this->rate_service->execute(1, 0, $start_hi, $stop_hi, $startYmd);
            $rate_1_nt = null;
            $rate_2_vt = $this->rate_service->execute(2, 0, $start_hi, $stop_hi, $startYmd);
            $rate_2_nt = null;
        } else {
            $rate_1_vt = null;
            $rate_1_nt = $this->rate_service->execute(1, 0, $start_hi, $stop_hi, $startYmd);
            $rate_2_vt = null;
            $rate_2_nt = $this->rate_service->execute(2, 0, $start_hi, $stop_hi, $startYmd);
        }

        $variation_price_one      = '';
        $variation_id_booking_one = null;
        $available_one            = null;
        $variation_price_two      = '';
        $variation_id_booking_two = null;
        $available_two            = null;

        // If rates were found in the table, use them.
        $rates_found = ($rate_1_vt || $rate_1_nt || $rate_2_vt || $rate_2_nt);

        if ($rates_found) {
            [$variation_price_one, $variation_id_booking_one] = $this->buildVariationData(
                $rate_1_vt, $rate_1_nt, $has_vt && $has_nt
            );
            if ($variation_id_booking_one !== null) {
                $available_one = $this->service_get_available_roomarts->wesanox_roomart_available(
                    1, $startYmd, $startHis, $stopHis
                );
            }

            [$variation_price_two, $variation_id_booking_two] = $this->buildVariationData(
                $rate_2_vt, $rate_2_nt, $has_vt && $has_nt
            );
            if ($variation_id_booking_two !== null) {
                $available_two = $this->service_get_available_roomarts->wesanox_roomart_available(
                    2, $startYmd, $startHis, $stopHis
                );
            }
        } else {
            // ── Fallback: hardcoded variation IDs ───────────────────────────────
            // Used when no rates are configured in the rates table.
            $opening_holiday = $this->service_get_holiday_prices->wesanox_get_day_price($startYmd);

            $suite_args = [
                'post_type'      => 'product',
                'posts_per_page' => 10,
                'product_cat'    => 'wellnessraeume',
            ];
            $suite_loop = new \WP_Query($suite_args);

            $vth  = $booking_time->vth  ?? 0;
            $vtmf = $booking_time->vtmf ?? 0;
            $nth  = $booking_time->nth  ?? 0;
            $ntmf = $booking_time->ntmf ?? 0;

            // Sat/Sun: full day weekend rate.
            // Fri + Holidays: before 16:00 = weekday rate, from 16:00 = weekend/holiday rate.
            $is_full_weekend = ($day_of_week === 0 || $day_of_week === 6);
            $is_mixed_day    = ($day_of_week === 5 || $opening_holiday === 1);

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

                    $vid = $variation_data->get_id();

                    if ($is_full_weekend) {
                        // Saturday / Sunday: entire booking at weekend rate.
                        if ($vid === 253) {
                            $variation_price_one      = number_format((float)$variation_data->get_price(), 2, ',', '');
                            $variation_id_booking_one = $vid;
                            $available_one = $this->service_get_available_roomarts->wesanox_roomart_available(
                                1, $startYmd, $startHis, $stopHis
                            );
                        }
                        if ($vid === 256) {
                            $variation_price_two      = number_format((float)$variation_data->get_price(), 2, ',', '');
                            $variation_id_booking_two = $vid;
                            $available_two = $this->service_get_available_roomarts->wesanox_roomart_available(
                                2, $startYmd, $startHis, $stopHis
                            );
                            break;
                        }
                    } elseif ($is_mixed_day) {
                        // Friday / Holiday: before 16:00 = weekday (251/254), from 16:00 = weekend (253/256).
                        if ( ($vth > 0 || $vtmf > 0) && ($nth > 0 || $ntmf > 0) ) {
                            // Spans 16:00: VT weekday + NT weekend
                            if ($vid === 251) {
                                $variation_price_one      = '<small>bis 16:00</small> ' . number_format((float)$variation_data->get_price(), 2, ',', '') . ' €<br>';
                                $variation_id_booking_one = (string)$vid;
                            }
                            if ($vid === 253) {
                                $variation_price_one      .= '<small>ab 16:00</small> ' . number_format((float)$variation_data->get_price(), 2, ',', '');
                                $variation_id_booking_one .= ',' . (string)$vid;
                                $available_one = $this->service_get_available_roomarts->wesanox_roomart_available(
                                    1, $startYmd, $startHis, $stopHis
                                );
                            }
                            if ($vid === 254) {
                                $variation_price_two      = '<small>bis 16:00</small> ' . number_format((float)$variation_data->get_price(), 2, ',', '') . ' €<br>';
                                $variation_id_booking_two = (string)$vid;
                            }
                            if ($vid === 256) {
                                $variation_price_two      .= '<small>ab 16:00</small> ' . number_format((float)$variation_data->get_price(), 2, ',', '');
                                $variation_id_booking_two .= ',' . (string)$vid;
                                $available_two = $this->service_get_available_roomarts->wesanox_roomart_available(
                                    2, $startYmd, $startHis, $stopHis
                                );
                                break;
                            }
                        } elseif ($vth > 0 || $vtmf > 0) {
                            // Only before 16:00: weekday rate
                            if ($vid === 251) {
                                $variation_price_one      = number_format((float)$variation_data->get_price(), 2, ',', '');
                                $variation_id_booking_one = $vid;
                                $available_one = $this->service_get_available_roomarts->wesanox_roomart_available(
                                    1, $startYmd, $startHis, $stopHis
                                );
                            }
                            if ($vid === 254) {
                                $variation_price_two      = number_format((float)$variation_data->get_price(), 2, ',', '');
                                $variation_id_booking_two = $vid;
                                $available_two = $this->service_get_available_roomarts->wesanox_roomart_available(
                                    2, $startYmd, $startHis, $stopHis
                                );
                                break;
                            }
                        } elseif ($nth > 0 || $ntmf > 0) {
                            // Only from 16:00: weekend/holiday rate
                            if ($vid === 253) {
                                $variation_price_one      = number_format((float)$variation_data->get_price(), 2, ',', '');
                                $variation_id_booking_one = $vid;
                                $available_one = $this->service_get_available_roomarts->wesanox_roomart_available(
                                    1, $startYmd, $startHis, $stopHis
                                );
                            }
                            if ($vid === 256) {
                                $variation_price_two      = number_format((float)$variation_data->get_price(), 2, ',', '');
                                $variation_id_booking_two = $vid;
                                $available_two = $this->service_get_available_roomarts->wesanox_roomart_available(
                                    2, $startYmd, $startHis, $stopHis
                                );
                                break;
                            }
                        }
                    } else {
                        // Mon-Thu: VT/NT split at 16:00 with weekday rates.
                        if ( ($vth > 0 || $vtmf > 0) && ($nth > 0 || $ntmf > 0) ) {
                            if ($vid === 251) {
                                $variation_price_one      = '<small>bis 16:00</small> ' . number_format((float)$variation_data->get_price(), 2, ',', '') . ' €<br>';
                                $variation_id_booking_one = (string)$vid;
                            }
                            if ($vid === 252) {
                                $variation_price_one      .= '<small>ab 16:00</small> ' . number_format((float)$variation_data->get_price(), 2, ',', '');
                                $variation_id_booking_one .= ',' . (string)$vid;
                                $available_one = $this->service_get_available_roomarts->wesanox_roomart_available(
                                    1, $startYmd, $startHis, $stopHis
                                );
                            }
                            if ($vid === 254) {
                                $variation_price_two      = '<small>bis 16:00</small> ' . number_format((float)$variation_data->get_price(), 2, ',', '') . ' €<br>';
                                $variation_id_booking_two = (string)$vid;
                            }
                            if ($vid === 255) {
                                $variation_price_two      .= '<small>ab 16:00</small> ' . number_format((float)$variation_data->get_price(), 2, ',', '');
                                $variation_id_booking_two .= ',' . (string)$vid;
                                $available_two = $this->service_get_available_roomarts->wesanox_roomart_available(
                                    2, $startYmd, $startHis, $stopHis
                                );
                                break;
                            }
                        } elseif ($vth > 0 || $vtmf > 0) {
                            if ($vid === 251) {
                                $variation_price_one      = number_format((float)$variation_data->get_price(), 2, ',', '');
                                $variation_id_booking_one = $vid;
                                $available_one = $this->service_get_available_roomarts->wesanox_roomart_available(
                                    1, $startYmd, $startHis, $stopHis
                                );
                            }
                            if ($vid === 254) {
                                $variation_price_two      = number_format((float)$variation_data->get_price(), 2, ',', '');
                                $variation_id_booking_two = $vid;
                                $available_two = $this->service_get_available_roomarts->wesanox_roomart_available(
                                    2, $startYmd, $startHis, $stopHis
                                );
                                break;
                            }
                        } elseif ($nth > 0 || $ntmf > 0) {
                            if ($vid === 252) {
                                $variation_price_one      = number_format((float)$variation_data->get_price(), 2, ',', '');
                                $variation_id_booking_one = $vid;
                                $available_one = $this->service_get_available_roomarts->wesanox_roomart_available(
                                    1, $startYmd, $startHis, $stopHis
                                );
                            }
                            if ($vid === 255) {
                                $variation_price_two      = number_format((float)$variation_data->get_price(), 2, ',', '');
                                $variation_id_booking_two = $vid;
                                $available_two = $this->service_get_available_roomarts->wesanox_roomart_available(
                                    2, $startYmd, $startHis, $stopHis
                                );
                            }
                        }
                    }
                }
            endwhile;

            wp_reset_postdata();
        }

        // ── Next available slots when a room is not bookable ────────────────
        $next_available_one = [];
        $next_available_two = [];
        $durationMinutes    = (int) round(($stopDt->getTimestamp() - $startDt->getTimestamp()) / 60);

        if (!$available_one) {
            $next_available_one = $this->next_slot_service->execute(
                1,
                $startDt->format('Y-m-d H:i:s'),
                $durationMinutes,
                3
            );
        }

        if (!$available_two) {
            $next_available_two = $this->next_slot_service->execute(
                2,
                $startDt->format('Y-m-d H:i:s'),
                $durationMinutes,
                3
            );
        }

        wp_send_json([
            'message'              => 'OK',
            'variation_price_one'  => $variation_price_one,
            'variation_id_one'     => $variation_id_booking_one,
            'available_one'        => $available_one,
            'next_available_one'   => $next_available_one,
            'variation_price_two'  => $variation_price_two,
            'variation_id_two'     => $variation_id_booking_two,
            'available_two'        => $available_two,
            'next_available_two'   => $next_available_two,
            'day_of_week'          => $day_of_week,
            'start'                => $startDt->format('Y-m-d H:i:s'),
            'stop'                 => $stopDt->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Build price string and variation/product ID from a pair of rates (VT before 16:00, NT after 16:00).
     *
     * @return array{string, int|string|null}  [price_html, variation_id_or_null]
     */
    private function buildVariationData(?Rate $rate_vt, ?Rate $rate_nt, bool $spans_cutoff): array
    {
        if ($spans_cutoff && $rate_vt !== null && $rate_nt !== null) {
            $vt_id   = $rate_vt->wc_variation_id ?? $rate_vt->wc_product_id;
            $nt_id   = $rate_nt->wc_variation_id ?? $rate_nt->wc_product_id;
            $vt_prod = wc_get_product($vt_id);
            $nt_prod = wc_get_product($nt_id);

            if ($vt_prod && $nt_prod) {
                $price = '<small>bis 16:00</small> '
                       . number_format((float) $vt_prod->get_price(), 2, ',', '') . ' €<br>'
                       . '<small>ab 16:00</small> '
                       . number_format((float) $nt_prod->get_price(), 2, ',', '');
                return [$price, $vt_id . ',' . $nt_id];
            }

            return ['', null];
        }

        $rate = $rate_vt ?? $rate_nt;
        if ($rate === null) {
            return ['', null];
        }

        $id   = $rate->wc_variation_id ?? $rate->wc_product_id;
        $prod = wc_get_product($id);

        if (!$prod) {
            return ['', null];
        }

        return [number_format((float) $prod->get_price(), 2, ',', ''), $id];
    }
}
