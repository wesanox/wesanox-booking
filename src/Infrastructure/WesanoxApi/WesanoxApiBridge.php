<?php

declare(strict_types=1);

namespace Wesanox\Booking\Infrastructure\WesanoxApi;

defined('ABSPATH') || exit;

/**
 * Concrete bridge to the wesanox-api plugin.
 *
 * All wesanox-api class references are guarded by class_exists() checks so
 * that this file can be loaded (and tested) even when wesanox-api is absent.
 * No wesanox-api class is referenced at the type level — only at runtime.
 */
final class WesanoxApiBridge implements WesanoxApiBridgeInterface
{
    // Fully-qualified class names resolved at runtime only.
    private const REPO_CLASS   = 'Wesanox\\Api\\Infrastructure\\ApiCredentials\\WordPressApiCredentialsRepository';
    private const CLIENT_CLASS = 'Wesanox\\Api\\Infrastructure\\Http\\LaravelApiClient';
    private const PLUGIN_FILE  = 'wesanox-api/wesanox-api.php';

    // ── availability ──────────────────────────────────────────────────────────

    public function isAvailable(): bool
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active(self::PLUGIN_FILE)
            && class_exists(self::REPO_CLASS)
            && class_exists(self::CLIENT_CLASS);
    }

    // ── HTTP verbs ────────────────────────────────────────────────────────────

    public function get(int $credentialId, string $path, array $query = []): BackendResponse
    {
        if (!$this->isAvailable()) {
            return BackendResponse::pluginNotAvailable();
        }

        $creds = $this->fetchCredential($credentialId);
        if ($creds === null) {
            return BackendResponse::credentialNotFound($credentialId);
        }

        try {
            $client   = $this->makeClient();
            $response = $client->get($creds, $path, $query);
            return BackendResponse::fromApiResponse($response);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function post(int $credentialId, string $path, array $body = []): BackendResponse
    {
        if (!$this->isAvailable()) {
            return BackendResponse::pluginNotAvailable();
        }

        $creds = $this->fetchCredential($credentialId);
        if ($creds === null) {
            return BackendResponse::credentialNotFound($credentialId);
        }

        try {
            $client   = $this->makeClient();
            $response = $client->post($creds, $path, $body);
            return BackendResponse::fromApiResponse($response);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function ping(int $credentialId): BackendResponse
    {
        if (!$this->isAvailable()) {
            return BackendResponse::pluginNotAvailable();
        }

        $creds = $this->fetchCredential($credentialId);
        if ($creds === null) {
            return BackendResponse::credentialNotFound($credentialId);
        }

        try {
            $client   = $this->makeClient();
            $response = $client->ping($creds);
            return BackendResponse::fromApiResponse($response);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    // ── admin helpers ─────────────────────────────────────────────────────────

    public function listCredentialOptions(): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        try {
            /** @var object $repo */
            $repo  = new (self::REPO_CLASS)();
            $creds = $repo->findAll();
            $map   = [];
            foreach ($creds as $cred) {
                $map[(int) $cred->id] = $cred->api_url . ' (ID: ' . $cred->id . ')';
            }
            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    // ── private helpers ───────────────────────────────────────────────────────

    /** @return object|null  ApiCredentials instance or null */
    private function fetchCredential(int $credentialId): ?object
    {
        try {
            /** @var object $repo */
            $repo = new (self::REPO_CLASS)();
            return $repo->findById($credentialId);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return object  LaravelApiClient instance */
    private function makeClient(): object
    {
        return new (self::CLIENT_CLASS)();
    }

    private function errorResponse(string $message): BackendResponse
    {
        return new BackendResponse(
            success:    false,
            data:       null,
            message:    $message,
            errors:     ['exception'],
            statusCode: 0,
        );
    }
}
