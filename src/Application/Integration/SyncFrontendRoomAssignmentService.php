<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Integration;

defined('ABSPATH') || exit;

use Wesanox\Booking\Infrastructure\WesanoxApi\BackendResponse;
use Wesanox\Booking\Infrastructure\WesanoxApi\WesanoxApiBridgeInterface;

/**
 * Pushes the WordPress-assigned room_id back to the Laravel backend.
 *
 * This is intentionally independent from WooCommerce order sync. The frontend
 * remains the master for room assignment; Laravel only receives item_id/room_id.
 */
final class SyncFrontendRoomAssignmentService
{
    private const ENDPOINT = '/v1/woo/frontend-bookings/room';

    public function __construct(
        private WesanoxApiBridgeInterface $bridge,
    ) {
    }

    /**
     * @param array<string, mixed> $booking
     */
    public function execute(array $booking): BackendResponse
    {
        if (!$this->shouldSync($booking)) {
            return new BackendResponse(
                success: true,
                data: ['skipped' => true],
                message: 'Room sync skipped.',
                errors: [],
                statusCode: 200,
            );
        }

        if (!$this->bridge->isAvailable()) {
            return BackendResponse::pluginNotAvailable();
        }

        $credential_id = $this->resolveCredentialId();

        if ($credential_id <= 0) {
            return new BackendResponse(
                success: false,
                data: null,
                message: 'Keine API-Credential-ID fuer Room-Sync konfiguriert.',
                errors: ['credential_not_configured'],
                statusCode: 0,
            );
        }

        return $this->bridge->post($credential_id, self::ENDPOINT, [
            'external_source' => 'backend',
            'external_id'     => (string) $booking['external_id'],
            'room_id'         => (int) $booking['room_id'],
            'booking_date'    => (string) ($booking['booking_date'] ?? ''),
            'booking_from'    => (string) ($booking['booking_from'] ?? ''),
            'booking_to'      => (string) ($booking['booking_to'] ?? ''),
        ]);
    }

    /**
     * @param array<string, mixed> $booking
     */
    private function shouldSync(array $booking): bool
    {
        $source = strtolower(trim((string) ($booking['external_source'] ?? '')));

        return $source === 'backend'
            && !empty($booking['external_id'])
            && !empty($booking['room_id'])
            && (int) $booking['room_id'] > 0;
    }

    private function resolveCredentialId(): int
    {
        $from_option = (int) get_option('wesanox_default_api_credential_id', 0);

        if ($from_option > 0) {
            return $from_option;
        }

        $options = $this->bridge->listCredentialOptions();

        if (!empty($options)) {
            return (int) array_key_first($options);
        }

        return 0;
    }
}
