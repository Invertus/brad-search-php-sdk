<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\Exceptions;

use BradSearch\SyncSdk\Exceptions\ApiException;
use PHPUnit\Framework\TestCase;

class ApiExceptionTest extends TestCase
{
    public function testKeepsTheStatusCodeAndBody(): void
    {
        $e = new ApiException('API request failed with status 502', 502, '{"error":"cannot unmarshal array into Go struct field variant_enrichment"}');

        $this->assertSame(502, $e->statusCode);
        $this->assertSame(502, $e->getCode());
        $this->assertSame('{"error":"cannot unmarshal array into Go struct field variant_enrichment"}', $e->responseBody);
        $this->assertFalse($e->responseBodyTruncated);
    }

    public function testANullBodyStaysNull(): void
    {
        $e = new ApiException('cURL error: Connection refused');

        $this->assertSame(0, $e->statusCode);
        $this->assertNull($e->responseBody);
        $this->assertFalse($e->responseBodyTruncated);
    }

    public function testABodyAtTheLimitIsKeptWhole(): void
    {
        $body = str_repeat('x', ApiException::MAX_RESPONSE_BODY_BYTES);

        $e = new ApiException('API request failed with status 500', 500, $body);

        $this->assertSame($body, $e->responseBody);
        $this->assertFalse($e->responseBodyTruncated);
    }

    public function testAnOversizedBodyIsCutAndFlagged(): void
    {
        $body = str_repeat('a', ApiException::MAX_RESPONSE_BODY_BYTES) . str_repeat('b', 5000);

        $e = new ApiException('API request failed with status 500', 500, $body);

        $this->assertSame(ApiException::MAX_RESPONSE_BODY_BYTES, strlen($e->responseBody));
        $this->assertStringEndsWith('a', $e->responseBody);
        $this->assertTrue($e->responseBodyTruncated);
    }

    public function testTheLimitLeavesRoomForTheConsumersOwnTwoKilobyteLogCut(): void
    {
        $this->assertGreaterThanOrEqual(2048 * 4, ApiException::MAX_RESPONSE_BODY_BYTES);
    }
}
