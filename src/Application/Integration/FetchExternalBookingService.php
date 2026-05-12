<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Integration;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\Integration\ExternalBooking;
use Wesanox\Booking\Domain\Integration\ExternalBookingMapper;
use Wesanox\Booking\Infrastructure\WesanoxApi\WesanoxApiException;
use Wesanox\Booking\Infrastructure\WesanoxApi\WesanoxApiBridgeInterface;

/**
 * Fetches external bookings for a specific area from the remote API.
 *
 * Usage:
 *   $bookings = $service->execute(
 *       credentialId:   3,
 *       externalAreaId: 'suite-1',
 *       localAreaId:    5,
 *       date:           '2025-06-15',
 *   );
 */
final class FetchExternalBookingService
{
    public function __construct(
        private WesanoxApiBridgeInterface $bridge,
        private ExternalBookingMapper     $mapper,
    ) {
    }

    /**
     * @return ExternalBooking[]
     * @throws WesanoxApiException  When the API call fails
     */
    public function execute(
        int    $credentialId,
        string $externalAreaId,
        int    $localAreaId,
        string $date,
        ?string $dateTo = null,
    ): array {
        $query = [
            'area_id' => $externalAreaId,
            'date'    => $date,
        ];

        if ($dateTo !== null) {
            $query['date_to'] = $dateTo;
        }

        $endpoint = '/bookings';
        $response = $this->bridge->get($credentialId, $endpoint, $query);

        if (!$response->isOk()) {
            throw WesanoxApiException::fromBackendResponse($response, $endpoint, $localAreaId);
        }

        $items = is_array($response->data) ? $response->data : [];

        return $this->mapper->fromRawList($items, $localAreaId, $externalAreaId);
    }
}
