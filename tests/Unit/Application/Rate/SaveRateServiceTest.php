<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Application\Rate;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Application\Rate\SaveRateService;
use Wesanox\Booking\Application\Rate\WooCommerceProductProviderInterface;
use Wesanox\Booking\Domain\ItemCategory\ItemCategory;
use Wesanox\Booking\Domain\ItemCategory\ItemCategoryRepositoryInterface;
use Wesanox\Booking\Domain\Rate\Rate;
use Wesanox\Booking\Domain\Rate\RateRepositoryInterface;
use Wesanox\Booking\Support\ValidationException;

final class SaveRateServiceTest extends TestCase
{
    private const ALL_DAYS      = Rate::WEEKDAYS;
    private const WEEKDAYS      = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    private const WEEKEND       = ['saturday', 'sunday'];

    // ── Validation: name ──────────────────────────────────────────────────────

    public function testItThrowsWhenNameIsEmpty(): void
    {
        $service = new SaveRateService($this->makeRepository(), $this->makeProducts(), $this->makeCategories());

        $this->expectException(ValidationException::class);
        $service->execute(null, 1, 1, '', '09:00', '18:00', self::ALL_DAYS, 1, null, true, 0);
    }

    public function testItThrowsWhenNameIsWhitespace(): void
    {
        $service = new SaveRateService($this->makeRepository(), $this->makeProducts(), $this->makeCategories());

        $this->expectException(ValidationException::class);
        $service->execute(null, 1, 1, '   ', '09:00', '18:00', self::ALL_DAYS, 1, null, true, 0);
    }

    // ── Validation: area ──────────────────────────────────────────────────────

    public function testItThrowsWhenAreaIdIsZero(): void
    {
        $service = new SaveRateService($this->makeRepository(), $this->makeProducts(), $this->makeCategories());

        $this->expectException(ValidationException::class);
        $service->execute(null, 0, 1, 'Early Bird', '09:00', '18:00', self::ALL_DAYS, 1, null, true, 0);
    }

    // ── Validation: item category ─────────────────────────────────────────────

    public function testItThrowsWhenCategoryIdIsZero(): void
    {
        $service = new SaveRateService($this->makeRepository(), $this->makeProducts(), $this->makeCategories());

        $this->expectException(ValidationException::class);
        $service->execute(null, 1, 0, 'Rate', '09:00', '18:00', self::ALL_DAYS, 1, null, true, 0);
    }

    public function testItThrowsWhenCategoryDoesNotExist(): void
    {
        $service = new SaveRateService($this->makeRepository(), $this->makeProducts(), $this->makeCategories(categoryExists: false));

        $this->expectException(ValidationException::class);
        $service->execute(null, 1, 99, 'Rate', '09:00', '18:00', self::ALL_DAYS, 1, null, true, 0);
    }

    // ── Validation: days ─────────────────────────────────────────────────────

    public function testItThrowsWhenDaysIsEmpty(): void
    {
        $service = new SaveRateService($this->makeRepository(), $this->makeProducts(), $this->makeCategories());

        $this->expectException(ValidationException::class);
        $service->execute(null, 1, 1, 'Rate', '09:00', '18:00', [], 1, null, true, 0);
    }

    public function testItThrowsWhenDaysContainsOnlyInvalidValues(): void
    {
        $service = new SaveRateService($this->makeRepository(), $this->makeProducts(), $this->makeCategories());

        $this->expectException(ValidationException::class);
        $service->execute(null, 1, 1, 'Rate', '09:00', '18:00', ['sunday_bad', 'not_a_day'], 1, null, true, 0);
    }

    public function testItAcceptsSingleDay(): void
    {
        $service = new SaveRateService($this->makeRepository(createWillReturn: 3), $this->makeProducts(), $this->makeCategories());

        $id = $service->execute(null, 1, 1, 'Mo only', '09:00', '18:00', ['monday'], 1, null, true, 0);

        self::assertSame(3, $id);
    }

    // ── Validation: time format ───────────────────────────────────────────────

    public function testItThrowsWhenTimeFromIsInvalid(): void
    {
        $service = new SaveRateService($this->makeRepository(), $this->makeProducts(), $this->makeCategories());

        $this->expectException(ValidationException::class);
        $service->execute(null, 1, 1, 'Early Bird', 'bad', '18:00', self::ALL_DAYS, 1, null, true, 0);
    }

    public function testItThrowsWhenTimeToIsInvalid(): void
    {
        $service = new SaveRateService($this->makeRepository(), $this->makeProducts(), $this->makeCategories());

        $this->expectException(ValidationException::class);
        $service->execute(null, 1, 1, 'Early Bird', '09:00', 'bad', self::ALL_DAYS, 1, null, true, 0);
    }

    public function testItThrowsWhenToIsBeforeFrom(): void
    {
        $service = new SaveRateService($this->makeRepository(), $this->makeProducts(), $this->makeCategories());

        $this->expectException(ValidationException::class);
        $service->execute(null, 1, 1, 'Late', '18:00', '09:00', self::ALL_DAYS, 1, null, true, 0);
    }

    public function testItThrowsWhenToEqualsFrom(): void
    {
        $service = new SaveRateService($this->makeRepository(), $this->makeProducts(), $this->makeCategories());

        $this->expectException(ValidationException::class);
        $service->execute(null, 1, 1, 'Same', '09:00', '09:00', self::ALL_DAYS, 1, null, true, 0);
    }

    public function testItAcceptsMidnightAsClosingTime(): void
    {
        $service = new SaveRateService($this->makeRepository(createWillReturn: 7), $this->makeProducts(), $this->makeCategories());

        $id = $service->execute(null, 1, 1, 'Fullday', '09:00', '00:00', self::ALL_DAYS, 1, null, true, 0);

        self::assertSame(7, $id);
    }

    // ── Validation: product ───────────────────────────────────────────────────

    public function testItThrowsWhenProductIdIsZero(): void
    {
        $service = new SaveRateService($this->makeRepository(), $this->makeProducts(), $this->makeCategories());

        $this->expectException(ValidationException::class);
        $service->execute(null, 1, 1, 'Rate', '09:00', '18:00', self::ALL_DAYS, 0, null, true, 0);
    }

    public function testItThrowsWhenProductDoesNotExist(): void
    {
        $service = new SaveRateService($this->makeRepository(), $this->makeProducts(productExists: false), $this->makeCategories());

        $this->expectException(ValidationException::class);
        $service->execute(null, 1, 1, 'Rate', '09:00', '18:00', self::ALL_DAYS, 99, null, true, 0);
    }

    public function testItThrowsWhenVariationDoesNotBelongToProduct(): void
    {
        $service = new SaveRateService(
            $this->makeRepository(),
            $this->makeProducts(productExists: true, variationBelongs: false),
            $this->makeCategories(),
        );

        $this->expectException(ValidationException::class);
        $service->execute(null, 1, 1, 'Rate', '09:00', '18:00', self::ALL_DAYS, 1, 999, true, 0);
    }

    // ── Validation: overlap ───────────────────────────────────────────────────

    public function testItThrowsWhenOverlapDetectedWithinSameCategoryAndDays(): void
    {
        // Existing rate covers all days 09:00–18:00.
        $overlapping = $this->makeRate(1, 1, '09:00', '18:00', self::ALL_DAYS);
        $service = new SaveRateService(
            $this->makeRepository(overlapping: [$overlapping]),
            $this->makeProducts(),
            $this->makeCategories(),
        );

        $this->expectException(ValidationException::class);
        // New rate also covers all days 10:00–12:00 → conflict.
        $service->execute(null, 1, 1, 'Conflict', '10:00', '12:00', self::ALL_DAYS, 1, null, true, 0);
    }

    public function testItDoesNotThrowWhenSameTimeslotIsInDifferentCategory(): void
    {
        $service = new SaveRateService(
            $this->makeRepository(overlapping: []),   // no time overlap for category 2
            $this->makeProducts(),
            $this->makeCategories(),
        );

        $id = $service->execute(null, 1, 2, 'Same Time Different Cat', '10:00', '12:00', self::ALL_DAYS, 1, null, true, 0);

        self::assertIsInt($id);
    }

    public function testItDoesNotThrowWhenSameTimeslotIsOnDifferentDays(): void
    {
        // Existing rate is Mo–Fr; new rate is Sa–So — no shared day → no conflict.
        $existing = $this->makeRate(1, 1, '09:00', '18:00', self::WEEKDAYS);
        $service  = new SaveRateService(
            $this->makeRepository(overlapping: [$existing]),   // SQL returns time-overlap hit
            $this->makeProducts(),
            $this->makeCategories(),
        );

        // Weekend rate: overlaps in time but NOT in days → should pass.
        $id = $service->execute(null, 1, 1, 'Weekend Rate', '09:00', '18:00', self::WEEKEND, 1, null, true, 0);

        self::assertIsInt($id);
    }

    public function testItThrowsWhenRatesShareOneCommonDay(): void
    {
        // Existing rate is Mo–Fr; new rate is Fr–So → Friday is shared → conflict.
        $existing = $this->makeRate(1, 1, '09:00', '18:00', self::WEEKDAYS);
        $service  = new SaveRateService(
            $this->makeRepository(overlapping: [$existing]),
            $this->makeProducts(),
            $this->makeCategories(),
        );

        $this->expectException(ValidationException::class);
        $service->execute(null, 1, 1, 'Friday overlap', '09:00', '18:00', ['friday', 'saturday', 'sunday'], 1, null, true, 0);
    }

    public function testItSkipsOverlapCheckWhenInactive(): void
    {
        $overlapping = $this->makeRate(1, 1, '09:00', '18:00', self::ALL_DAYS);
        $service = new SaveRateService(
            $this->makeRepository(overlapping: [$overlapping], createWillReturn: 5),
            $this->makeProducts(),
            $this->makeCategories(),
        );

        // is_active = false → no overlap check.
        $id = $service->execute(null, 1, 1, 'Inactive Rate', '10:00', '12:00', self::ALL_DAYS, 1, null, false, 0);

        self::assertSame(5, $id);
    }

    // ── Create & Update ───────────────────────────────────────────────────────

    public function testItReturnsNewIdOnCreate(): void
    {
        $service = new SaveRateService($this->makeRepository(createWillReturn: 42), $this->makeProducts(), $this->makeCategories());

        $id = $service->execute(null, 1, 1, 'Morning', '07:00', '12:00', self::WEEKDAYS, 1, null, true, 0);

        self::assertSame(42, $id);
    }

    public function testItReturnsExistingIdOnUpdate(): void
    {
        $service = new SaveRateService($this->makeRepository(updateWillReturn: true), $this->makeProducts(), $this->makeCategories());

        $id = $service->execute(10, 1, 1, 'Morning', '07:00', '12:00', self::WEEKDAYS, 1, null, true, 0);

        self::assertSame(10, $id);
    }

    public function testValidationCollectsMultipleErrors(): void
    {
        $service = new SaveRateService($this->makeRepository(), $this->makeProducts(), $this->makeCategories());

        try {
            $service->execute(null, 0, 0, '', 'bad', 'bad', [], 0, null, true, 0);
            self::fail('Expected ValidationException not thrown.');
        } catch (ValidationException $e) {
            self::assertGreaterThanOrEqual(2, count($e->errors()));
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @param string[] $days */
    private function makeRate(int $id, int $item_category_id, string $from, string $to, array $days = Rate::WEEKDAYS): Rate
    {
        return new Rate(
            id:               $id,
            area_id:          1,
            item_category_id: $item_category_id,
            name:             'Rate ' . $id,
            time_from:        $from,
            time_to:          $to,
            days:             $days,
            wc_product_id:    1,
            wc_variation_id:  null,
            is_active:        true,
            sort_order:       0,
        );
    }

    /**
     * @param Rate[] $overlapping
     */
    private function makeRepository(
        int   $createWillReturn = 1,
        bool  $updateWillReturn = true,
        array $overlapping      = [],
    ): RateRepositoryInterface {
        return new class ($createWillReturn, $updateWillReturn, $overlapping) implements RateRepositoryInterface {
            public function __construct(
                private int   $createReturn,
                private bool  $updateReturn,
                private array $overlappingReturn,
            ) {
            }

            public function findAll(): array  { return []; }
            public function findByArea(int $area_id): array { return []; }
            public function findActiveByArea(int $area_id): array { return []; }
            public function findActiveByAreaAndCategory(int $area_id, int $category_id): array { return []; }
            public function findById(int $id): ?Rate { return null; }

            public function findOverlapping(int $area_id, int $item_category_id, string $time_from, string $time_to, ?int $exclude_id = null): array
            {
                return $this->overlappingReturn;
            }

            public function create(array $data): int { return $this->createReturn; }
            public function update(int $id, array $data): bool { return $this->updateReturn; }
            public function delete(int $id): bool { return true; }
        };
    }

    private function makeProducts(
        bool $productExists    = true,
        bool $variationBelongs = true,
    ): WooCommerceProductProviderInterface {
        return new class ($productExists, $variationBelongs) implements WooCommerceProductProviderInterface {
            public function __construct(
                private bool $productExistsReturn,
                private bool $variationBelongsReturn,
            ) {
            }

            public function getProducts(): array { return [1 => 'Test Product']; }
            public function getVariations(int $product_id): array { return []; }
            public function productExists(int $product_id): bool { return $this->productExistsReturn; }

            public function variationBelongsToProduct(int $variation_id, int $product_id): bool
            {
                return $this->variationBelongsReturn;
            }
        };
    }

    private function makeCategories(
        bool $categoryExists = true,
    ): ItemCategoryRepositoryInterface {
        $category = $categoryExists
            ? new ItemCategory(id: 1, name: 'Standard')
            : null;

        return new class ($category) implements ItemCategoryRepositoryInterface {
            public function __construct(private ?ItemCategory $category) {}

            public function findAll(): array { return []; }
            public function findById(int $id): ?ItemCategory { return $this->category; }
            public function create(array $data): int { return 1; }
            public function update(int $id, array $data): bool { return true; }
            public function delete(int $id): bool { return true; }
        };
    }
}
