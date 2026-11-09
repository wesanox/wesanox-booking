<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Domain\Booking;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Wesanox\Booking\Domain\Booking\BookingStatus;

final class BookingStatusTest extends TestCase
{
    // --- fromWcStatus ---

    public function testItReturnsNoOrderForNull(): void
    {
        self::assertSame(BookingStatus::NO_ORDER, BookingStatus::fromWcStatus(null));
    }

    public function testItReturnsNoOrderForEmptyString(): void
    {
        self::assertSame(BookingStatus::NO_ORDER, BookingStatus::fromWcStatus(''));
    }

    #[DataProvider('wcStatusProvider')]
    public function testItMapsWcStatusToCanonical(string $wc_status, string $expected): void
    {
        self::assertSame($expected, BookingStatus::fromWcStatus($wc_status));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function wcStatusProvider(): array
    {
        return [
            'pending with prefix'    => ['wc-pending',    BookingStatus::PENDING],
            'processing with prefix' => ['wc-processing', BookingStatus::PROCESSING],
            'completed with prefix'  => ['wc-completed',  BookingStatus::COMPLETED],
            'cancelled with prefix'  => ['wc-cancelled',  BookingStatus::CANCELLED],
            'refunded with prefix'   => ['wc-refunded',   BookingStatus::REFUNDED],
            'failed with prefix'     => ['wc-failed',     BookingStatus::FAILED],
            'pending no prefix'      => ['pending',        BookingStatus::PENDING],
            'completed no prefix'    => ['completed',      BookingStatus::COMPLETED],
            'unknown status'         => ['wc-on-hold',     BookingStatus::PENDING],
        ];
    }

    // --- label ---

    public function testItReturnsGermanLabelForKnownStatus(): void
    {
        self::assertSame('Ausstehend',      BookingStatus::label(BookingStatus::PENDING));
        self::assertSame('In Bearbeitung',  BookingStatus::label(BookingStatus::PROCESSING));
        self::assertSame('Abgeschlossen',   BookingStatus::label(BookingStatus::COMPLETED));
        self::assertSame('Storniert',       BookingStatus::label(BookingStatus::CANCELLED));
        self::assertSame('Ohne Bestellung', BookingStatus::label(BookingStatus::NO_ORDER));
    }

    public function testItFallsBackToStatusKeyForUnknownLabel(): void
    {
        self::assertSame('unknown-xyz', BookingStatus::label('unknown-xyz'));
    }

    // --- badgeClass ---

    public function testItReturnsBadgeClassForPending(): void
    {
        self::assertSame('order-status-pending', BookingStatus::badgeClass(BookingStatus::PENDING));
    }

    public function testItReturnsBadgeClassForCompleted(): void
    {
        self::assertSame('order-status-completed', BookingStatus::badgeClass(BookingStatus::COMPLETED));
    }

    // --- all ---

    public function testItReturnsAllStatusesAsArray(): void
    {
        $all = BookingStatus::all();

        self::assertIsArray($all);
        self::assertArrayHasKey(BookingStatus::PENDING,  $all);
        self::assertArrayHasKey(BookingStatus::CANCELLED, $all);
        self::assertArrayHasKey(BookingStatus::NO_ORDER,  $all);
    }
}
