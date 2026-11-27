<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Domain\Cart;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Domain\Cart\TariffQuantityAllocator;

/**
 * Regression: since the Friday afternoon tariffs (variations 253 / 256) were configured on
 * 17.11.2026, a Friday booking across 16:00 charged the afternoon tariff for the whole
 * duration and the morning tariff on top. The cart told morning from afternoon by a fixed
 * list of variation IDs (251/254 and 252/255) and gave every other ID the full duration.
 */
final class TariffQuantityAllocatorTest extends TestCase
{
    private TariffQuantityAllocator $allocator;

    protected function setUp(): void
    {
        $this->allocator = new TariffQuantityAllocator();
    }

    public function test_friday_booking_across_the_cutoff_charges_each_tariff_only_for_its_own_hours(): void
    {
        // Friday 13:45–17:45, two guests: Premium Vormittag (254) + Premium Freitag Nachmittag (256).
        $lines = $this->allocator->allocate([254, 256], 2, 2.25, 1.75);

        self::assertSame(
            [
                ['product_id' => 254, 'quantity' => 4.5],
                ['product_id' => 256, 'quantity' => 3.5],
            ],
            $lines,
        );
    }

    /** @return array<string, array{int[]}> */
    public static function tariffPairs(): array
    {
        return [
            'Classic Mo–Do' => [[251, 252]],
            'Premium Mo–Do' => [[254, 255]],
            'Classic Freitag' => [[251, 253]],
            'Premium Freitag' => [[254, 256]],
            'neu angelegte Variationen' => [[9001, 9002]],
        ];
    }

    /** @param int[] $tariff_ids */
    #[DataProvider('tariffPairs')]
    public function test_the_split_does_not_depend_on_the_variation_ids(array $tariff_ids): void
    {
        $lines = $this->allocator->allocate($tariff_ids, 1, 2.25, 1.75);

        self::assertSame(
            [
                ['product_id' => $tariff_ids[0], 'quantity' => 2.25],
                ['product_id' => $tariff_ids[1], 'quantity' => 1.75],
            ],
            $lines,
        );
    }

    public function test_charged_hours_never_exceed_the_booked_duration(): void
    {
        $lines = $this->allocator->allocate([254, 256], 3, 2.25, 1.75);

        self::assertSame(3 * 4.0, array_sum(array_column($lines, 'quantity')));
    }

    public function test_a_single_tariff_is_charged_for_the_whole_duration(): void
    {
        self::assertSame(
            [['product_id' => 256, 'quantity' => 8.0]],
            $this->allocator->allocate([256], 2, 2.25, 1.75),
        );
    }

    public function test_a_single_tariff_falls_back_to_the_booked_duration_when_the_split_is_unknown(): void
    {
        self::assertSame(
            [['product_id' => 255, 'quantity' => 6.0]],
            $this->allocator->allocate([255], 2, 0.0, 0.0, 3.0),
        );
    }

    public function test_the_fallback_duration_is_ignored_when_the_split_is_known(): void
    {
        self::assertSame(
            [['product_id' => 255, 'quantity' => 4.0]],
            $this->allocator->allocate([255], 2, 0.0, 2.0, 3.0),
        );
    }

    public function test_a_tariff_without_hours_in_its_segment_is_left_out(): void
    {
        self::assertSame(
            [['product_id' => 256, 'quantity' => 4.0]],
            $this->allocator->allocate([254, 256], 2, 0.0, 2.0),
        );
        self::assertSame(
            [['product_id' => 254, 'quantity' => 4.0]],
            $this->allocator->allocate([254, 256], 2, 2.0, 0.0),
        );
    }

    public function test_two_tariffs_are_not_allocated_when_the_split_is_unknown(): void
    {
        self::assertSame([], $this->allocator->allocate([254, 256], 2, 0.0, 0.0, 4.0));
    }

    public function test_more_than_two_tariffs_are_rejected(): void
    {
        self::assertSame([], $this->allocator->allocate([254, 255, 256], 2, 2.25, 1.75));
    }

    public function test_nothing_is_allocated_without_tariffs_guests_or_hours(): void
    {
        self::assertSame([], $this->allocator->allocate([], 2, 2.25, 1.75));
        self::assertSame([], $this->allocator->allocate([254], 0, 2.25, 1.75));
        self::assertSame([], $this->allocator->allocate([254], 2, 0.0, 0.0));
    }
}
