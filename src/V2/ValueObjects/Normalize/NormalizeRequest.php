<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Normalize;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents a normalization request containing fields to normalize.
 *
 * This immutable ValueObject wraps the fields array for the normalize API endpoint.
 */
final readonly class NormalizeRequest extends ValueObject
{
    /**
     * @param array<int, string> $fields Field names to normalize
     * @param string|null $mode Normalization mode ("linear" or "rank"). Defaults to null (server defaults to "linear").
     */
    public function __construct(
        public array $fields,
        public ?string $mode = null,
    ) {
        $this->validateFields($fields);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = [
            'fields' => $this->fields,
        ];

        if ($this->mode !== null) {
            $data['mode'] = $this->mode;
        }

        return $data;
    }

    /**
     * @param array<int, string> $fields
     * @throws InvalidArgumentException
     */
    private function validateFields(array $fields): void
    {
        if (count($fields) === 0) {
            throw new InvalidArgumentException(
                'At least one field is required.',
                'fields',
                $fields
            );
        }

        foreach ($fields as $index => $field) {
            if (!is_string($field) || trim($field) === '') {
                throw new InvalidArgumentException(
                    sprintf('Field at index %d must be a non-empty string.', $index),
                    'fields',
                    $field
                );
            }
        }
    }
}
