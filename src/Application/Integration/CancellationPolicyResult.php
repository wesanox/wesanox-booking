<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Integration;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use Exception;

/**
 * Cancellation policy of a WooCommerce order as decided by the Laravel backend.
 *
 * When the backend could not be asked ($available === false) the frontend must not
 * cancel directly and only offers a cancellation request.
 */
final class CancellationPolicyResult
{
    public function __construct(
        public readonly bool $available,
        public readonly bool $can_cancel,
        public readonly ?string $reason,
        public readonly ?DateTimeImmutable $cancel_until,
        public readonly ?int $cutoff_hours,
        public readonly bool $has_withdrawal_insurance,
    ) {
    }

    public static function unavailable(): self
    {
        return new self(false, false, 'backend_unavailable', null, null, false);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromBackendData(array $data): self
    {
        return new self(
            available: true,
            can_cancel: (bool) ($data['can_cancel'] ?? false),
            reason: isset($data['reason']) ? (string) $data['reason'] : null,
            cancel_until: self::parseDate($data['cancel_until'] ?? null),
            cutoff_hours: isset($data['cutoff_hours']) ? (int) $data['cutoff_hours'] : null,
            has_withdrawal_insurance: (bool) ($data['has_withdrawal_insurance'] ?? false),
        );
    }

    public function canCancelDirectly(): bool
    {
        return $this->available && $this->can_cancel;
    }

    private static function parseDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }
}
