<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Integration;

defined('ABSPATH') || exit;

use Wesanox\Booking\Infrastructure\WesanoxApi\BackendResponse;
use Wesanox\Booking\Infrastructure\WesanoxApi\WesanoxApiBridgeInterface;

/**
 * Tests the API connection for a given area's credential.
 *
 * Usage:
 *   $response = $service->execute(credentialId: 3);
 *   if ($response->isOk()) { // connected }
 */
final class CheckAreaApiConnectionService
{
    public function __construct(
        private WesanoxApiBridgeInterface $bridge,
    ) {
    }

    /**
     * @param int $credentialId  ID from wesanox_api_credentials table
     */
    public function execute(int $credentialId): BackendResponse
    {
        if (!$this->bridge->isAvailable()) {
            return BackendResponse::pluginNotAvailable();
        }

        return $this->bridge->ping($credentialId);
    }
}
