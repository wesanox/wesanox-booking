<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Domain\Booking;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Domain\Booking\BookingOccupancy;

final class BookingOccupancyTest extends TestCase
{
    public function test_sql_excludes_cancelled_bookings_but_keeps_null_status(): void
    {
        self::assertSame(
            '(b.booking_status IS NULL OR b.booking_status <> 3)',
            BookingOccupancy::notCancelledSql('b')
        );
    }

    public function test_sql_uses_the_given_alias(): void
    {
        self::assertSame(
            '(bk.booking_status IS NULL OR bk.booking_status <> 3)',
            BookingOccupancy::notCancelledSql('bk')
        );
    }

    public function test_sql_strips_unsafe_characters_from_alias(): void
    {
        self::assertSame(
            '(bDROPTABLEx.booking_status IS NULL OR bDROPTABLEx.booking_status <> 3)',
            BookingOccupancy::notCancelledSql('b; DROP TABLE x')
        );
        self::assertSame(
            '(b.booking_status IS NULL OR b.booking_status <> 3)',
            BookingOccupancy::notCancelledSql('`;')
        );
    }

    #[DataProvider('statusProvider')]
    public function test_only_cancelled_bookings_free_the_room(?int $status, bool $blocks): void
    {
        self::assertSame($blocks, BookingOccupancy::blocksRoom($status));
    }

    /** @return array<string, array{0: int|null, 1: bool}> */
    public static function statusProvider(): array
    {
        return [
            'checkout hold / legacy row' => [null, true],
            'gebucht'                    => [0, true],
            'eingecheckt'                => [1, true],
            'ausgecheckt'                => [2, true],
            'storniert'                  => [3, false],
            'in bearbeitung'             => [4, true],
            'blockiert'                  => [5, true],
        ];
    }
}
