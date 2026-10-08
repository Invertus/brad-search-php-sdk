<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Response\IndexSegmentsResponse;
use PHPUnit\Framework\TestCase;

class IndexSegmentsResponseTest extends TestCase
{
    public function testFromOpenApiExample(): void
    {
        $data = json_decode(
            (string) file_get_contents(__DIR__ . '/../../../fixtures/openapi-examples/index-segments-response.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $response = IndexSegmentsResponse::fromArray($data);

        $this->assertSame('193d520f-6732-49ac-98ba-e26fdcf676a5-v2', $response->index);
        $this->assertSame(12, $response->segments);
        $this->assertSame(1500, $response->deletedDocs);
        $this->assertSame(2147483648, $response->storeBytes);
        $this->assertSame(10737418240, $response->freeDiskBytes);
        $this->assertFalse($response->mergeRunning);
        $this->assertFalse($response->rewriteRunning);
        $this->assertSame($data, $response->jsonSerialize());
    }

    public function testMissingOptionalCountersDefaultToZero(): void
    {
        $response = IndexSegmentsResponse::fromArray(['index' => 'app-v1', 'segments' => 3]);

        $this->assertSame(0, $response->deletedDocs);
        $this->assertFalse($response->mergeRunning);
    }

    public function testMissingSegmentsThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        IndexSegmentsResponse::fromArray(['index' => 'app-v1']);
    }
}
