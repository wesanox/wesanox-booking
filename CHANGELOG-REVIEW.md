## 2026-07-03

### Datei: `src/Woocommerce/WoocommerceProductHandler.php`

* Aenderung: Vor dem lokalen Booking-Insert wird eine vorhandene Buchung aus `_booking_id` oder `wc_order_id` wiederverwendet.
* Grund: WooCommerce-Checkout- oder Payment-Replays duerfen keinen zweiten Datensatz in `wp_wesanox_bookings` erzeugen.
* Technische Auswirkung: Replays aktualisieren die Order-Metadaten mit der bestehenden Buchung und leeren den Checkout-State.
* Risiko / Nebenwirkung: Vorhandene Dubletten werden nicht automatisch bereinigt.
* Pruefhinweis: Gleichen WooCommerce-Order-Callback zweimal ausfuehren und pruefen, dass nur eine Zeile mit der `wc_order_id` existiert.

### Datei: `src/Repository/RepositoryBooking.php`

* Aenderung: Lookup-Methoden fuer Booking-ID und WooCommerce-Order-ID ergaenzt; `insert_booking()` gibt bei vorhandener `wc_order_id` die bestehende ID zurueck.
* Grund: Die Repository-Schicht soll den Checkout-Guard absichern, falls der Insert-Pfad erneut verwendet wird.
* Technische Auswirkung: Bestehende Buchungen werden nach `id ASC` priorisiert, falls Altdaten bereits Dubletten enthalten.
* Risiko / Nebenwirkung: Kein Datenbank-Unique-Key; echte Altdubletten bleiben bestehen.
* Pruefhinweis: Repository-Insert mit gleicher `wc_order_id` wiederholen und Rueckgabe derselben Booking-ID erwarten.
