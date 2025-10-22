<?php

namespace Wesanox\Booking\View\Frontend\Elements;

defined( 'ABSPATH' )|| exit;

class ElementBookingPersons
{
    /**
     * Renders a frontend HTML representation for selecting the number of guests per room.
     *
     * This method generates a structured HTML snippet designed for a sliding interface. Users can specify the number of guests
     * (1 to 4) with checkboxes, along with descriptive guidelines regarding the room's capacity.
     *
     * @return string The HTML markup for the guest selection interface.
     */
    public function wesanox_render_element_booking_persons(): string
    {
        return '
            <div data-hash="count-people-slide" class="swiper-slide">
                <div class="row mx-0">
                    <div id="element-booking-persons" class="col-12 col-md-6 col-xl-3 py-4 px-3 step">
                        <h4 class="mb-2">Anzahl der Gäste</h4>
                        Max 4 Personen pro Raum<br>
                        <div class="d-flex gap-2 mt-2">
                            <label for="person-1" id="person-1">
                                1
                                <input type="checkbox" class="person" name="1">
                            </label>
                            <label for="person-2" id="person-2" class="active">
                                2
                                <input type="checkbox" class="person" name="2">
                            </label>
                            <label for="person-3" id="person-3">
                                3
                                <input type="checkbox" class="person" name="3">
                            </label>
                            <label for="person-4" id="person-4">
                                4
                                <input type="checkbox" class="person" name="4">
                            </label>
                        </div>
                        <div class="bg-white mt-2 py-3 px-2">
                            <div class="text-justify">
                                Die Nutzung einer Suite mit zwei Gästen ist ideal. Hierfür wurden unsere Suiten designt.<br> Du kannst die Suite auch mit bis zu vier Personen nutzen. Dadurch kann es möglicherweise zu Einschränkungen kommen.
                            </div>
                        </div>
                        <div class="w-100 d-flex justify-content-end">
                            <a href="#check-in-out-slide" value="check-in-out-box" step="2" class="btn btn-primary active d-flex justify-content-between align-items-center forward mt-3">
                                Weiter <span>></span>
                            </a>
                        </div>
                    </div>
                </div>          
            </div>';
    }
}