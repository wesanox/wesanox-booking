<?php

declare(strict_types=1);

namespace Wesanox\Booking\Domain\Booking;

/**
 * Booking status value object.
 * Domain layer — no WordPress dependencies.
 */
final class BookingStatus
{
    public const PENDING    = 'pending';
    public const PROCESSING = 'processing';
    public const COMPLETED  = 'completed';
    public const CANCELLED  = 'cancelled';
    public const REFUNDED   = 'refunded';
    public const FAILED     = 'failed';
    public const NO_ORDER   = 'no_order';

    /** @var array<string, string> Maps WC order status (with or without "wc-" prefix) to canonical status */
    private const WC_MAP = [
        'pending'    => self::PENDING,
        'processing' => self::PROCESSING,
        'completed'  => self::COMPLETED,
        'cancelled'  => self::CANCELLED,
        'refunded'   => self::REFUNDED,
        'failed'     => self::FAILED,
    ];

    /** @var array<string, string> */
    private const LABELS = [
        self::PENDING    => 'Ausstehend',
        self::PROCESSING => 'In Bearbeitung',
        self::COMPLETED  => 'Abgeschlossen',
        self::CANCELLED  => 'Storniert',
        self::REFUNDED   => 'Erstattet',
        self::FAILED     => 'Fehlgeschlagen',
        self::NO_ORDER   => 'Ohne Bestellung',
    ];

    /** @var array<string, string> CSS badge classes for admin UI */
    private const BADGE_CLASSES = [
        self::PENDING    => 'order-status-pending',
        self::PROCESSING => 'order-status-processing',
        self::COMPLETED  => 'order-status-completed',
        self::CANCELLED  => 'order-status-cancelled',
        self::REFUNDED   => 'order-status-refunded',
        self::FAILED     => 'order-status-failed',
        self::NO_ORDER   => 'order-status-on-hold',
    ];

    public static function fromWcStatus(?string $wc_status): string
    {
        if ($wc_status === null || $wc_status === '') {
            return self::NO_ORDER;
        }

        // Strip WooCommerce prefix "wc-" if present
        $clean = str_starts_with($wc_status, 'wc-') ? substr($wc_status, 3) : $wc_status;

        return self::WC_MAP[$clean] ?? self::PENDING;
    }

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? $status;
    }

    public static function badgeClass(string $status): string
    {
        return self::BADGE_CLASSES[$status] ?? '';
    }

    /** @return array<string, string> All statuses with their labels */
    public static function all(): array
    {
        return self::LABELS;
    }
}
