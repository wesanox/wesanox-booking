<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Application\Integration;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Application\Integration\FetchCancellationPolicyService;
use Wesanox\Booking\Infrastructure\WesanoxApi\BackendResponse;
use Wesanox\Booking\Infrastructure\WesanoxApi\WesanoxApiBridgeInterface;

final class FetchCancellationPolicyServiceTest extends TestCase
{
    public function testPostsOrderIdToCancellationPolicyEndpoint(): void
    {
        $bridge = $this->createMock(WesanoxApiBridgeInterface::class);
        $bridge->method('isAvailable')->willReturn(true);
        $bridge->expects(self::once())
            ->method('post')
            ->with(
                self::equalTo(5),
                self::equalTo('/v1/woo/orders/cancellation-policy'),
                self::equalTo(['order_id' => 42])
            )
            ->willReturn(new BackendResponse(true, ['can_cancel' => true], '', [], 200));

        (new FetchCancellationPolicyService($bridge, 5))->execute(42);
    }

    public function testMapsBackendDecision(): void
    {
        $result = $this->serviceReturning(new BackendResponse(true, [
            'can_cancel'               => true,
            'reason'                   => null,
            'cancel_until'             => '2026-10-18T12:00:00+02:00',
            'cutoff_hours'             => 48,
            'has_withdrawal_insurance' => false,
        ], '', [], 200))->execute(42);

        self::assertTrue($result->available);
        self::assertTrue($result->canCancelDirectly());
        self::assertSame(48, $result->cutoff_hours);
        self::assertSame('2026-10-18 12:00', $result->cancel_until?->format('Y-m-d H:i'));
    }

    public function testDeniedDecisionKeepsReason(): void
    {
        $result = $this->serviceReturning(new BackendResponse(true, [
            'can_cancel'               => false,
            'reason'                   => 'cancellation_deadline_passed',
            'cancel_until'             => null,
            'cutoff_hours'             => 24,
            'has_withdrawal_insurance' => false,
        ], '', [], 200))->execute(42);

        self::assertFalse($result->canCancelDirectly());
        self::assertSame('cancellation_deadline_passed', $result->reason);
        self::assertSame(24, $result->cutoff_hours);
    }

    public function testBackendErrorFallsBackToCancellationRequest(): void
    {
        $result = $this->serviceReturning(new BackendResponse(false, null, 'Server error', [], 500))->execute(42);

        self::assertFalse($result->available);
        self::assertFalse($result->canCancelDirectly());
    }

    public function testUnavailableBridgeFallsBackToCancellationRequest(): void
    {
        $bridge = $this->createMock(WesanoxApiBridgeInterface::class);
        $bridge->method('isAvailable')->willReturn(false);
        $bridge->expects(self::never())->method('post');

        $result = (new FetchCancellationPolicyService($bridge, 5))->execute(42);

        self::assertFalse($result->available);
        self::assertFalse($result->canCancelDirectly());
    }

    private function serviceReturning(BackendResponse $response): FetchCancellationPolicyService
    {
        $bridge = $this->createMock(WesanoxApiBridgeInterface::class);
        $bridge->method('isAvailable')->willReturn(true);
        $bridge->method('post')->willReturn($response);

        return new FetchCancellationPolicyService($bridge, 5);
    }
}
