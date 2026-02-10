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
            status: 'success',
            oldIndex: '193d520f-6732-49ac-98ba-e26fdcf676a5-v1',
            newIndex: '193d520f-6732-49ac-98ba-e26fdcf676a5-v2',
            aliasName: '193d520f-6732-49ac-98ba-e26fdcf676a5',
            message: 'Alias swapped successfully',
            previousVersion: 1,
            newVersion: 2
        );

        $this->assertEquals('success', $response->status);
        $this->assertEquals('193d520f-6732-49ac-98ba-e26fdcf676a5-v1', $response->oldIndex);
        $this->assertEquals('193d520f-6732-49ac-98ba-e26fdcf676a5-v2', $response->newIndex);
        $this->assertEquals('193d520f-6732-49ac-98ba-e26fdcf676a5', $response->aliasName);
        $this->assertEquals('Alias swapped successfully', $response->message);
        $this->assertEquals(1, $response->previousVersion);
        $this->assertEquals(2, $response->newVersion);
    }

    public function testExtendsValueObject(): void
    {
        $response = new VersionActivateResponse(
            'success',
            'app-v1',
            'app-v2',
            'app',
            'Success',
            1,
            2
        );

        $this->assertInstanceOf(ValueObject::class, $response);
    }

    public function testImplementsJsonSerializable(): void
    {
        $response = new VersionActivateResponse(
            'success',
            'app-v1',
            'app-v2',
            'app',
            'Success',
            1,
            2
        );

        $this->assertInstanceOf(JsonSerializable::class, $response);
    }

    public function testFromArrayWithValidData(): void
    {
        $data = [
            'status' => 'success',
            'old_index' => '193d520f-6732-49ac-98ba-e26fdcf676a5-v1',
            'new_index' => '193d520f-6732-49ac-98ba-e26fdcf676a5-v2',
            'alias_name' => '193d520f-6732-49ac-98ba-e26fdcf676a5',
            'message' => 'Alias swapped successfully',
        ];

        $response = VersionActivateResponse::fromArray($data);

        $this->assertEquals('success', $response->status);
        $this->assertEquals('193d520f-6732-49ac-98ba-e26fdcf676a5-v1', $response->oldIndex);
        $this->assertEquals('193d520f-6732-49ac-98ba-e26fdcf676a5-v2', $response->newIndex);
        $this->assertEquals('193d520f-6732-49ac-98ba-e26fdcf676a5', $response->aliasName);
        $this->assertEquals('Alias swapped successfully', $response->message);
        $this->assertEquals(1, $response->previousVersion);
        $this->assertEquals(2, $response->newVersion);
    }

    public function testFromArrayParsesVersionNumbers(): void
    {
        $data = [
            'status' => 'success',
            'old_index' => 'my-app-v42',
            'new_index' => 'my-app-v43',
            'alias_name' => 'my-app',
            'message' => 'Success',
        ];

        $response = VersionActivateResponse::fromArray($data);

        $this->assertEquals(42, $response->previousVersion);
        $this->assertEquals(43, $response->newVersion);
    }

    public function testFromArrayThrowsOnMissingStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: status');

        VersionActivateResponse::fromArray([
            'old_index' => 'app-v1',
            'new_index' => 'app-v2',
            'alias_name' => 'app',
            'message' => 'Success',
        ]);
    }

    public function testFromArrayThrowsOnMissingOldIndex(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: old_index');

        VersionActivateResponse::fromArray([
            'status' => 'success',
            'new_index' => 'app-v2',
            'alias_name' => 'app',
            'message' => 'Success',
        ]);
    }

    public function testFromArrayThrowsOnMissingNewIndex(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: new_index');

        VersionActivateResponse::fromArray([
            'status' => 'success',
            'old_index' => 'app-v1',
            'alias_name' => 'app',
            'message' => 'Success',
        ]);
    }

    public function testFromArrayThrowsOnMissingAliasName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: alias_name');

        VersionActivateResponse::fromArray([
            'status' => 'success',
            'old_index' => 'app-v1',
            'new_index' => 'app-v2',
            'message' => 'Success',
        ]);
    }

    public function testFromArrayThrowsOnMissingMessage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: message');

        VersionActivateResponse::fromArray([
            'status' => 'success',
            'old_index' => 'app-v1',
            'new_index' => 'app-v2',
            'alias_name' => 'app',
        ]);
    }

    public function testFromArrayThrowsOnInvalidOldIndexFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot parse version from index name');

        VersionActivateResponse::fromArray([
            'status' => 'success',
            'old_index' => 'app-without-version',
            'new_index' => 'app-v2',
            'alias_name' => 'app',
            'message' => 'Success',
        ]);
    }

    public function testFromArrayThrowsOnInvalidNewIndexFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot parse version from index name');

        VersionActivateResponse::fromArray([
            'status' => 'success',
            'old_index' => 'app-v1',
            'new_index' => 'app-no-version',
            'alias_name' => 'app',
            'message' => 'Success',
        ]);
    }

    public function testRejectsEmptyStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('status cannot be empty');

        new VersionActivateResponse('', 'app-v1', 'app-v2', 'app', 'Success', 1, 2);
    }

    public function testRejectsEmptyOldIndex(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('old_index cannot be empty');

        new VersionActivateResponse('success', '', 'app-v2', 'app', 'Success', 1, 2);
    }

    public function testRejectsEmptyNewIndex(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('new_index cannot be empty');

        new VersionActivateResponse('success', 'app-v1', '', 'app', 'Success', 1, 2);
    }

    public function testRejectsEmptyAliasName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('alias_name cannot be empty');

        new VersionActivateResponse('success', 'app-v1', 'app-v2', '', 'Success', 1, 2);
    }

    public function testRejectsEmptyMessage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('message cannot be empty');

        new VersionActivateResponse('success', 'app-v1', 'app-v2', 'app', '', 1, 2);
    }

    public function testRejectsNegativePreviousVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('previous_version must be non-negative');

        new VersionActivateResponse('success', 'app-v1', 'app-v2', 'app', 'Success', -1, 2);
    }

    public function testRejectsNegativeNewVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('new_version must be non-negative');

        new VersionActivateResponse('success', 'app-v1', 'app-v2', 'app', 'Success', 1, -2);
    }

    public function testJsonSerializeReturnsCorrectStructure(): void
    {
        $response = new VersionActivateResponse(
            'success',
            'app-v1',
            'app-v2',
            'app',
            'Success',
            1,
            2
        );

        $expected = [
            'status' => 'success',
            'old_index' => 'app-v1',
            'new_index' => 'app-v2',
            'alias_name' => 'app',
            'message' => 'Success',
            'previous_version' => 1,
            'new_version' => 2,
        ];

        $this->assertEquals($expected, $response->jsonSerialize());
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $response = new VersionActivateResponse(
            'success',
            'app-v1',
            'app-v2',
            'app',
            'Success',
            1,
            2
        );

        $this->assertEquals($response->jsonSerialize(), $response->toArray());
    }

    /**
     * Test parsing of actual Golang API response.
     */
    public function testMatchesGolangApiResponse(): void
    {
        $apiResponse = [
            'status' => 'success',
            'old_index' => '193d520f-6732-49ac-98ba-e26fdcf676a5-v1',
            'new_index' => '193d520f-6732-49ac-98ba-e26fdcf676a5-v2',
            'alias_name' => '193d520f-6732-49ac-98ba-e26fdcf676a5',
            'message' => 'Alias swapped successfully',
        ];

        $response = VersionActivateResponse::fromArray($apiResponse);

        $this->assertEquals('success', $response->status);
        $this->assertEquals(1, $response->previousVersion);
        $this->assertEquals(2, $response->newVersion);
        $this->assertEquals('193d520f-6732-49ac-98ba-e26fdcf676a5', $response->aliasName);
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $response = new VersionActivateResponse(
            'success',
            'app-v1',
            'app-v2',
            'app',
            'Success',
            1,
            2
        );

        $json = json_encode($response);
        $decoded = json_decode($json, true);

        $this->assertEquals('success', $decoded['status']);
        $this->assertEquals('app-v1', $decoded['old_index']);
        $this->assertEquals('app-v2', $decoded['new_index']);
        $this->assertEquals('app', $decoded['alias_name']);
        $this->assertEquals('Success', $decoded['message']);
        $this->assertEquals(1, $decoded['previous_version']);
        $this->assertEquals(2, $decoded['new_version']);
    }

    public function testAcceptsVersionZero(): void
    {
        $response = new VersionActivateResponse(
            'success',
            'app-v0',
            'app-v1',
            'app',
            'Success',
            0,
            1
        );

        $this->assertEquals(0, $response->previousVersion);
    }

    public function testSameVersionAllowed(): void
    {
        $response = new VersionActivateResponse(
            'success',
            'app-v2',
            'app-v2',
            'app',
            'Success',
            2,
            2
        );

        $this->assertEquals(2, $response->previousVersion);
        $this->assertEquals(2, $response->newVersion);
    }

    public function testRollbackScenario(): void
    {
        $data = [
            'status' => 'success',
            'old_index' => 'app-v3',
            'new_index' => 'app-v1',
            'alias_name' => 'app',
            'message' => 'Rolled back to version 1',
        ];

        $response = VersionActivateResponse::fromArray($data);

        $this->assertEquals(3, $response->previousVersion);
        $this->assertEquals(1, $response->newVersion);
    }

    public function testParsesMultiDigitVersions(): void
    {
        $data = [
            'status' => 'success',
            'old_index' => 'app-v99',
            'new_index' => 'app-v100',
            'alias_name' => 'app',
            'message' => 'Success',
        ];

        $response = VersionActivateResponse::fromArray($data);

        $this->assertEquals(99, $response->previousVersion);
        $this->assertEquals(100, $response->newVersion);
    }
}
