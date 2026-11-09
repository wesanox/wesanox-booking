<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Application\Booking;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Application\Booking\ValidateBookingExtrasService;

final class ValidateBookingExtrasServiceTest extends TestCase
{
    private ValidateBookingExtrasService $svc;

    protected function setUp(): void
    {
        $this->svc = new ValidateBookingExtrasService();
    }

    // ── isValid ─────────────────────────────────────────────────────────

    public function testEmptyExtrasAreValid(): void
    {
        self::assertTrue($this->svc->isValid([]));
    }

    public function testAllowedProductIdsAreValid(): void
    {
        $extras = [
            ['product_id' => 92],
            ['product_id' => 93],
            ['product_id' => 94],
            ['product_id' => 95],
            ['product_id' => 96],
            ['product_id' => 235],
        ];

        self::assertTrue($this->svc->isValid($extras));
    }

    public function testForbiddenProductIdMakesExtrasInvalid(): void
    {
        $extras = [
            ['product_id' => 92],
            ['product_id' => 999], // not allowed
        ];

        self::assertFalse($this->svc->isValid($extras));
    }

    public function testStringProductIdsAreHandled(): void
    {
        self::assertTrue($this->svc->isValid([['product_id' => '92']]));
        self::assertFalse($this->svc->isValid([['product_id' => '999']]));
    }

    public function testMissingProductIdKeyIsInvalid(): void
    {
        // item without product_id key → (int)(null ?? 0) = 0 → not in whitelist
        self::assertFalse($this->svc->isValid([['variation_id' => 5]]));
    }

    // ── sanitize ────────────────────────────────────────────────────────

    public function testSanitizeKeepsAllowedItems(): void
    {
        $extras = [
            ['product_id' => 92, 'qty' => 1],
            ['product_id' => 93, 'qty' => 2],
        ];

        $result = $this->svc->sanitize($extras);

        self::assertCount(2, $result);
        self::assertSame(92, (int) $result[0]['product_id']);
        self::assertSame(93, (int) $result[1]['product_id']);
    }

    public function testSanitizeStripsForbiddenItems(): void
    {
        $extras = [
            ['product_id' => 92],
            ['product_id' => 999], // forbidden
            ['product_id' => 94],
        ];

        $result = $this->svc->sanitize($extras);

        self::assertCount(2, $result);
        $ids = array_column($result, 'product_id');
        self::assertContains(92, $ids);
        self::assertContains(94, $ids);
        self::assertNotContains(999, $ids);
    }

    public function testSanitizeReturnsSequentialKeys(): void
    {
        $extras = [
            ['product_id' => 999],
            ['product_id' => 92],
        ];

        $result = $this->svc->sanitize($extras);

        self::assertArrayHasKey(0, $result);
    }

    public function testSanitizeEmptyReturnsEmpty(): void
    {
        self::assertSame([], $this->svc->sanitize([]));
    }

    // ── allowedProductIds ───────────────────────────────────────────────

    public function testAllowedProductIdsContainsExpectedValues(): void
    {
        $allowed = ValidateBookingExtrasService::allowedProductIds();

        foreach ([92, 93, 94, 95, 96, 235] as $id) {
            self::assertContains($id, $allowed, "Product ID {$id} must be in the whitelist");
        }
    }
}
