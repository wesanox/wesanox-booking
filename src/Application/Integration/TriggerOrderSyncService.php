<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Integration;

defined('ABSPATH') || exit;

use Wesanox\Booking\Infrastructure\WesanoxApi\BackendResponse;
use Wesanox\Booking\Infrastructure\WesanoxApi\WesanoxApiException;
use Wesanox\Booking\Infrastructure\WesanoxApi\WesanoxApiBridgeInterface;

/**
 * Triggers a WooCommerce order sync on the external API.
 *
 * Calls  POST /webhooks/orders  with { order_id, api_id }.
 * The remote Laravel app then fetches the order from WooCommerce and
 * creates/updates the booking on its end.
 *
 * Usage:
 *   $response = $service->execute(orderId: 123, credentialId: 1);
 */
final class TriggerOrderSyncService
{
    private const ENDPOINT = '/webhooks/orders';

    public function __construct(
        private WesanoxApiBridgeInterface $bridge,
    ) {
    }

    /**
     * @param int $orderId      WooCommerce order ID
     * @param int $credentialId ID from wesanox_api_credentials table (= api_id on remote)
     * @throws WesanoxApiException  When the API call fails or returns non-2xx
     */
    public function execute(int $orderId, int $credentialId): BackendResponse
    {
        if (!$this->bridge->isAvailable()) {
            return BackendResponse::pluginNotAvailable();
        }

        $body = [
            'order_id' => $orderId,
            'api_id'   => $credentialId,
        ];

        $response = $this->bridge->post($credentialId, self::ENDPOINT, $body);

        if (!$response->isOk()) {
            throw WesanoxApiException::fromBackendResponse($response, self::ENDPOINT);
        }

        return $response;
    }
}
