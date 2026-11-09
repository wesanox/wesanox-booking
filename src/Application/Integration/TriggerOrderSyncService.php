<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Integration;

defined('ABSPATH') || exit;

use Wesanox\Booking\Infrastructure\WesanoxApi\BackendResponse;
use Wesanox\Booking\Infrastructure\WesanoxApi\WesanoxApiException;
use Wesanox\Booking\Infrastructure\WesanoxApi\WesanoxApiBridgeInterface;

/**
 * Triggers WooCommerce order processing on the external API.
 *
 * Calls POST /v1/woo/orders/process with { order_id }.
 * The remote Laravel app fetches the order from WooCommerce and classifies
 * whether it contains booking data, coupon data, or both.
 *
 * Usage:
 *   $response = $service->execute(orderId: 123, credentialId: 1);
 */
final class TriggerOrderSyncService
{
    private const ENDPOINT = '/v1/woo/orders/process';

    public function __construct(
        private WesanoxApiBridgeInterface $bridge,
    ) {
    }

    /**
     * @param int $orderId      WooCommerce order ID
     * @param int $credentialId ID from wesanox_api_credentials table for the Laravel app API connection
     * @throws WesanoxApiException  When the API call fails or returns non-2xx
     */
    public function execute(int $orderId, int $credentialId): BackendResponse
    {
        if (!$this->bridge->isAvailable()) {
            return BackendResponse::pluginNotAvailable();
        }

        $body = [
            'order_id' => $orderId,
        ];

        $response = $this->bridge->post($credentialId, self::ENDPOINT, $body);

        if (!$response->isOk()) {
            throw WesanoxApiException::fromBackendResponse($response, self::ENDPOINT);
        }

        return $response;
    }
}
