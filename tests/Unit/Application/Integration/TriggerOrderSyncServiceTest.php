<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Application\Integration;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Application\Integration\TriggerOrderSyncService;
use Wesanox\Booking\Infrastructure\WesanoxApi\BackendResponse;
use Wesanox\Booking\Infrastructure\WesanoxApi\WesanoxApiException;
use Wesanox\Booking\Infrastructure\WesanoxApi\WesanoxApiBridgeInterface;

final class TriggerOrderSyncServiceTest extends TestCase
{
    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeService(BackendResponse $response): TriggerOrderSyncService
    {
        $bridge = $this->createMock(WesanoxApiBridgeInterface::class);
        $bridge->method('isAvailable')->willReturn(true);
        $bridge->method('post')->willReturn($response);
        return new TriggerOrderSyncService($bridge);
    }

    // ── plugin not available ──────────────────────────────────────────────────

    public function testReturnsPluginNotAvailableWhenBridgeUnavailable(): void
    {
        $bridge = $this->createMock(WesanoxApiBridgeInterface::class);
        $bridge->method('isAvailable')->willReturn(false);

        $svc      = new TriggerOrderSyncService($bridge);
        $response = $svc->execute(123, 1);

        self::assertFalse($response->success);
        self::assertContains('plugin_unavailable', $response->errors);
    }

    // ── success ───────────────────────────────────────────────────────────────

    public function testReturnsSuccessResponseOnOkResult(): void
    {
        $data     = ['sync_id' => 7, 'sync_status' => 'synced', 'woo_order_id' => 123];
        $response = new BackendResponse(true, $data, 'Order synchronisiert', [], 200);
        $svc      = $this->makeService($response);

        $result = $svc->execute(123, 1);

        self::assertTrue($result->isOk());
        self::assertSame(7, $result->data['sync_id']);
    }

    // ── failure ───────────────────────────────────────────────────────────────

    public function testThrowsWesanoxApiExceptionOnNonOkResponse(): void
    {
        $this->expectException(WesanoxApiException::class);

        $svc = $this->makeService(new BackendResponse(false, null, 'Not found', [], 404));
        $svc->execute(123, 1);
    }

    // ── request shape ─────────────────────────────────────────────────────────

    public function testPostsToCorrectEndpoint(): void
    {
        $bridge = $this->createMock(WesanoxApiBridgeInterface::class);
        $bridge->method('isAvailable')->willReturn(true);
        $bridge->expects(self::once())
               ->method('post')
               ->with(
                   self::equalTo(3),
                   self::equalTo('/v1/woo/orders/process'),
                   self::callback(fn($b) => $b['order_id'] === 42 && !array_key_exists('api_id', $b))
               )
               ->willReturn(new BackendResponse(true, null, '', [], 200));

        $svc = new TriggerOrderSyncService($bridge);
        $svc->execute(42, 3);
    }

    public function testExceptionEndpointIsWooOrdersProcess(): void
    {
        $bridge = $this->createMock(WesanoxApiBridgeInterface::class);
        $bridge->method('isAvailable')->willReturn(true);
        $bridge->method('post')->willReturn(new BackendResponse(false, null, 'err', [], 500));

        try {
            (new TriggerOrderSyncService($bridge))->execute(1, 1);
            self::fail('Expected exception');
        } catch (WesanoxApiException $e) {
            self::assertSame('/v1/woo/orders/process', $e->endpoint);
        }
    }
}
