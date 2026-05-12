<?php

declare(strict_types=1);

namespace Wesanox\Booking\Boot\Booking;

defined('ABSPATH') || exit;

use Wesanox\Booking\Application\Integration\TriggerOrderSyncService;
use Wesanox\Booking\Infrastructure\WesanoxApi\WesanoxApiBridge;
use Wesanox\Booking\Infrastructure\WesanoxApi\WesanoxApiException;
use function PHPUnit\Framework\isArray;

/**
 * Hooks into WooCommerce order events and synchronises orders with the
 * external booking system via POST /webhooks/orders.
 *
 * Credential resolution (first match wins):
 *   1. Order meta  wesanox_api_credential_id  (set by booking flow)
 *   2. WP option   wesanox_default_api_credential_id  (site-wide default)
 *   3. First credential returned by the bridge
 */
class HandlerBooking
{
    private TriggerOrderSyncService $syncService;

    public function __construct()
    {
        $bridge            = new WesanoxApiBridge();
        $this->syncService = new TriggerOrderSyncService($bridge);

        add_action('woocommerce_checkout_order_processed', [$this, 'handleOrder'], 10, 1);
        add_action('woocommerce_order_status_changed',     [$this, 'handleOrder'], 10, 4);
    }

    public function handleOrder(int|string $orderId): void
    {
        $orderId = (int) $orderId;
        $order   = wc_get_order($orderId);

        if (empty($order)) {
            error_log("wesanox-booking HandlerBooking: Order #{$orderId} nicht gefunden.");
            return;
        }

        // Validate booking meta before triggering sync.
        $startTime = $order->get_meta('Startzeit', true);
        $endTime   = $order->get_meta('Endzeit',   true);

        if (!$startTime || !$endTime) {
            $this->notifyMissingMeta($order, $orderId);
            return;
        }

        $credentialId = $this->resolveCredentialId($order);

        if ($credentialId <= 0) {
            error_log("wesanox-booking HandlerBooking: Keine API-Credential-ID für Order #{$orderId} ermittelbar.");
            $order->add_order_note('Wesanox API: Keine Credential-ID konfiguriert. Sync nicht ausgeführt.');
            return;
        }

        try {
            $response = $this->syncService->execute($orderId, $credentialId);

            $data    = is_array($response->data) ? $response->data : [];
            $syncId  = $data['sync_id']  ?? '—';
            $status  = $data['sync_status'] ?? '—';

            $order->add_order_note("Wesanox API: Order synchronisiert. sync_id={$syncId}, status={$status}");

        } catch (WesanoxApiException $e) {
            error_log("wesanox-booking HandlerBooking: API-Fehler für Order #{$orderId}: {$e->getMessage()} (HTTP {$e->statusCode})");
            $order->add_order_note("Wesanox API Fehler: {$e->getMessage()} (HTTP {$e->statusCode})");
        } catch (\Throwable $e) {
            error_log("wesanox-booking HandlerBooking: Unerwarteter Fehler für Order #{$orderId}: {$e->getMessage()}");
            $order->add_order_note('Wesanox API: Unerwarteter Fehler. Bitte Logs prüfen.');
        }
    }

    // ── backwards-compatibility ───────────────────────────────────────────────

    /**
     * @deprecated  Used by WoocommerceProductHandler — kept to avoid a breaking
     *              change until that class is refactored to use the bridge directly.
     *              Delegates to WesanoxApiBridge::get() via the first available credential.
     *
     * @return mixed  Decoded JSON body or null on failure
     */
    public function api_call_requests(string $urlSection): mixed
    {
        $bridge = new WesanoxApiBridge();

        if (!$bridge->isAvailable()) {
            error_log('wesanox-booking api_call_requests: wesanox-api nicht aktiv.');
            return null;
        }

        $options = $bridge->listCredentialOptions();

        if (empty($options)) {
            error_log('wesanox-booking api_call_requests: Keine API-Credentials gefunden.');
            return null;
        }

        $credentialId = (int) array_key_first($options);

        // Split path and query string so the bridge can pass them separately.
        $parts = explode('?', $urlSection, 2);
        $path  = '/' . ltrim($parts[0], '/');
        $query = [];

        if (isset($parts[1])) {
            parse_str($parts[1], $query);
        }

        $response = $bridge->get($credentialId, $path, $query);

        if (!$response->isOk()) {
            error_log("wesanox-booking api_call_requests: Fehler für {$path}: {$response->message}");
            return null;
        }

        return $response->data;
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Determine the API credential ID to use for this order.
     *
     * Priority:
     *   1. Order meta  wesanox_api_credential_id
     *   2. WP option   wesanox_default_api_credential_id
     *   3. First available credential from the bridge
     */
    private function resolveCredentialId(\WC_Order $order): int
    {
        // 1. Per-order meta (set by the booking flow when it knows the area).
        $fromMeta = (int) $order->get_meta('wesanox_api_credential_id', true);
        if ($fromMeta > 0) {
            return $fromMeta;
        }

        // 2. Site-wide WP option.
        $fromOption = (int) get_option('wesanox_default_api_credential_id', 0);
        if ($fromOption > 0) {
            return $fromOption;
        }

        // 3. First credential available in the bridge.
        $bridge  = new WesanoxApiBridge();
        $options = $bridge->listCredentialOptions();
        if (!empty($options)) {
            return (int) array_key_first($options);
        }

        return 0;
    }

    private function notifyMissingMeta(\WC_Order $order, int $orderId): void
    {
        $order->add_order_note('Wesanox API: Startzeit oder Endzeit fehlt. Sync nicht ausgeführt.');

        $to      = 'wester@mediamus.de, sandra@emsland-camping.de, melissa@emsland-camping.de';
        $subject = 'FEHLER BEI BESTELLUNG ' . $orderId;
        $message = "Die Bestellung mit der ID {$orderId} hat keine Startzeit oder Endzeit.";

        wp_mail($to, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);

        error_log("wesanox-booking HandlerBooking: Order #{$orderId}: Startzeit oder Endzeit fehlt.");
    }
}
