<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Response\VersionActivateResponse;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class VersionActivateResponseTest extends TestCase
{
    public function testConstructorWithValidValues(): void
    {
        $response = new VersionActivateResponse(
            previousVersion: 1,
            newVersion: 2,
            aliasName: 'products'
        );

        $this->assertEquals(1, $response->previousVersion);
        $this->assertEquals(2, $response->newVersion);
        $this->assertEquals('products', $response->aliasName);
    }

    public function testExtendsValueObject(): void
    {
        $response = new VersionActivateResponse(1, 2, 'test');

        $this->assertInstanceOf(ValueObject::class, $response);
    }

    public function testImplementsJsonSerializable(): void
    {
        $response = new VersionActivateResponse(1, 2, 'test');

        $this->assertInstanceOf(JsonSerializable::class, $response);
    }

    public function testFromArrayWithValidData(): void
    {
        $data = [
            'previous_version' => 1,
            'new_version' => 2,
            'alias_name' => 'app_products',
        ];

        $response = VersionActivateResponse::fromArray($data);

        $this->assertEquals(1, $response->previousVersion);
        $this->assertEquals(2, $response->newVersion);
        $this->assertEquals('app_products', $response->aliasName);
    }

    public function testFromArrayThrowsOnMissingPreviousVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: previous_version');

        VersionActivateResponse::fromArray([
            'new_version' => 2,
            'alias_name' => 'test',
        ]);
    }

    public function testFromArrayThrowsOnMissingNewVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: new_version');

        VersionActivateResponse::fromArray([
            'previous_version' => 1,
            'alias_name' => 'test',
        ]);
    }

    public function testFromArrayThrowsOnMissingAliasName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: alias_name');

        VersionActivateResponse::fromArray([
            'previous_version' => 1,
            'new_version' => 2,
        ]);
    }

    public function testRejectsEmptyAliasName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('alias_name cannot be empty');

        new VersionActivateResponse(1, 2, '');
    }

    public function testRejectsNegativePreviousVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('previous_version must be non-negative');

        new VersionActivateResponse(-1, 2, 'test');
    }

    public function testRejectsNegativeNewVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('new_version must be non-negative');

        new VersionActivateResponse(1, -2, 'test');
    }

    public function testJsonSerializeReturnsCorrectStructure(): void
    {
        $response = new VersionActivateResponse(1, 2, 'products');

        $expected = [
            'previous_version' => 1,
            'new_version' => 2,
            'alias_name' => 'products',
        ];

        $this->assertEquals($expected, $response->jsonSerialize());
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $response = new VersionActivateResponse(1, 2, 'test');

        $this->assertEquals($response->jsonSerialize(), $response->toArray());
    }

    /**
     * Test parsing of OpenAPI example response.
     */
    public function testMatchesOpenApiExampleResponse(): void
    {
        $apiResponse = [
            'previous_version' => 1,
            'new_version' => 3,
            'alias_name' => 'app_12345_products',
        ];

        $response = VersionActivateResponse::fromArray($apiResponse);

        $this->assertEquals(1, $response->previousVersion);
        $this->assertEquals(3, $response->newVersion);
        $this->assertEquals('app_12345_products', $response->aliasName);
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $response = new VersionActivateResponse(1, 2, 'test');

        $json = json_encode($response);
        $decoded = json_decode($json, true);

        $this->assertEquals(1, $decoded['previous_version']);
        $this->assertEquals(2, $decoded['new_version']);
        $this->assertEquals('test', $decoded['alias_name']);
    }

    public function testAcceptsVersionZero(): void
    {
        $response = new VersionActivateResponse(0, 1, 'test');

        $this->assertEquals(0, $response->previousVersion);
    }

    public function testSameVersionAllowed(): void
    {
        $response = new VersionActivateResponse(2, 2, 'test');

        $this->assertEquals(2, $response->previousVersion);
        $this->assertEquals(2, $response->newVersion);
    }

    public function testRollbackScenario(): void
    {
        $response = new VersionActivateResponse(3, 1, 'test');

        $this->assertEquals(3, $response->previousVersion);
        $this->assertEquals(1, $response->newVersion);
    }
}
