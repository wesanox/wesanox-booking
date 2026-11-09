<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Domain\Booking;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Domain\Booking\SpecialsCustomerNotice;

final class SpecialsCustomerNoticeTest extends TestCase
{
    public function testOrderMetaKeyAndLabelMatchTheLaravelBookingImport(): void
    {
        self::assertSame('_wesanox_allergy_notice', SpecialsCustomerNotice::ORDER_META_KEY);
        self::assertSame('Hinweise zu Allergien und Unverträglichkeiten bei Specials:', SpecialsCustomerNotice::LABEL);
    }

    public function testItAddsSpecialsNoticeWithoutExistingCustomerNotice(): void
    {
        self::assertSame(
            "Hinweise zu Allergien und Unverträglichkeiten bei Specials:\nKeine Ananas und keine Honigmelone",
            SpecialsCustomerNotice::merge(null, 'Keine Ananas und keine Honigmelone'),
        );
    }

    public function testItPreservesExistingCustomerNotice(): void
    {
        self::assertSame(
            "Bitte leise anklopfen.\n\nHinweise zu Allergien und Unverträglichkeiten bei Specials:\nKeine Ananas",
            SpecialsCustomerNotice::merge('Bitte leise anklopfen.', 'Keine Ananas'),
        );
    }

    public function testItReplacesOnlyTheExistingSpecialsSection(): void
    {
        $existing = "Bitte leise anklopfen.\n\nHinweise zu Allergien und Unverträglichkeiten bei Specials:\nKeine Ananas";

        self::assertSame(
            "Bitte leise anklopfen.\n\nHinweise zu Allergien und Unverträglichkeiten bei Specials:\nKeine Honigmelone",
            SpecialsCustomerNotice::merge($existing, 'Keine Honigmelone'),
        );
    }

    public function testRepeatedMergeDoesNotDuplicateTheSpecialsSection(): void
    {
        $first = SpecialsCustomerNotice::merge('Bitte leise anklopfen.', 'Keine Ananas');

        self::assertSame($first, SpecialsCustomerNotice::merge($first, 'Keine Ananas'));
        self::assertSame(1, substr_count($first, SpecialsCustomerNotice::LABEL));
    }

    #[DataProvider('emptySpecialsNoticeProvider')]
    public function testEmptySpecialsNoticeDoesNotChangeExistingCustomerNotice(?string $specialsNotice): void
    {
        $customerNotice = "  Bitte leise anklopfen.\n";

        self::assertSame(
            $customerNotice,
            SpecialsCustomerNotice::merge($customerNotice, $specialsNotice),
        );
    }

    /** @return array<string, array{0: string|null}> */
    public static function emptySpecialsNoticeProvider(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'whitespace' => [" \n "],
        ];
    }

    public function testItPreservesUmlautsAndLineBreaks(): void
    {
        self::assertStringEndsWith(
            "Keine Ananas.\nHonigmelone wird nicht vertragen. Grüße!",
            SpecialsCustomerNotice::merge('', "Keine Ananas.\nHonigmelone wird nicht vertragen. Grüße!"),
        );
    }
}
