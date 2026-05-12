<?php

declare(strict_types=1);

namespace Wesanox\Booking\Infrastructure\WesanoxApi;

defined('ABSPATH') || exit;

/**
 * Adapter interface that wesanox-booking uses to talk to external APIs.
 *
 * Concrete implementation: WesanoxApiBridge (delegates to wesanox-api plugin).
 * Test doubles can implement this interface without requiring the real plugin.
 */
interface WesanoxApiBridgeInterface
{
    /**
     * Returns true when the wesanox-api plugin is installed, active, and its
     * classes are autoloaded.  All other methods return BackendResponse::pluginNotAvailable()
     * when this is false.
     */
    public function isAvailable(): bool;

    /**
     * Perform a GET request using the stored credentials identified by $credentialId.
     *
     * @param int    $credentialId  ID from wesanox_api_credentials table
     * @param string $path          API path, e.g. '/bookings'
     * @param array<string, mixed> $query  URL query parameters
     */
    public function get(int $credentialId, string $path, array $query = []): BackendResponse;

    /**
     * Perform a POST request using the stored credentials identified by $credentialId.
     *
     * @param int    $credentialId  ID from wesanox_api_credentials table
     * @param string $path          API path, e.g. '/bookings'
     * @param array<string, mixed> $body  Request body
     */
    public function post(int $credentialId, string $path, array $body = []): BackendResponse;

    /**
     * Test connectivity for a stored credential.
     * Returns a successful BackendResponse when the remote endpoint is reachable.
     */
    public function ping(int $credentialId): BackendResponse;

    /**
     * Return all stored API credentials as a flat map of id => label.
     * Used to populate admin select dropdowns without hard-coupling to wesanox-api classes.
     *
     * @return array<int, string>  [credential_id => "URL (id: N)"]
     */
    public function listCredentialOptions(): array;
}
