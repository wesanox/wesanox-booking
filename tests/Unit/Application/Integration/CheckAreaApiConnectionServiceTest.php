<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Application\Integration;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Application\Integration\CheckAreaApiConnectionService;
use Wesanox\Booking\Infrastructure\WesanoxApi\BackendResponse;
use Wesanox\Booking\Infrastructure\WesanoxApi\WesanoxApiBridgeInterface;

final class CheckAreaApiConnectionServiceTest extends TestCase
{
    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeBridge(bool $available, BackendResponse $pingResponse): WesanoxApiBridgeInterface
    {
        $mock = $this->createMock(WesanoxApiBridgeInterface::class);
        $mock->method('isAvailable')->willReturn($available);
        $mock->method('ping')->willReturn($pingResponse);
        return $mock;
    }

    // ── tests ─────────────────────────────────────────────────────────────────

    public function testReturnsPluginNotAvailableWhenBridgeUnavailable(): void
    {
        $bridge  = $this->createMock(WesanoxApiBridgeInterface::class);
        $bridge->method('isAvailable')->willReturn(false);

        $svc      = new CheckAreaApiConnectionService($bridge);
        $response = $svc->execute(1);

        self::assertFalse($response->success);
        self::assertContains('plugin_unavailable', $response->errors);
    }

    public function testReturnsPingResponseWhenAvailable(): void
    {
        $pong   = new BackendResponse(true, null, 'pong', [], 200);
        $bridge = $this->makeBridge(true, $pong);

        $svc      = new CheckAreaApiConnectionService($bridge);
        $response = $svc->execute(3);

        self::assertTrue($response->isOk());
    }

    public function testPassesCredentialIdToPing(): void
    {
        $bridge = $this->createMock(WesanoxApiBridgeInterface::class);
        $bridge->method('isAvailable')->willReturn(true);
        $bridge->expects(self::once())
               ->method('ping')
               ->with(self::equalTo(7))
               ->willReturn(new BackendResponse(true, null, '', [], 200));

        $svc = new CheckAreaApiConnectionService($bridge);
        $svc->execute(7);
    }
}
