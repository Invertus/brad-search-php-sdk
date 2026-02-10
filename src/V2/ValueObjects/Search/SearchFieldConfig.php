<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Search;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents a search field configuration matching SearchFieldConfigV2 schema.
 *
 * This immutable ValueObject defines how a specific field should be searched,
 * including its position in the search order and the match mode to use.
 */
final readonly class SearchFieldConfig extends ValueObject
{
    private const MIN_POSITION = 1;

    /**
     * @param string $field The field name to configure for search
     * @param int $position The position in search order (must be >= 1)
     * @param MatchMode $matchMode The match mode to use (defaults to fuzzy)
     */
    public function __construct(
        public string $field,
        public int $position,
        public MatchMode $matchMode = MatchMode::FUZZY
    ) {
        $this->validateField($field);
        $this->validatePosition($position);
    }

    /**
     * Creates a SearchFieldConfig from an API response array.
     *
     * @param array<string, mixed> $data Raw API response data
     *
     * @return self
     *
     * @throws InvalidArgumentException If required fields are missing or invalid
     */
    public static function fromArray(array $data): self
    {
        self::validateRequiredFields($data, ['field', 'position']);

        $matchMode = MatchMode::FUZZY;
        if (isset($data['match_mode'])) {
            $matchMode = MatchMode::from($data['match_mode']);
        }

        return new self(
            field: (string) $data['field'],
            position: (int) $data['position'],
            matchMode: $matchMode
        );
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

    /**
     * Returns a new instance with a different field name.
     */
    public function withField(string $field): self
    {
        return new self($field, $this->position, $this->matchMode);
    }

    /**
     * Returns a new instance with a different position.
     */
    public function withPosition(int $position): self
    {
        return new self($this->field, $position, $this->matchMode);
    }

    /**
     * Returns a new instance with a different match mode.
     */
    public function withMatchMode(MatchMode $matchMode): self
    {
        return new self($this->field, $this->position, $matchMode);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'field' => $this->field,
            'position' => $this->position,
            'match_mode' => $this->matchMode->value,
        ];
    }

    /**
     * Validates that the field name is not empty.
     *
     * @throws InvalidArgumentException If field is empty
     */
    private function validateField(string $field): void
    {
        if ($field === '') {
            throw new InvalidArgumentException(
                'Field name cannot be empty.',
                'field',
                $field
            );
        }
    }

    /**
     * Validates that the position is at least 1.
     *
     * @throws InvalidArgumentException If position is less than 1
     */
    private function validatePosition(int $position): void
    {
        if ($position < self::MIN_POSITION) {
            throw new InvalidArgumentException(
                sprintf('Position must be at least %d, got %d.', self::MIN_POSITION, $position),
                'position',
                $position
            );
        }
    }
}
