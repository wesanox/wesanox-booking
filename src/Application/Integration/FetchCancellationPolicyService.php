<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Integration;

defined('ABSPATH') || exit;

use Wesanox\Booking\Infrastructure\WesanoxApi\WesanoxApiBridgeInterface;

/**
 * Asks the Laravel backend whether the guest may cancel a WooCommerce order by themselves.
 *
 * Calls POST /v1/woo/orders/cancellation-policy with { order_id }.
 * The cancellation deadline and the withdrawal insurance are configured per area in the
 * backend, so this plugin no longer hard-codes deadlines or insurance product IDs.
 * Any technical failure returns an "unavailable" result instead of throwing, because the
 * customer page must still render and falls back to a cancellation request.
 */
final class FetchCancellationPolicyService
{
    private const ENDPOINT = '/v1/woo/orders/cancellation-policy';

    public function __construct(
        private WesanoxApiBridgeInterface $bridge,
        private int $credential_id = 0,
    ) {
    }

    public function execute(int $order_id): CancellationPolicyResult
    {
        if (!$this->bridge->isAvailable()) {
            return CancellationPolicyResult::unavailable();
        }

        $credential_id = $this->resolveCredentialId();

        if ($credential_id <= 0) {
            return CancellationPolicyResult::unavailable();
        }

        $response = $this->bridge->post($credential_id, self::ENDPOINT, [
            'order_id' => $order_id,
        ]);

        if (!$response->isOk() || !is_array($response->data)) {
            return CancellationPolicyResult::unavailable();
        }

        return CancellationPolicyResult::fromBackendData($response->data);
    }

    private function resolveCredentialId(): int
    {
        if ($this->credential_id > 0) {
            return $this->credential_id;
        }

        $from_option = function_exists('get_option')
            ? (int) get_option('wesanox_default_api_credential_id', 0)
            : 0;

        if ($from_option > 0) {
            return $from_option;
        }

        $options = $this->bridge->listCredentialOptions();

        return !empty($options) ? (int) array_key_first($options) : 0;
    }
}
