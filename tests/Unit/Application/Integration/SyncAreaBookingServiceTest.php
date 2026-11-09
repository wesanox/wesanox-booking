<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Application\Integration;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Application\Integration\SyncAreaBookingService;
use Wesanox\Booking\Infrastructure\WesanoxApi\BackendResponse;
use Wesanox\Booking\Infrastructure\WesanoxApi\WesanoxApiException;
use Wesanox\Booking\Infrastructure\WesanoxApi\WesanoxApiBridgeInterface;

final class SyncAreaBookingServiceTest extends TestCase
{
    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeService(BackendResponse $response): SyncAreaBookingService
    {
        $bridge = $this->createMock(WesanoxApiBridgeInterface::class);
        $bridge->method('post')->willReturn($response);
        return new SyncAreaBookingService($bridge);
    }

    private function payload(): array
    {
        return ['date' => '2025-06-15', 'start' => '14:00', 'end' => '17:00'];
    }

    // ── success ───────────────────────────────────────────────────────────────

    public function testReturnsBackendResponseOnSuccess(): void
    {
        $response = new BackendResponse(true, ['booking_id' => 99], 'created', [], 201);
        $svc      = $this->makeService($response);

        $result = $svc->execute(1, 'suite-1', 5, $this->payload());

        self::assertTrue($result->isOk());
        self::assertSame(['booking_id' => 99], $result->data);
    }

    // ── failure ───────────────────────────────────────────────────────────────

    public function testThrowsWesanoxApiExceptionOnFailedResponse(): void
    {
        $this->expectException(WesanoxApiException::class);

        $svc = $this->makeService(new BackendResponse(false, null, 'conflict', [], 409));
        $svc->execute(1, 'suite-1', 5, $this->payload());
    }

    // ── request body ─────────────────────────────────────────────────────────

    public function testMergesExternalAreaIdIntoBody(): void
    {
        $bridge = $this->createMock(WesanoxApiBridgeInterface::class);
        $bridge->expects(self::once())
               ->method('post')
               ->with(
                   self::equalTo(3),
                   self::equalTo('/bookings'),
                   self::callback(fn($body) => $body['area_id'] === 'suite-1')
               )
               ->willReturn(new BackendResponse(true, null, '', [], 201));

        $svc = new SyncAreaBookingService($bridge);
        $svc->execute(3, 'suite-1', 5, $this->payload());
    }
}
