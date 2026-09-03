<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\Client;

use BradSearch\SyncSdk\Client\HttpClient;
use BradSearch\SyncSdk\Client\RetryPolicy;
use BradSearch\SyncSdk\Client\Transport\HttpResponse;
use BradSearch\SyncSdk\Config\SyncConfig;
use BradSearch\SyncSdk\Exceptions\ApiException;
use BradSearch\SyncSdk\Exceptions\TransportException;
use BradSearch\SyncSdk\Tests\Client\Support\FakeSleeper;
use BradSearch\SyncSdk\Tests\Client\Support\FakeTransport;
use PHPUnit\Framework\TestCase;

class HttpClientRetryTest extends TestCase
{
    private FakeSleeper $sleeper;

    protected function setUp(): void
    {
        $this->sleeper = new FakeSleeper();
    }

    /**
     * @param list<HttpResponse|TransportException> $outcomes
     * @return array{HttpClient, FakeTransport}
     */
    private function client(array $outcomes, ?RetryPolicy $policy = null): array
    {
        $config = new SyncConfig(
            baseUrl: 'https://api.example.com',
            authToken: 'token',
            timeout: 120,
            connectTimeout: 7,
            retryPolicy: $policy ?? new RetryPolicy(),
        );
        $transport = new FakeTransport($outcomes);

        return [new HttpClient($config, $transport, $this->sleeper), $transport];
    }

    public function testRetriesIdempotentCallOn502ThenSucceeds(): void
    {
        [$client, $transport] = $this->client([
            new HttpResponse(502, 'bad gateway'),
            new HttpResponse(200, '{"ok":true}'),
        ]);

        $result = $client->get('api/v2/thing');

        $this->assertSame(['ok' => true], $result);
        $this->assertCount(2, $transport->requests);
        $this->assertCount(1, $this->sleeper->delays);
    }

    public function testRetriesIdempotentCallOn429(): void
    {
        [$client, $transport] = $this->client([
            new HttpResponse(429, 'slow down'),
            new HttpResponse(200, '{}'),
        ]);

        $client->put('api/v2/thing', ['a' => 1]);

        $this->assertCount(2, $transport->requests);
    }

    public function testRetriesIdempotentCallOnTransportError(): void
    {
        [$client, $transport] = $this->client([
            new TransportException('cURL error: Connection timed out after 7001 milliseconds'),
            new HttpResponse(200, '{}'),
        ]);

        $client->delete('api/v2/thing');

        $this->assertCount(2, $transport->requests);
    }

    public function testDoesNotRetryOn400(): void
    {
        [$client, $transport] = $this->client([
            new HttpResponse(400, '{"error":"bad request"}'),
        ]);

        try {
            $client->get('api/v2/thing');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(400, $e->statusCode);
            $this->assertSame('{"error":"bad request"}', $e->responseBody);
        }

        $this->assertCount(1, $transport->requests);
        $this->assertSame([], $this->sleeper->delays);
    }

    public function testDoesNotRetryNonIdempotentPostOn503(): void
    {
        [$client, $transport] = $this->client([
            new HttpResponse(503, 'unavailable'),
        ]);

        try {
            $client->post('api/v2/configuration', ['x' => 1]);
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(503, $e->statusCode);
            $this->assertSame('unavailable', $e->responseBody);
        }

        $this->assertCount(1, $transport->requests);
        $this->assertSame([], $this->sleeper->delays);
    }

    public function testDoesNotRetryNonIdempotentPostOnTransportError(): void
    {
        [$client, $transport] = $this->client([
            new TransportException('cURL error: Connection refused'),
        ]);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('cURL error: Connection refused');

        try {
            $client->post('api/v2/configuration/refresh');
        } finally {
            $this->assertCount(1, $transport->requests);
        }
    }

    public function testRetriesPostFlaggedIdempotent(): void
    {
        [$client, $transport] = $this->client([
            new HttpResponse(503, 'unavailable'),
            new HttpResponse(200, '{"status":"success"}'),
        ]);

        $result = $client->post('api/v2/index/x/bulk-operations', ['operations' => []], idempotent: true);

        $this->assertSame(['status' => 'success'], $result);
        $this->assertCount(2, $transport->requests);
    }

    public function testThrowsLastResponseWhenAttemptsExhausted(): void
    {
        [$client, $transport] = $this->client([
            new HttpResponse(503, 'first'),
            new HttpResponse(502, 'second'),
            new HttpResponse(500, '{"error":"third"}'),
        ]);

        try {
            $client->get('api/v2/thing');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(500, $e->statusCode);
            $this->assertSame('{"error":"third"}', $e->responseBody);
        }

        $this->assertCount(3, $transport->requests);
        $this->assertCount(2, $this->sleeper->delays);
    }

    public function testThrowsLastTransportErrorWhenAttemptsExhausted(): void
    {
        [$client, $transport] = $this->client([
            new TransportException('cURL error: first'),
            new TransportException('cURL error: second'),
            new TransportException('cURL error: third'),
        ]);

        try {
            $client->get('api/v2/thing');
            $this->fail('Expected TransportException');
        } catch (TransportException $e) {
            $this->assertSame('cURL error: third', $e->getMessage());
            $this->assertSame(0, $e->statusCode);
            $this->assertNull($e->responseBody);
        }

        $this->assertCount(3, $transport->requests);
    }

    public function testRetryPolicyNoneDisablesRetries(): void
    {
        [$client, $transport] = $this->client([
            new HttpResponse(503, 'unavailable'),
        ], RetryPolicy::none());

        $this->expectException(ApiException::class);

        try {
            $client->get('api/v2/thing');
        } finally {
            $this->assertCount(1, $transport->requests);
        }
    }

    public function testBackoffDelaysStayWithinJitterBounds(): void
    {
        [$client] = $this->client([
            new HttpResponse(503, ''),
            new HttpResponse(503, ''),
            new HttpResponse(503, ''),
            new HttpResponse(503, ''),
            new HttpResponse(503, ''),
        ], new RetryPolicy(maxAttempts: 5));

        try {
            $client->get('api/v2/thing');
        } catch (ApiException) {
            // expected after 5 attempts
        }

        $this->assertCount(4, $this->sleeper->delays);

        // Exponential 1, 2, 4, 8 with equal jitter: each delay lands in [d/2, d].
        $expected = [1.0, 2.0, 4.0, 8.0];
        foreach ($this->sleeper->delays as $i => $delay) {
            $this->assertGreaterThanOrEqual($expected[$i] / 2, $delay, "delay #{$i}");
            $this->assertLessThanOrEqual($expected[$i], $delay, "delay #{$i}");
        }
    }

    public function testRequestCarriesTimeoutsHeadersAndBody(): void
    {
        [$client, $transport] = $this->client([
            new HttpResponse(200, '{}'),
        ]);

        $client->post('api/v2/thing', ['a' => 1]);

        $request = $transport->requests[0];
        $this->assertSame('POST', $request->method);
        $this->assertSame('https://api.example.com/api/v2/thing', $request->url);
        $this->assertSame(120, $request->timeout);
        $this->assertSame(7, $request->connectTimeout);
        $this->assertSame('{"a":1}', $request->body);
        $this->assertContains('Authorization: Bearer token', $request->headers);
        $this->assertContains('Content-Type: application/json', $request->headers);
    }

    public function testGetSendsNoBody(): void
    {
        [$client, $transport] = $this->client([
            new HttpResponse(200, '{}'),
        ]);

        $client->get('api/v2/thing');

        $this->assertNull($transport->requests[0]->body);
    }

    public function testEmptyResponseBodyDecodesToEmptyArray(): void
    {
        [$client] = $this->client([
            new HttpResponse(204, ''),
        ]);

        $this->assertSame([], $client->delete('api/v2/thing'));
    }

    public function testInvalidJsonIsNotRetried(): void
    {
        [$client, $transport] = $this->client([
            new HttpResponse(200, 'not json'),
        ]);

        try {
            $client->get('api/v2/thing');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(200, $e->statusCode);
            $this->assertSame('not json', $e->responseBody);
        }

        $this->assertCount(1, $transport->requests);
    }
}
