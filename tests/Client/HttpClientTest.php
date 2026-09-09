<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\Client;

use BradSearch\SyncSdk\Client\HttpClient;
use BradSearch\SyncSdk\Client\RetryPolicy;
use BradSearch\SyncSdk\Client\Transport\HttpResponse;
use BradSearch\SyncSdk\Config\SyncConfig;
use BradSearch\SyncSdk\Exceptions\ApiException;
use BradSearch\SyncSdk\Exceptions\FailureClass;
use BradSearch\SyncSdk\Exceptions\NotFoundException;
use BradSearch\SyncSdk\Exceptions\TransientApiException;
use BradSearch\SyncSdk\Exceptions\TransportException;
use BradSearch\SyncSdk\Exceptions\UnauthorizedException;
use BradSearch\SyncSdk\Exceptions\ValidationFailedException;
use BradSearch\SyncSdk\Tests\Client\Support\FakeSleeper;
use BradSearch\SyncSdk\Tests\Client\Support\FakeTransport;
use PHPUnit\Framework\TestCase;

/**
 * AC-6 / AC-8 / AC-9: the HttpClient maps a non-2xx response to a typed exception,
 * chosen from the response `code` first and the HTTP status second.
 */
class HttpClientTest extends TestCase
{
    private FakeSleeper $sleeper;

    protected function setUp(): void
    {
        $this->sleeper = new FakeSleeper();
    }

    /**
     * @param list<HttpResponse|TransportException> $outcomes
     */
    private function client(array $outcomes): HttpClient
    {
        $config = new SyncConfig(
            baseUrl: 'https://api.example.com',
            authToken: 'token',
            retryPolicy: RetryPolicy::none(),
        );

        return new HttpClient($config, new FakeTransport($outcomes), $this->sleeper);
    }

    /**
     * @return list<array{int, string, class-string<ApiException>, FailureClass}>
     */
    public static function statusMappingProvider(): array
    {
        return [
            '422 is a validation failure' => [
                422, '', ValidationFailedException::class, FailureClass::PermanentItem,
            ],
            '400 is a validation failure' => [
                400, '', ValidationFailedException::class, FailureClass::PermanentItem,
            ],
            '404 is not found' => [
                404, '', NotFoundException::class, FailureClass::PermanentConfig,
            ],
            '401 is unauthorized' => [
                401, '', UnauthorizedException::class, FailureClass::PermanentConfig,
            ],
            '403 is unauthorized' => [
                403, '', UnauthorizedException::class, FailureClass::PermanentConfig,
            ],
            '429 is transient' => [
                429, '', TransientApiException::class, FailureClass::Transient,
            ],
            '500 is transient' => [
                500, '', TransientApiException::class, FailureClass::Transient,
            ],
            '502 is transient' => [
                502, '', TransientApiException::class, FailureClass::Transient,
            ],
            '503 is transient' => [
                503, '', TransientApiException::class, FailureClass::Transient,
            ],
        ];
    }

    /**
     * @param class-string<ApiException> $expectedClass
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('statusMappingProvider')]
    public function testMapsStatusToTypedException(
        int $status,
        string $body,
        string $expectedClass,
        FailureClass $expectedFailureClass,
    ): void {
        $client = $this->client([new HttpResponse($status, $body)]);

        try {
            $client->get('api/v2/thing');
            $this->fail('expected an ApiException');
        } catch (ApiException $e) {
            $this->assertInstanceOf($expectedClass, $e);
            $this->assertSame($expectedFailureClass, $e->failureClass());
            $this->assertSame($status, $e->statusCode);
        }
    }

    /**
     * The engine `code` wins over the status, so a new code lands on the right
     * exception even when the status is ambiguous.
     */
    public function testCodeWinsOverStatus(): void
    {
        $client = $this->client([
            new HttpResponse(422, '{"status":"error","error":"Configuration is malformed","code":"config_malformed"}'),
        ]);

        try {
            $client->get('api/v2/thing');
            $this->fail('expected an ApiException');
        } catch (ApiException $e) {
            $this->assertInstanceOf(ValidationFailedException::class, $e);
            // config_malformed is a broken tenant config, not one bad product.
            $this->assertSame(FailureClass::PermanentConfig, $e->failureClass());
            $this->assertSame('config_malformed', $e->errorCode);
        }
    }

    public function testValidationErrorCodeIsPermanentItem(): void
    {
        $client = $this->client([
            new HttpResponse(422, '{"status":"error","error":"Invalid product","code":"validation_error"}'),
        ]);

        try {
            $client->get('api/v2/thing');
            $this->fail('expected an ApiException');
        } catch (ApiException $e) {
            $this->assertInstanceOf(ValidationFailedException::class, $e);
            $this->assertSame(FailureClass::PermanentItem, $e->failureClass());
            $this->assertSame('validation_error', $e->errorCode);
        }
    }

    public function testBackendUnavailableCodeOnA500IsTransient(): void
    {
        $client = $this->client([
            new HttpResponse(500, '{"status":"error","error":"boom","code":"backend_unavailable"}'),
        ]);

        try {
            $client->get('api/v2/thing');
            $this->fail('expected an ApiException');
        } catch (ApiException $e) {
            $this->assertInstanceOf(TransientApiException::class, $e);
            $this->assertSame(FailureClass::Transient, $e->failureClass());
            $this->assertSame('backend_unavailable', $e->errorCode);
        }
    }

    public function testConfigNotFoundCodeIsPermanentConfig(): void
    {
        $client = $this->client([
            new HttpResponse(404, '{"status":"error","error":"Configuration not found","code":"config_not_found"}'),
        ]);

        try {
            $client->get('api/v2/thing');
            $this->fail('expected an ApiException');
        } catch (ApiException $e) {
            $this->assertInstanceOf(NotFoundException::class, $e);
            $this->assertSame(FailureClass::PermanentConfig, $e->failureClass());
        }
    }

    /**
     * An older engine sends no code at all. The status must still classify.
     */
    public function testMissingCodeFallsBackToStatus(): void
    {
        $client = $this->client([
            new HttpResponse(422, '{"status":"error","error":"Invalid JSON"}'),
        ]);

        try {
            $client->get('api/v2/thing');
            $this->fail('expected an ApiException');
        } catch (ApiException $e) {
            $this->assertInstanceOf(ValidationFailedException::class, $e);
            $this->assertNull($e->errorCode);
        }
    }

    /**
     * A body that is not JSON at all (an HTML error page from a proxy, say) must
     * not break classification.
     */
    public function testNonJsonBodyStillClassifiesByStatus(): void
    {
        $client = $this->client([
            new HttpResponse(503, '<html><body>Service Unavailable</body></html>'),
        ]);

        try {
            $client->get('api/v2/thing');
            $this->fail('expected an ApiException');
        } catch (ApiException $e) {
            $this->assertInstanceOf(TransientApiException::class, $e);
            $this->assertSame(FailureClass::Transient, $e->failureClass());
            $this->assertNull($e->errorCode);
        }
    }

    /**
     * An unrecognised code must not be trusted over the status.
     */
    public function testUnknownCodeFallsBackToStatus(): void
    {
        $client = $this->client([
            new HttpResponse(404, '{"status":"error","error":"nope","code":"something_new"}'),
        ]);

        try {
            $client->get('api/v2/thing');
            $this->fail('expected an ApiException');
        } catch (ApiException $e) {
            $this->assertInstanceOf(NotFoundException::class, $e);
            $this->assertSame(FailureClass::PermanentConfig, $e->failureClass());
        }
    }

    /**
     * AC-6: a cURL connect failure or timeout is TRANSIENT.
     */
    public function testTransportExceptionIsTransient(): void
    {
        $client = $this->client([
            new TransportException('connect timeout', CURLE_OPERATION_TIMEDOUT, true),
        ]);

        try {
            $client->get('api/v2/thing');
            $this->fail('expected an ApiException');
        } catch (ApiException $e) {
            $this->assertInstanceOf(TransportException::class, $e);
            $this->assertSame(FailureClass::Transient, $e->failureClass());
        }
    }

    /**
     * AC-8: existing catch (ApiException) code keeps working, and statusCode plus
     * responseBody stay available.
     */
    public function testEveryTypedExceptionIsStillAnApiException(): void
    {
        foreach ([422, 404, 401, 429, 500] as $status) {
            $client = $this->client([new HttpResponse($status, 'the body')]);

            try {
                $client->get('api/v2/thing');
                $this->fail('expected an ApiException');
            } catch (ApiException $e) {
                $this->assertSame($status, $e->statusCode);
                $this->assertSame('the body', $e->responseBody);
                $this->assertStringContainsString((string) $status, $e->getMessage());
            }
        }
    }

    /**
     * A JSON body that decodes to something other than an object must not be
     * mistaken for an error envelope.
     */
    public function testScalarJsonBodyIsIgnoredForCodeExtraction(): void
    {
        $client = $this->client([new HttpResponse(422, '"just a string"')]);

        try {
            $client->get('api/v2/thing');
            $this->fail('expected an ApiException');
        } catch (ApiException $e) {
            $this->assertInstanceOf(ValidationFailedException::class, $e);
            $this->assertNull($e->errorCode);
        }
    }

    /**
     * A 2xx response is untouched by the mapping work.
     */
    public function testSuccessfulResponseIsUnaffected(): void
    {
        $client = $this->client([new HttpResponse(200, '{"ok":true}')]);

        $this->assertSame(['ok' => true], $client->get('api/v2/thing'));
    }

    /**
     * A decode failure on a 2xx body still raises the base ApiException, with no
     * failure class promise beyond TRANSIENT.
     */
    public function testMalformedSuccessBodyThrowsApiException(): void
    {
        $client = $this->client([new HttpResponse(200, 'not json')]);

        $this->expectException(ApiException::class);
        $client->get('api/v2/thing');
    }
}
