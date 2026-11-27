## 2026-11-27

* **Button „Anderes Datum auswählen“ war unsichtbar (AEA-126):** Im „Ausgebucht“-Feld der Suite-Auswahl stand der Button-Text dunkelblau auf dunkelblauem Grund, sichtbar war nur der Rahmen. Der Button ist ein Link und bekam die allgemeine Linkfarbe (`.woocommerce a` bzw. die Elementor-Linkfarbe) statt der Button-Farbe. Im „Ausgebucht“-Feld ist die Schrift von Link-Buttons jetzt weiß und nicht mehr unterstrichen; beim Überfahren gilt die Button-Farbe von Bootstrap.
* Betroffene Dateien: `styles/frontend/elements/element-booking-item.sass`, `styles/frontend/styles.css`. Die CSS-Datei ist von Hand ergänzt, nicht neu kompiliert, weil sie den Block „Multi-Step Booking Widgets“ enthält, der in den Sass-Quellen fehlt.

* **Freitagsbuchungen über 16:00 wurden zu teuer berechnet:** Seit die Freitag-Nachmittag-Tarife am 17.11.2026 angelegt wurden (Variationen 253 und 256), bekam der Nachmittagstarif im Warenkorb die gesamte Buchungsdauer, der Vormittagstarif kam zusätzlich dazu. Beispiel Freitag 13:45–17:45, 2 Personen Premium: 4,5 h Vormittag und 8 h Nachmittag statt 4,5 h und 3,5 h. Der Warenkorb erkannte Vormittag und Nachmittag an festen Variations-IDs (251/254 und 252/255); jede andere ID bekam die volle Dauer.
* Die Menge richtet sich jetzt nach dem Segment: ein Tarif gilt für die ganze Buchung, bei zwei Tarifen der erste für die Stunden vor 16:00 und der zweite für die Stunden ab 16:00. Neue Tarife brauchen keine Codeänderung mehr.
* Mo–Do und Buchungen mit nur einem Tarif rechnen unverändert. Mehr als zwei Tarife in einer Anfrage werden abgelehnt.
* Betroffene Dateien: `src/Domain/Cart/TariffQuantityAllocator.php` (neu), `src/Woocommerce/WoocommerceProductHandler.php`, `tests/Unit/Domain/Cart/TariffQuantityAllocatorTest.php`
* **Anzeige in Warenkorb und Kasse:** Die Zeile des Freitag-Nachmittag-Tarifs zeigte weiter „… Personen je 4 Stunden“ (gesamte Dauer) und wiederholte den Suite-Kopf, weil auch die Templates den Tarif an festen Variations-IDs erkannten. Sie richten sich jetzt nach der Position der Tarifzeile und zeigen die Stunden ab 16:00 (im Beispiel 1:45). Gemeinsam mit dem Plugin ausrollen.
* Betroffene Dateien im Child-Theme `hello-elementor-child`: `woocommerce/cart/cart.php`, `woocommerce/checkout/review-order.php`

## 2026-11-16

* **Späte Buchungen blockieren die Suite im Kalender (AEA-93, AEA-33):** Buchungen, die nach 23:30 enden (23:45, 23:59, 24:00), blockierten ihre Suite im Kalender gar nicht. Durch den 30-Minuten-Puffer sprang das Ende auf „00:xx“, der gesperrte Zeitraum war leer. Ausgebuchte Freitage und Samstage standen deshalb auf „Hohes Aufkommen“, obwohl der Startzeiten-Schritt keine Zeit mehr anbot. Das Ende wird jetzt auf die Schließzeit begrenzt.
* Startzeiten, Dauer und Checkout waren nicht betroffen (SQL-Prüfung rechnet korrekt).
* Betroffene Dateien: `src/Service/ServiceGetAvailableTimes.php`, `tests/Unit/Service/LateBookingsBlockCalendarTest.php`
* Nachgestellt mit Produktivdaten-Backup vom 26.10.2026: 10.10. und 24.10.2026 wurden vorher als „Hohes Aufkommen“ angezeigt, jetzt als „Ausgebucht“.

## 2026-11-02

* **Stornierte Buchungen geben den Raum frei:** Buchungen mit `booking_status = 3` (im Backend storniert) blockieren keinen Raum mehr – auch wenn die WooCommerce-Bestellung noch offen ist oder die Buchung gar keine Bestellung hat (z. B. stornierte Testbuchungen). Bisher entschied nur der Bestellstatus. Buchungen ohne Status (Checkout-Holds, Altdaten) blockieren weiterhin.
* Wirkt auf Kalender (inkl. Zählung für „Hohes Aufkommen“), Startzeiten, Dauer, Kategorie-Auswahl, Raumzuweisung im Checkout, Shortcut-Prüfungen und die Konfliktprüfung beim Sync vom Backend (dort wurden Backend-Buchungen bisher mit 409 abgelehnt, wenn eine stornierte Buchung im Weg lag).
* Betroffene Dateien: `src/Domain/Booking/BookingOccupancy.php` (neu), `src/Service/ServiceGetAvailableTimes.php`, `src/Service/ServiceGetAvailableRoomarts.php`, `src/Repository/RepositoryBooking.php`, `src/Application/Shortcut/CheckTimeslotAvailabilityService.php`, `src/Application/Shortcut/CheckSuiteAvailabilityService.php`, `src/Rest/Controllers/BookingController.php`, `tests/Unit/Domain/Booking/BookingOccupancyTest.php`, `tests/Unit/Service/CancelledBookingsDoNotBlockTest.php`
* Anlass: Suite 6 war am 01.11.2026 im Frontend fast den ganzen Tag nicht buchbar.

## 2026-10-28

* **Allergie-Hinweis (AEA-41):** Der im Checkout eingegebene Hinweis zu Allergien und Unverträglichkeiten bei Specials wird zusätzlich als Order-Meta `_wesanox_allergy_notice` gespeichert. Das Laravel-Backend übernimmt ihn daraus als eigenes Buchungsfeld. Die Kundennotiz der Bestellung enthält den Hinweis weiterhin, damit er in Bestellmail und WP-Admin sichtbar bleibt.
* Betroffene Dateien: `src/Domain/Booking/SpecialsCustomerNotice.php`, `src/Woocommerce/WoocommerceProductHandler.php`, `tests/Unit/Domain/Booking/SpecialsCustomerNoticeTest.php`.

## 2026-10-19

* Checkout: Die Verfügbarkeitsantwort des Laravel-Backends entscheidet jetzt. Meldet es „nicht verfügbar“, wird der Checkout abgebrochen; bei `outside_opening_hours` mit der Meldung „Zu dieser Uhrzeit ist der Spa-Bereich geschlossen.“ Bisher wurde jede Antwort als verfügbar gewertet. Ist das Backend nicht erreichbar, prüft das Plugin die lokal synchronisierte Öffnungszeit des Tages (inkl. Ausnahmen).
* Betroffene Dateien: `src/Domain/Booking/CheckoutAvailabilityDecision.php`, `src/Woocommerce/WoocommerceProductHandler.php`, `tests/Unit/Domain/Booking/CheckoutAvailabilityDecisionTest.php` (P26011PB-36, AEA-4)
* Die WooCommerce-Mails „Stornierte Bestellung“ (Administration und Kunde) werden für Bestellungen mit Buchung unterdrückt; die Storno-Mails versendet das Laravel-Backend je Stornoart (Gast-Storno, Storno wegen Nichtzahlung, Backoffice). Gutschein- und andere Bestellungen behalten ihre WooCommerce-Mails.
* Die Selbststornierung setzt das Bestellmerkmal `_wesanox_cancelled_by = guest`, damit das Backend einen Gast-Storno von einem Storno im WooCommerce-Admin unterscheiden kann.
* Betroffene Dateien: `src/Woocommerce/BookingCancellationEmailFilter.php`, `src/Plugin.php`, `src/View/Frontend/ShortCodes/ShortCodeCancle.php`, `tests/Unit/Woocommerce/BookingCancellationEmailFilterTest.php`
* Zweck: P26015PI-63, AEA-8. Erst nach dem Laravel-Deployment ausrollen, sonst erhalten Gäste bei Woo-Stornos keine Mail.

## 2026-10-16

* Der Order-Sync reagiert zusätzlich auf die Aktion `wesanox_gift_order_coupons_created` aus `wesanox-coupon`. So erfährt das Backend auch von Gutscheinen, die per Bestellaktion „Zahlung bestätigt – Gutschein erzeugen“ ohne Statuswechsel erzeugt wurden.
* Betroffene Dateien: `src/Boot/Booking/HandlerBooking.php`
* Zweck: Gutscheinbestellungen mit manuell bestätigter Zahlung werden ins Backend übernommen (P25013WPC-2).

## 2026-10-16

* Die Stornierung im Kundenkonto fragt die Stornierbarkeit über `POST /v1/woo/orders/cancellation-policy` im Laravel-Backend ab, statt 48 Stunden und Versicherungs-IDs fest zu codieren. Vor der Selbststornierung wird erneut geprüft; ist das Backend nicht erreichbar, wird nur eine Stornierungsanfrage angeboten.
* Texte nennen Fristende und konfigurierte Stornofrist; mit Rücktrittsversicherung ist die Selbststornierung bis Terminbeginn möglich.
* Der Filter `wesanox_booking_cancellation_insurance_variation_ids` entfällt; Versicherungsprodukte werden je Area im Backend gepflegt.
* Betroffene Dateien: `src/View/Frontend/ShortCodes/ShortCodeCancle.php`, `src/Application/Integration/FetchCancellationPolicyService.php`, `src/Application/Integration/CancellationPolicyResult.php`, `tests/Unit/Application/Integration/FetchCancellationPolicyServiceTest.php`
* Zweck: Eine gemeinsame Stornoregel für Frontend und Backend (P26011PB-31).

## 2026-07-03

* Checkout-Buchungen in WordPress gegen doppelte Erstellung fuer dieselbe `wc_order_id` abgesichert.
* Betroffene Dateien: `src/Woocommerce/WoocommerceProductHandler.php`, `src/Repository/RepositoryBooking.php`.
* Zweck: Wiederholte WooCommerce-Checkout-Callbacks sollen vorhandene Buchungen wiederverwenden, statt weitere Datensaetze in `wp_wesanox_bookings` anzulegen.
