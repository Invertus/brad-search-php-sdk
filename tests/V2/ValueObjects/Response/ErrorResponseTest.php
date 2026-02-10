<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\Response\ErrorResponse;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class ErrorResponseTest extends TestCase
{
    public function testConstructorWithValidValues(): void
    {
        $response = new ErrorResponse(
            status: 400,
            error: 'Bad Request'
        );

        $this->assertEquals(400, $response->status);
        $this->assertEquals('Bad Request', $response->error);
        $this->assertNull($response->details);
    }

    public function testConstructorWithStringDetails(): void
    {
        $response = new ErrorResponse(
            status: 400,
            error: 'Validation Error',
            details: 'Field "name" is required'
        );

        $this->assertEquals('Field "name" is required', $response->details);
    }

    public function testConstructorWithArrayDetails(): void
    {
        $details = [
            'field' => 'email',
            'message' => 'Invalid email format',
        ];

        $response = new ErrorResponse(
            status: 422,
            error: 'Validation Error',
            details: $details
        );

        $this->assertEquals($details, $response->details);
    }

    public function testExtendsValueObject(): void
    {
        $response = new ErrorResponse(400, 'Bad Request');

        $this->assertInstanceOf(ValueObject::class, $response);
    }

    public function testImplementsJsonSerializable(): void
    {
        $response = new ErrorResponse(400, 'Bad Request');

        $this->assertInstanceOf(JsonSerializable::class, $response);
    }

    public function testFromArrayWithValidData(): void
    {
        $data = [
            'status' => 404,
            'error' => 'Not Found',
        ];

        $response = ErrorResponse::fromArray($data);

        $this->assertEquals(404, $response->status);
        $this->assertEquals('Not Found', $response->error);
        $this->assertNull($response->details);
    }

    public function testFromArrayWithDetails(): void
    {
        $data = [
            'status' => 400,
            'error' => 'Bad Request',
            'details' => 'Missing required parameter',
        ];

        $response = ErrorResponse::fromArray($data);

        $this->assertEquals('Missing required parameter', $response->details);
    }

    public function testFromArrayThrowsOnMissingStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: status');

        ErrorResponse::fromArray([
            'error' => 'Bad Request',
        ]);
    }

    public function testFromArrayThrowsOnMissingError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: error');

        ErrorResponse::fromArray([
            'status' => 400,
        ]);
    }

    public function testRejectsEmptyError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('error cannot be empty');

        new ErrorResponse(400, '');
    }

    public function testIsClientErrorReturnsTrue(): void
    {
        $response = new ErrorResponse(400, 'Bad Request');
        $this->assertTrue($response->isClientError());

        $response = new ErrorResponse(404, 'Not Found');
        $this->assertTrue($response->isClientError());

        $response = new ErrorResponse(499, 'Client Closed Request');
        $this->assertTrue($response->isClientError());
    }

    public function testIsClientErrorReturnsFalse(): void
    {
        $response = new ErrorResponse(500, 'Internal Server Error');
        $this->assertFalse($response->isClientError());

        $response = new ErrorResponse(200, 'OK');
        $this->assertFalse($response->isClientError());
    }

    public function testIsServerErrorReturnsTrue(): void
    {
        $response = new ErrorResponse(500, 'Internal Server Error');
        $this->assertTrue($response->isServerError());

        $response = new ErrorResponse(503, 'Service Unavailable');
        $this->assertTrue($response->isServerError());

        $response = new ErrorResponse(599, 'Network Connect Timeout');
        $this->assertTrue($response->isServerError());
    }

    public function testIsServerErrorReturnsFalse(): void
    {
        $response = new ErrorResponse(400, 'Bad Request');
        $this->assertFalse($response->isServerError());

        $response = new ErrorResponse(200, 'OK');
        $this->assertFalse($response->isServerError());
    }

    public function testIsNotFoundReturnsTrue(): void
    {
        $response = new ErrorResponse(404, 'Not Found');

        $this->assertTrue($response->isNotFound());
    }

    public function testIsNotFoundReturnsFalse(): void
    {
        $response = new ErrorResponse(400, 'Bad Request');

        $this->assertFalse($response->isNotFound());
    }

    public function testIsUnauthorizedReturnsTrue(): void
    {
        $response = new ErrorResponse(401, 'Unauthorized');

        $this->assertTrue($response->isUnauthorized());
    }

    public function testIsUnauthorizedReturnsFalse(): void
    {
        $response = new ErrorResponse(403, 'Forbidden');

        $this->assertFalse($response->isUnauthorized());
    }

    public function testIsValidationErrorReturnsTrueFor400(): void
    {
        $response = new ErrorResponse(400, 'Bad Request');

        $this->assertTrue($response->isValidationError());
    }

    public function testIsValidationErrorReturnsTrueFor422(): void
    {
        $response = new ErrorResponse(422, 'Unprocessable Entity');

        $this->assertTrue($response->isValidationError());
    }

    public function testIsValidationErrorReturnsFalse(): void
    {
        $response = new ErrorResponse(404, 'Not Found');
        $this->assertFalse($response->isValidationError());

        $response = new ErrorResponse(500, 'Internal Server Error');
        $this->assertFalse($response->isValidationError());
    }

    public function testJsonSerializeReturnsCorrectStructure(): void
    {
        $response = new ErrorResponse(400, 'Bad Request');

        $expected = [
            'status' => 400,
            'error' => 'Bad Request',
        ];

        $this->assertEquals($expected, $response->jsonSerialize());
    }

    public function testJsonSerializeIncludesDetails(): void
    {
        $response = new ErrorResponse(400, 'Bad Request', 'Missing field');

        $serialized = $response->jsonSerialize();

        $this->assertArrayHasKey('details', $serialized);
        $this->assertEquals('Missing field', $serialized['details']);
    }

    public function testJsonSerializeExcludesNullDetails(): void
    {
        $response = new ErrorResponse(400, 'Bad Request');

        $serialized = $response->jsonSerialize();

        $this->assertArrayNotHasKey('details', $serialized);
    }

    public function testToArrayReturnsJsonSerializeOutput(): void
    {
        $response = new ErrorResponse(400, 'Bad Request');

        $this->assertEquals($response->jsonSerialize(), $response->toArray());
    }

    /**
     * Test parsing of OpenAPI example response.
     */
    public function testMatchesOpenApiExampleResponse(): void
    {
        $apiResponse = [
            'status' => 400,
            'error' => 'Validation Error',
            'details' => [
                'field' => 'locales',
                'message' => 'At least one locale is required',
            ],
        ];

        $response = ErrorResponse::fromArray($apiResponse);

        $this->assertEquals(400, $response->status);
        $this->assertEquals('Validation Error', $response->error);
        $this->assertIsArray($response->details);
        $this->assertEquals('locales', $response->details['field']);
        $this->assertTrue($response->isValidationError());
        $this->assertTrue($response->isClientError());
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $response = new ErrorResponse(400, 'Bad Request', 'Details here');

        $json = json_encode($response);
        $decoded = json_decode($json, true);

        $this->assertEquals(400, $decoded['status']);
        $this->assertEquals('Bad Request', $decoded['error']);
        $this->assertEquals('Details here', $decoded['details']);
    }

    public function testAcceptsZeroStatus(): void
    {
        $response = new ErrorResponse(0, 'Unknown Error');

        $this->assertEquals(0, $response->status);
    }

    public function testAcceptsNegativeStatus(): void
    {
        $response = new ErrorResponse(-1, 'Connection Error');

        $this->assertEquals(-1, $response->status);
    }

    public function testComplexDetailsArray(): void
    {
        $details = [
            'errors' => [
                ['field' => 'name', 'message' => 'Required'],
                ['field' => 'price', 'message' => 'Must be positive'],
            ],
            'count' => 2,
        ];

        $response = new ErrorResponse(422, 'Validation Failed', $details);

        $this->assertEquals($details, $response->details);
        $this->assertEquals(2, $response->details['count']);
    }
}
