<?php

declare(strict_types=1);

namespace Wesanox\Booking\Infrastructure\WesanoxApi;

defined('ABSPATH') || exit;

use RuntimeException;

/**
 * Thrown when an API operation fails at the application layer.
 * Wraps BackendResponse details so callers can inspect the failure.
 */
final class WesanoxApiException extends RuntimeException
{
    /**
     * @param string   $message      Human-readable summary
     * @param int      $statusCode   HTTP status code (0 = no response)
     * @param string[] $errors       Error codes / keys from the BackendResponse
     * @param string   $endpoint     API path that was called
     * @param int|null $areaId       Area ID involved in the request (if applicable)
     */
    public function __construct(
        string               $message,
        public readonly int    $statusCode,
        public readonly array  $errors,
        public readonly string $endpoint,
        public readonly ?int   $areaId = null,
    ) {
        parent::__construct($message);
    }

    public static function fromBackendResponse(
        BackendResponse $response,
        string $endpoint,
        ?int $areaId = null,
    ): self {
        return new self(
            message:    $response->message ?: 'API-Fehler',
            statusCode: $response->statusCode,
            errors:     $response->errors,
            endpoint:   $endpoint,
            areaId:     $areaId,
        );
    }
}
