<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Application\Integration;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Application\Integration\FetchExternalBookingService;
use Wesanox\Booking\Domain\Integration\ExternalBookingMapper;
use Wesanox\Booking\Infrastructure\WesanoxApi\BackendResponse;
use Wesanox\Booking\Infrastructure\WesanoxApi\WesanoxApiException;
use Wesanox\Booking\Infrastructure\WesanoxApi\WesanoxApiBridgeInterface;

final class FetchExternalBookingServiceTest extends TestCase
{
    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeService(BackendResponse $response): FetchExternalBookingService
    {
        $bridge = $this->createMock(WesanoxApiBridgeInterface::class);
        $bridge->method('get')->willReturn($response);

        return new FetchExternalBookingService($bridge, new ExternalBookingMapper());
    }

    private function rawItem(string $id = 'ext-1'): array
    {
        return [
            'id'     => $id,
            'date'   => '2025-06-15',
            'start'  => '14:00',
            'end'    => '17:00',
            'status' => 'confirmed',
        ];
    }

    // ── success ───────────────────────────────────────────────────────────────

    public function testReturnsExternalBookingsOnSuccess(): void
    {
        $response = new BackendResponse(true, [$this->rawItem('e1'), $this->rawItem('e2')], 'ok', [], 200);
        $svc      = $this->makeService($response);

        $bookings = $svc->execute(1, 'suite-1', 5, '2025-06-15');

        self::assertCount(2, $bookings);
        self::assertSame('e1', $bookings[0]->externalId);
        self::assertSame('e2', $bookings[1]->externalId);
    }

    public function testReturnsEmptyArrayWhenDataIsNull(): void
    {
        $response = new BackendResponse(true, null, 'ok', [], 200);
        $svc      = $this->makeService($response);

        self::assertSame([], $svc->execute(1, 'suite-1', 5, '2025-06-15'));
    }

    // ── failure ───────────────────────────────────────────────────────────────

    public function testThrowsWesanoxApiExceptionOnFailedResponse(): void
    {
        $this->expectException(WesanoxApiException::class);

        $response = new BackendResponse(false, null, 'Not found', ['not_found'], 404);
        $svc      = $this->makeService($response);

        $svc->execute(1, 'suite-1', 5, '2025-06-15');
    }

    public function testExceptionContainsAreaId(): void
    {
        $response = new BackendResponse(false, null, 'err', [], 500);
        $bridge   = $this->createMock(WesanoxApiBridgeInterface::class);
        $bridge->method('get')->willReturn($response);

        $svc = new FetchExternalBookingService($bridge, new ExternalBookingMapper());

        try {
            $svc->execute(1, 'suite-1', 99, '2025-06-15');
            self::fail('Expected exception not thrown');
        } catch (WesanoxApiException $e) {
            self::assertSame(99, $e->areaId);
        }
    }

    // ── date_to parameter ─────────────────────────────────────────────────────

    public function testPassesDateToQueryWhenProvided(): void
    {
        $bridge = $this->createMock(WesanoxApiBridgeInterface::class);
        $bridge->expects(self::once())
               ->method('get')
               ->with(
                   self::equalTo(3),
                   self::equalTo('/bookings'),
                   self::callback(fn($q) => isset($q['date_to']) && $q['date_to'] === '2025-06-30')
               )
               ->willReturn(new BackendResponse(true, [], 'ok', [], 200));

        $svc = new FetchExternalBookingService($bridge, new ExternalBookingMapper());
        $svc->execute(3, 'r', 1, '2025-06-01', '2025-06-30');
    }
}
