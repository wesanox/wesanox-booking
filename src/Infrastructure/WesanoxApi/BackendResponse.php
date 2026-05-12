<?php

declare(strict_types=1);

namespace Wesanox\Booking\Infrastructure\WesanoxApi;

defined('ABSPATH') || exit;

/**
 * Local DTO that mirrors \Wesanox\Api\Infrastructure\Http\ApiResponse.
 *
 * Keeps wesanox-booking independent of the wesanox-api namespace at the
 * type level — the bridge converts ApiResponse → BackendResponse at runtime.
 */
final class BackendResponse
{
    public function __construct(
        public readonly bool   $success,
        public readonly mixed  $data,
        public readonly string $message,
        /** @var string[] */
        public readonly array  $errors,
        public readonly int    $statusCode,
    ) {
    }

    /** Build from a wesanox-api ApiResponse object (duck-typed at runtime). */
    public static function fromApiResponse(object $apiResponse): self
    {
        return new self(
            success:    (bool)   ($apiResponse->success    ?? false),
            data:                 $apiResponse->data        ?? null,
            message:   (string)  ($apiResponse->message    ?? ''),
            errors:    (array)   ($apiResponse->errors     ?? []),
            statusCode:(int)     ($apiResponse->statusCode ?? 0),
        );
    }

    /** Returned when the wesanox-api plugin is not installed / active. */
    public static function pluginNotAvailable(): self
    {
        return new self(
            success:    false,
            data:       null,
            message:    'Das wesanox-api Plugin ist nicht aktiv.',
            errors:     ['plugin_unavailable'],
            statusCode: 0,
        );
    }

    /** Returned when credentials could not be loaded. */
    public static function credentialNotFound(int $credentialId): self
    {
        return new self(
            success:    false,
            data:       null,
            message:    "API-Zugangsdaten mit ID {$credentialId} nicht gefunden.",
            errors:     ['credential_not_found'],
            statusCode: 0,
        );
    }

    public function isOk(): bool
    {
        return $this->success && $this->statusCode >= 200 && $this->statusCode < 300;
    }
}
