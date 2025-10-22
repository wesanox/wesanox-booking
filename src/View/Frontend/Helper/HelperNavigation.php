<?php

namespace Wesanox\Booking\View\Frontend\Helper;

defined( 'ABSPATH' )|| exit;

class HelperNavigation
{
    public function wesanox_render_frontend_navigation(): string
    {
        $booking    = ( function_exists('WC') && WC()->session ) ? (array) WC()->session->get('booking', []) : [];

        $start = ( !empty($booking['day']) && !empty($booking['start_time']) ) ? date('d.m.Y', strtotime($booking['day'])) . ' ' . $booking['start_time'] : '';
        $person = ( !empty($booking['person_count']) ) ? $booking['person_count'] : '';
        $duration = ( !empty($booking['how_long']) ) ? $booking['how_long'] : '';

        $cart_contents_count = (WC()->cart) ? WC()->cart->get_cart_contents_count() : '';

        $html_button = '
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check2" viewBox="0 0 16 16">
                <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0"/>
            </svg>';

        $html = '
            <div id="message" class="position-fixed top-0 start-0 end-0 alert alert-success d-none text-center z-3"></div>
            <div class="swiper nav-swiper">
                <div class="swiper-wrapper pb-3">
                    ';

        if ( $person != '' ) {
            $count_text = ( $person > 1 ) ? $person . ' Personen' : $person . ' Person';

            $html .= '
                                <a href="#count-people-slide" id="count-people" class="swiper-slide p-3 step-btn active">
                                    <div class="d-flex gap-3">
                                        <div class="d-flex justify-content-center align-items-center rounded-circle checked">
                                            ' . $html_button . '
                                        </div>
                                        <div>
                                            <strong>Anzahl der Personen</strong><br>
                                            <span class="count">' . $count_text . '</span>
                                        </div> 
                                    </div> 
                                </a>';
        } else {
            $html .= '
                                <a href="#count-people-slide"  id="count-people" class="swiper-slide p-3 step-btn active">
                                    <div class="d-flex gap-3">
                                        <div class="d-none justify-content-center align-items-center rounded-circle checked">
                                            ' . $html_button . '
                                        </div>
                                        <div class="d-flex justify-content-center align-items-center rounded-circle not-checked">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="currentColor" class="bi bi-people-fill" viewBox="0 0 16 16">
                                                <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6m-5.784 6A2.238 2.238 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.325 6.325 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1zM4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <strong>Anzahl der Personen</strong><br>
                                            <span class="count">Wie viele?</span>
                                        </div>
                                    </div> 
                                </a>';
        }

        if ( $start != '' ) {
            $count_text = $start;

            $html .= '
                                <a href="#check-in-out-slide" id="check-in-out" class="swiper-slide p-3 step-btn active">
                                    <div class="d-flex gap-3">
                                        <div class="d-flex justify-content-center align-items-center rounded-circle checked">
                                            ' . $html_button . '
                                        </div>
                                        <div>
                                            <strong>Check-In</strong><br>
                                            <span class="day">' . $count_text . '</span>
                                        </div>
                                    </div>
                                </a>';
        } else {
            $html .= '
                                <a id="check-in-out" class="swiper-slide p-3 step-btn">
                                    <div class="d-flex gap-3">
                                        <div class="d-none justify-content-center align-items-center rounded-circle checked">
                                            ' . $html_button . '
                                        </div>
                                        <div class="rounded-circle d-flex justify-content-center align-items-center not-checked">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="currentColor" class="bi bi-calendar-week" viewBox="0 0 16 16">
                                                <path d="M11 6.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm-3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm-5 3a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5z"/>
                                                <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5M1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4z"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <strong>Check-In</strong><br>
                                            <span class="day">Wann?</span>
                                        </div>
                                    </div>
                                </a>';
        }

        if ( $duration != '' ) {
            $html .= '
                                <a href="#how-long-time-slide" id="how-long-time" class="swiper-slide p-3 step-btn active">
                                    <div class="d-flex gap-3">
                                        <div class="d-flex justify-content-center align-items-center rounded-circle checked">
                                            ' . $html_button . '
                                        </div>
                                        <div>
                                            <strong>Dauer</strong><br>
                                            <span class="how-long">' . $duration . ' Stunden</span>
                                        </div>
                                    </div>
                                </a>';
        } else {
            $html .= '
                                <a id="how-long-time" class="swiper-slide p-3 step-btn">
                                    <div class="d-flex gap-3">
                                        <div class="d-none justify-content-center align-items-center rounded-circle checked">
                                            ' . $html_button . '
                                        </div>
                                        <div class="rounded-circle d-flex justify-content-center align-items-center not-checked">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="currentColor" class="bi bi-clock" viewBox="0 0 16 16">
                                                <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71z"/>
                                                <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16m7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <strong>Dauer</strong><br>
                                            <span class="how-long">Wie lange?</span>
                                        </div>  
                                    </div>
                                </a>';
        }

        if ($cart_contents_count != 0 && $person != '' && $duration != '' ) {
            $html .= '
                                <a href="#room-slide" id="room" class="swiper-slide p-3 step-btn active">
                                    <div class="d-flex gap-3">
                                        <div class="d-flex justify-content-center align-items-center rounded-circle checked">
                                            ' . $html_button . '
                                        </div>
                                        <div>
                                            <strong>Räume</strong><br>
                                            <span class="suite">ausgewählt</span>
                                        </div> 
                                    </div>
                                </a>
                                <a href="#extras-slide" id="extras" class="swiper-slide p-3 step-btn active">
                                    <div class="d-flex gap-3">
                                        <div class="d-flex justify-content-center align-items-center rounded-circle checked">
                                            ' . $html_button . '
                                        </div>
                                        <div>
                                            <strong>Extras</strong><br>
                                            <span>Buche Upgrades dazu</span>
                                        </div>
                                    </div>
                                </a>';
        } else {
            $html .= '
                                <a id="room" class="swiper-slide p-3 step-btn">
                                    <div class="d-flex gap-3">
                                        <div class="d-none justify-content-center align-items-center rounded-circle checked">
                                            ' . $html_button . '
                                        </div>
                                        <div class="rounded-circle d-flex justify-content-center align-items-center not-checked">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="currentColor" class="bi bi-shop-window" viewBox="0 0 16 16">
                                                <path d="M2.97 1.35A1 1 0 0 1 3.73 1h8.54a1 1 0 0 1 .76.35l2.609 3.044A1.5 1.5 0 0 1 16 5.37v.255a2.375 2.375 0 0 1-4.25 1.458A2.371 2.371 0 0 1 9.875 8 2.37 2.37 0 0 1 8 7.083 2.37 2.37 0 0 1 6.125 8a2.37 2.37 0 0 1-1.875-.917A2.375 2.375 0 0 1 0 5.625V5.37a1.5 1.5 0 0 1 .361-.976l2.61-3.045zm1.78 4.275a1.375 1.375 0 0 0 2.75 0 .5.5 0 0 1 1 0 1.375 1.375 0 0 0 2.75 0 .5.5 0 0 1 1 0 1.375 1.375 0 1 0 2.75 0V5.37a.5.5 0 0 0-.12-.325L12.27 2H3.73L1.12 5.045A.5.5 0 0 0 1 5.37v.255a1.375 1.375 0 0 0 2.75 0 .5.5 0 0 1 1 0M1.5 8.5A.5.5 0 0 1 2 9v6h12V9a.5.5 0 0 1 1 0v6h.5a.5.5 0 0 1 0 1H.5a.5.5 0 0 1 0-1H1V9a.5.5 0 0 1 .5-.5m2 .5a.5.5 0 0 1 .5.5V13h8V9.5a.5.5 0 0 1 1 0V13a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5a.5.5 0 0 1 .5-.5"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <strong>Räume</strong><br>
                                            <span class="suite">Wähle deine Suite</span>
                                        </div> 
                                    </div>
                                </a>
                                <a id="extras" class="swiper-slide p-3 step-btn">
                                    <div class="d-flex gap-3">
                                        <div class="rounded-circle d-flex justify-content-center align-items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="currentColor" class="bi bi-cup-straw" viewBox="0 0 16 16">
                                                <path d="M13.902.334a.5.5 0 0 1-.28.65l-2.254.902-.4 1.927c.376.095.715.215.972.367.228.135.56.396.56.82 0 .046-.004.09-.011.132l-.962 9.068a1.28 1.28 0 0 1-.524.93c-.488.34-1.494.87-3.01.87-1.516 0-2.522-.53-3.01-.87a1.28 1.28 0 0 1-.524-.93L3.51 5.132A.78.78 0 0 1 3.5 5c0-.424.332-.685.56-.82.262-.154.607-.276.99-.372C5.824 3.614 6.867 3.5 8 3.5c.712 0 1.389.045 1.985.127l.464-2.215a.5.5 0 0 1 .303-.356l2.5-1a.5.5 0 0 1 .65.278zM9.768 4.607A13.991 13.991 0 0 0 8 4.5c-1.076 0-2.033.11-2.707.278A3.284 3.284 0 0 0 4.645 5c.146.073.362.15.648.222C5.967 5.39 6.924 5.5 8 5.5c.571 0 1.109-.03 1.588-.085zm.292 1.756C9.445 6.45 8.742 6.5 8 6.5c-1.133 0-2.176-.114-2.95-.308a5.514 5.514 0 0 1-.435-.127l.838 8.03c.013.121.06.186.102.215.357.249 1.168.69 2.438.69 1.27 0 2.081-.441 2.438-.69.042-.029.09-.094.102-.215l.852-8.03a5.517 5.517 0 0 1-.435.127 8.88 8.88 0 0 1-.89.17zM4.467 4.884s.003.002.005.006zm7.066 0-.005.006c.002-.004.005-.006.005-.006M11.354 5a3.174 3.174 0 0 0-.604-.21l-.099.445.055-.013c.286-.072.502-.149.648-.222"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <strong>Extras</strong><br>
                                            <span>Buche Upgrades dazu</span>
                                        </div>
                                    </div>
                                </a>';
        }

        $html .= '
                </div>
            </div>';

        return $html;
    }
}