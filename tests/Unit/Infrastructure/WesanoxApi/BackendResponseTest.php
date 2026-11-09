<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Infrastructure\WesanoxApi;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Infrastructure\WesanoxApi\BackendResponse;

final class BackendResponseTest extends TestCase
{
    // ── isOk ─────────────────────────────────────────────────────────────────

    public function testIsOkReturnsTrueForSuccessful2xxResponse(): void
    {
        $r = new BackendResponse(true, null, '', [], 200);
        self::assertTrue($r->isOk());
    }

    public function testIsOkReturnsFalseFor4xxResponse(): void
    {
        $r = new BackendResponse(true, null, '', [], 404);
        self::assertFalse($r->isOk());
    }

    public function testIsOkReturnsFalseWhenSuccessFlagIsFalse(): void
    {
        $r = new BackendResponse(false, null, '', [], 200);
        self::assertFalse($r->isOk());
    }

    public function testIsOkReturnsFalseForZeroStatusCode(): void
    {
        $r = new BackendResponse(true, null, '', [], 0);
        self::assertFalse($r->isOk());
    }

    // ── factories ─────────────────────────────────────────────────────────────

    public function testPluginNotAvailableHasExpectedShape(): void
    {
        $r = BackendResponse::pluginNotAvailable();
        self::assertFalse($r->success);
        self::assertFalse($r->isOk());
        self::assertContains('plugin_unavailable', $r->errors);
    }

    public function testCredentialNotFoundHasExpectedShape(): void
    {
        $r = BackendResponse::credentialNotFound(42);
        self::assertFalse($r->success);
        self::assertStringContainsString('42', $r->message);
        self::assertContains('credential_not_found', $r->errors);
    }

    public function testFromApiResponseMapsFieldsCorrectly(): void
    {
        $stub = new class {
            public bool   $success    = true;
            public mixed  $data       = ['key' => 'value'];
            public string $message    = 'ok';
            public array  $errors     = [];
            public int    $statusCode = 201;
        };

        $r = BackendResponse::fromApiResponse($stub);

        self::assertTrue($r->success);
        self::assertSame(['key' => 'value'], $r->data);
        self::assertSame('ok', $r->message);
        self::assertSame(201, $r->statusCode);
        self::assertTrue($r->isOk());
    }
}
