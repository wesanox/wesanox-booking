<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Integration;

defined('ABSPATH') || exit;

use Wesanox\Booking\Infrastructure\WesanoxApi\BackendResponse;
use Wesanox\Booking\Infrastructure\WesanoxApi\WesanoxApiException;
use Wesanox\Booking\Infrastructure\WesanoxApi\WesanoxApiBridgeInterface;

/**
 * Sends a local booking to the external API (outbound sync).
 *
 * Usage:
 *   $response = $service->execute(
 *       credentialId:   3,
 *       externalAreaId: 'suite-1',
 *       localAreaId:    5,
 *       payload:        ['date' => '2025-06-15', 'start' => '14:00', ...],
 *   );
 */
final class SyncAreaBookingService
{
    public function __construct(
        private WesanoxApiBridgeInterface $bridge,
    ) {
    }

    /**
     * POST the booking payload to the remote API.
     *
     * @param  array<string, mixed> $payload  Booking data to synchronise
     * @throws WesanoxApiException  When the API call fails
     */
    public function execute(
        int    $credentialId,
        string $externalAreaId,
        int    $localAreaId,
        array  $payload,
    ): BackendResponse {
        $endpoint = '/bookings';

        $body = array_merge($payload, ['area_id' => $externalAreaId]);

        $response = $this->bridge->post($credentialId, $endpoint, $body);

        if (!$response->isOk()) {
            throw WesanoxApiException::fromBackendResponse($response, $endpoint, $localAreaId);
        }

        return $response;
    }
}
