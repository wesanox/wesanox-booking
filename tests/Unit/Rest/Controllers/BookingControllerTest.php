<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Rest\Controllers;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Wesanox\Booking\Rest\Controllers\BookingController;

/**
 * Tests for BookingController validation logic.
 * These are pure unit tests — no WordPress or database required.
 */
final class BookingControllerTest extends TestCase
{
    private BookingController $controller;
    private \ReflectionMethod $normalizeTime;
    private \ReflectionMethod $isValidDate;
    private \ReflectionMethod $isValidTime;
    private \ReflectionMethod $validateCreateParams;
    private \ReflectionMethod $syncWooOrderBookingMeta;

    protected function setUp(): void
    {
        $this->controller = new BookingController();
        $GLOBALS['wesanox_test_wc_orders'] = [];

        $ref = new ReflectionClass(BookingController::class);

        $this->normalizeTime = $ref->getMethod('normalizeTime');
        $this->normalizeTime->setAccessible(true);

        $this->isValidDate = $ref->getMethod('isValidDate');
        $this->isValidDate->setAccessible(true);

        $this->isValidTime = $ref->getMethod('isValidTime');
        $this->isValidTime->setAccessible(true);

        $this->validateCreateParams = $ref->getMethod('validateCreateParams');
        $this->validateCreateParams->setAccessible(true);

        $this->syncWooOrderBookingMeta = $ref->getMethod('syncWooOrderBookingMeta');
        $this->syncWooOrderBookingMeta->setAccessible(true);
    }

    // --- normalizeTime ---

    public function test_it_returns_null_for_null_input(): void
    {
        $result = $this->normalizeTime->invoke($this->controller, null);

        self::assertNull($result);
    }

    public function test_it_returns_null_for_empty_string(): void
    {
        $result = $this->normalizeTime->invoke($this->controller, '');

        self::assertNull($result);
    }

    public function test_it_passes_through_full_his_format(): void
    {
        $result = $this->normalizeTime->invoke($this->controller, '14:30:00');

        self::assertSame('14:30:00', $result);
    }

    public function test_it_appends_seconds_to_hi_format(): void
    {
        $result = $this->normalizeTime->invoke($this->controller, '14:30');

        self::assertSame('14:30:00', $result);
    }

    public function test_it_returns_null_for_invalid_format(): void
    {
        $result = $this->normalizeTime->invoke($this->controller, 'not-a-time');

        self::assertNull($result);
    }

    // --- isValidDate ---

    public function test_it_accepts_valid_date(): void
    {
        $result = $this->isValidDate->invoke($this->controller, '2025-06-15');

        self::assertTrue($result);
    }

    public function test_it_rejects_invalid_date(): void
    {
        $result = $this->isValidDate->invoke($this->controller, '2025-13-01');

        self::assertFalse($result);
    }

    public function test_it_rejects_wrong_date_format(): void
    {
        $result = $this->isValidDate->invoke($this->controller, '15.06.2025');

        self::assertFalse($result);
    }

    // --- isValidTime ---

    public function test_it_accepts_valid_time(): void
    {
        $result = $this->isValidTime->invoke($this->controller, '14:30:00');

        self::assertTrue($result);
    }

    public function test_it_rejects_invalid_time(): void
    {
        $result = $this->isValidTime->invoke($this->controller, '25:00:00');

        self::assertFalse($result);
    }

    public function test_it_rejects_hi_format_without_seconds(): void
    {
        $result = $this->isValidTime->invoke($this->controller, '14:30');

        self::assertFalse($result);
    }

    // --- validateCreateParams ---

    public function test_it_returns_no_errors_for_valid_params(): void
    {
        $errors = $this->validateCreateParams->invoke(
            $this->controller,
            '2025-06-15',
            '10:00:00',
            '12:00:00'
        );

        self::assertEmpty($errors);
    }

    public function test_it_returns_error_for_invalid_date(): void
    {
        $errors = $this->validateCreateParams->invoke(
            $this->controller,
            'not-a-date',
            '10:00:00',
            '12:00:00'
        );

        self::assertArrayHasKey('booking_date', $errors);
    }

    public function test_it_returns_error_for_null_booking_from(): void
    {
        $errors = $this->validateCreateParams->invoke(
            $this->controller,
            '2025-06-15',
            null,
            '12:00:00'
        );

        self::assertArrayHasKey('booking_from', $errors);
    }

    public function test_it_returns_error_when_to_is_before_from(): void
    {
        $errors = $this->validateCreateParams->invoke(
            $this->controller,
            '2025-06-15',
            '14:00:00',
            '10:00:00'
        );

        self::assertArrayHasKey('time_range', $errors);
    }

    public function test_it_returns_error_when_to_equals_from(): void
    {
        $errors = $this->validateCreateParams->invoke(
            $this->controller,
            '2025-06-15',
            '10:00:00',
            '10:00:00'
        );

        self::assertArrayHasKey('time_range', $errors);
    }

    public function test_it_collects_multiple_errors(): void
    {
        $errors = $this->validateCreateParams->invoke(
            $this->controller,
            'bad-date',
            null,
            null
        );

        self::assertArrayHasKey('booking_date', $errors);
        self::assertArrayHasKey('booking_from', $errors);
        self::assertArrayHasKey('booking_to', $errors);
    }

    public function test_it_updates_woo_order_booking_meta_when_room_changes(): void
    {
        $order = new \Wesanox_Test_WC_Order([
            '_booking_room_id' => 3,
            '_booking_item_id' => 3,
        ]);

        $GLOBALS['wesanox_test_wc_orders'][99] = $order;

        $this->syncWooOrderBookingMeta->invoke($this->controller, [
            'id' => 12,
            'wc_order_id' => 99,
            'room_id' => 7,
            'booking_date' => '2026-06-01',
            'booking_from' => '10:00:00',
            'booking_to' => '12:00:00',
        ]);

        self::assertSame(7, $order->meta['_booking_room_id']);
        self::assertSame(7, $order->meta['_booking_item_id']);
        self::assertSame(12, $order->meta['_booking_id']);
        self::assertSame('2026-06-01', $order->meta['_booking_date']);
        self::assertSame('10:00:00', $order->meta['_booking_from']);
        self::assertSame('12:00:00', $order->meta['_booking_to']);
        self::assertSame(1, $order->saveCount);
    }

    public function test_it_does_not_save_woo_order_when_booking_meta_is_current(): void
    {
        $order = new \Wesanox_Test_WC_Order([
            '_booking_room_id' => 7,
            '_booking_item_id' => 7,
            '_booking_id' => 12,
            '_booking_date' => '2026-06-01',
            '_booking_from' => '10:00:00',
            '_booking_to' => '12:00:00',
        ]);

        $GLOBALS['wesanox_test_wc_orders'][99] = $order;

        $this->syncWooOrderBookingMeta->invoke($this->controller, [
            'id' => 12,
            'wc_order_id' => 99,
            'room_id' => 7,
            'booking_date' => '2026-06-01',
            'booking_from' => '10:00:00',
            'booking_to' => '12:00:00',
        ]);

        self::assertSame(0, $order->saveCount);
    }
}
