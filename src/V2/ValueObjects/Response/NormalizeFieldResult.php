<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Response;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents a single field's normalization result.
 *
 * This immutable ValueObject contains the result of normalizing a single field:
 * - field: The name of the field that was normalized
 * - status: The result status (success, error)
 * - min: The minimum value found for this field
 * - max: The maximum value found for this field
 * - documentsUpdated: The number of documents updated
 */
final readonly class NormalizeFieldResult extends ValueObject
{
    /**
     * @param string $field The name of the field
     * @param string $status The result status (success, error)
     * @param float $min The minimum value found
     * @param float $max The maximum value found
     * @param int $documentsUpdated The number of documents updated (0 for async responses)
     * @param string|null $taskId The async task ID (present when operation is async)
     */
    public function __construct(
        public string $field,
        public string $status,
        public float $min,
        public float $max,
        public int $documentsUpdated,
        public ?string $taskId = null,
    ) {
        $this->validateNotEmpty($field, 'field');
        $this->validateNotEmpty($status, 'status');
    }

    /**
     * Creates a NormalizeFieldResult from an API response array.
     *
     * @param array<string, mixed> $data Raw API response data
     *
     * @return self
     *
     * @throws InvalidArgumentException If required fields are missing or invalid
     */
    public static function fromArray(array $data): self
    {
        self::validateRequiredFields($data, [
            'field',
            'status',
            'min',
            'max',
            'documents_updated',
        ]);

        return new self(
            field: (string) $data['field'],
            status: (string) $data['status'],
            min: (float) $data['min'],
            max: (float) $data['max'],
            documentsUpdated: (int) $data['documents_updated'],
            taskId: isset($data['task_id']) ? (string) $data['task_id'] : null,
        );
    }

    /**
     * Checks if this field was normalized successfully.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = [
            'field' => $this->field,
            'status' => $this->status,
            'min' => $this->min,
            'max' => $this->max,
            'documents_updated' => $this->documentsUpdated,
        ];

        if ($this->taskId !== null) {
            $data['task_id'] = $this->taskId;
        }

        return $data;
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
