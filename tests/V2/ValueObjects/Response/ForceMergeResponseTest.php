<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Response\ForceMergeResponse;
use PHPUnit\Framework\TestCase;

class ForceMergeResponseTest extends TestCase
{
    public function testFromOpenApiExample(): void
    {
        $data = json_decode(
            (string) file_get_contents(__DIR__ . '/../../../fixtures/openapi-examples/force-merge-response.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $response = ForceMergeResponse::fromArray($data);

        $this->assertSame('accepted', $response->status);
        $this->assertSame('193d520f-6732-49ac-98ba-e26fdcf676a5-v2', $response->index);
        $this->assertSame('oTUltX4IQMOUUVeiohTt8A:12345', $response->task);
        $this->assertSame(1, $response->maxNumSegments);
        $this->assertSame($data, $response->jsonSerialize());
    }

    public function testMissingMaxNumSegmentsDefaultsToOne(): void
    {
        $response = ForceMergeResponse::fromArray([
            'status' => 'accepted',
            'index' => 'app-v1',
            'task' => 'node:1',
        ]);

        $this->assertSame(1, $response->maxNumSegments);
    }

    public function testMissingIndexThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ForceMergeResponse::fromArray(['status' => 'accepted', 'task' => 'node:1']);
    }

    public function testEmptyTaskThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ForceMergeResponse('accepted', 'app-v1', ' ', 1);
    }

    public function testMaxNumSegmentsBelowOneThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ForceMergeResponse('accepted', 'app-v1', 'node:1', 0);
    }
}
