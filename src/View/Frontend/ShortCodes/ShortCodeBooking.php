<?php

namespace Wesanox\Booking\View\Frontend\ShortCodes;

defined( 'ABSPATH' )|| exit;

use Wesanox\Booking\View\Frontend\Elements\ElementBookingCalender;
use Wesanox\Booking\View\Frontend\Elements\ElementBookingPersons;
use Wesanox\Booking\View\Frontend\Elements\ElementBookingTime;
use Wesanox\Booking\View\Frontend\Elements\ElementBookingDuration;
use Wesanox\Booking\View\Frontend\Elements\ElementBookingItem;
use Wesanox\Booking\View\Frontend\Elements\ElementBookingExtras;
use Wesanox\Booking\View\Frontend\Elements\ElementBookingModal;

use Wesanox\Booking\View\Frontend\Helper\HelperNavigation;

class ShortCodeBooking
{
    protected ElementBookingPersons $element_booking_persons;
    protected ElementBookingCalender $element_booking_calender;
    protected ElementBookingTime $element_booking_time;
    protected ElementBookingDuration $element_booking_duration;
    protected ElementBookingItem $element_booking_item;
    protected ElementBookingExtras $element_booking_extras;
    protected ElementBookingModal $element_booking_modal;

    protected HelperNavigation $helper_navigation;

    public function __construct()
    {
        $this->element_booking_persons = new ElementBookingPersons();
        $this->element_booking_calender = new ElementBookingCalender();
        $this->element_booking_time = new ElementBookingTime();
        $this->element_booking_duration = new ElementBookingDuration();
        $this->element_booking_item = new ElementBookingItem();
        $this->element_booking_extras = new ElementBookingExtras();
        $this->element_booking_modal = new ElementBookingModal();

        $this->helper_navigation = new HelperNavigation();

        add_shortcode('booking_view', [$this, 'wesanox_render_frontend_shortcode']);
    }

    public function wesanox_render_frontend_shortcode(): string
    {
        $booking = ( function_exists('WC') && WC()->session ) ? (array) WC()->session->get('booking', []) : [];
        $duration = ( !empty($booking['how_long']) ) ? $booking['how_long'] : '';

        $active_box = ( $duration != '' ) ? ' d-none d-md-block' : ' d-none';
        $active     = ( $duration != '' ) ? ' active' : ' inactive';

        return '
            <div id="wesanox-booking">
                <div class="timer-box text-center h6 mb-5 d-none">
                    Ablauf deiner Buchung: <span id="timer"></span> - Danach musst du deine Daten erneut eingeben.
                </div>
                ' . $this->helper_navigation->wesanox_render_frontend_navigation() . '
                <div class="swiper content-swiper mt-5 step-boxen">
                    <div class="swiper-wrapper">
                        ' . $this->element_booking_persons->wesanox_render_element_booking_persons() . '
                        <div data-hash="check-in-out-slide" class="swiper-slide">
                            <div class="row mx-0 w-100">  
                                ' . $this->element_booking_calender->wesanox_render_element_booking_calender() . '
                                ' . $this->element_booking_time->wesanox_render_element_booking_time($active_box, $active) . '
                            </div>
                        </div>
                        ' . $this->element_booking_duration->wesanox_render_element_booking_duration() . '
                        ' . $this->element_booking_item->wesanox_render_element_booking_item() . '
                        ' . $this->element_booking_extras->wesanox_render_element_booking_extras() . '
                    </div>
                </div>
            </div>
            ' . $this->element_booking_modal->wesanox_render_element_booking_modal();
    }
}