<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents the response from search settings API endpoints.
 *
 * This immutable ValueObject contains the response data for search settings operations:
 * - status: Operation status (e.g., "success", "created")
 * - appId: The application ID
 * - message: Optional response message
 */
final readonly class SettingsResponse extends ValueObject
{
    /**
     * @param string $status Operation status
     * @param string $appId Application ID
     * @param string|null $message Optional response message
     */
    public function __construct(
        public string $status,
        public string $appId,
        public ?string $message = null
    ) {
        $this->validateNotEmpty($status, 'status');
        $this->validateNotEmpty($appId, 'app_id');
    }

    /**
     * Creates a SettingsResponse from an API response array.
     *
     * @param array<string, mixed> $data Raw API response data
     *
     * @return self
     *
     * @throws InvalidArgumentException If required fields are missing or invalid
     */
    public static function fromArray(array $data): self
    {
        self::validateRequiredFields($data, ['status', 'app_id']);

        return new self(
            status: (string) $data['status'],
            appId: (string) $data['app_id'],
            message: isset($data['message']) ? (string) $data['message'] : null
        );
    }

    /**
     * Checks if the operation was successful.
     */
    public function isSuccessful(): bool
    {
        return in_array($this->status, ['success', 'created'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [
            'status' => $this->status,
            'app_id' => $this->appId,
        ];

        if ($this->message !== null) {
            $result['message'] = $this->message;
        }

        return $result;
    }

    /**
     * Validates that a string field is not empty.
     *
     * @throws InvalidArgumentException If the value is empty
     */
    private function validateNotEmpty(string $value, string $fieldName): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException(
                sprintf('%s cannot be empty.', $fieldName),
                $fieldName,
                $value
            );
        }
    }

    /**
     * Validates that all required fields are present in the data array.
     *
     * @param array<string, mixed> $data
     * @param array<string> $requiredFields
     *
     * @throws InvalidArgumentException If a required field is missing
     */
    private static function validateRequiredFields(array $data, array $requiredFields): void
    {
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException(
                    sprintf('Missing required field: %s', $field),
                    $field,
                    null
                );
            }
        }
    }
}
