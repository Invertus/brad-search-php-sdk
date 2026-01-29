<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Search;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents a search field configuration matching SearchFieldConfigV2 schema.
 *
 * This immutable ValueObject defines how a specific field should be searched,
 * including its position in the search order, boost multiplier for relevance scoring,
 * and the match mode to use.
 */
final readonly class SearchFieldConfig extends ValueObject
{
    private const MIN_POSITION = 1;
    private const MIN_BOOST_MULTIPLIER = 0.01;
    private const MAX_BOOST_MULTIPLIER = 100.0;

    /**
     * @param string $field The field name to configure for search
     * @param int $position The position in search order (must be >= 1)
     * @param float $boostMultiplier The boost multiplier for relevance scoring (0.01 to 100.0)
     * @param MatchMode $matchMode The match mode to use (defaults to fuzzy)
     */
    public function __construct(
        public string $field,
        public int $position,
        public float $boostMultiplier,
        public MatchMode $matchMode = MatchMode::FUZZY
    ) {
        $this->validateField($field);
        $this->validatePosition($position);
        $this->validateBoostMultiplier($boostMultiplier);
    }

    /**
     * Returns a new instance with a different field name.
     */
    public function withField(string $field): self
    {
        return new self($field, $this->position, $this->boostMultiplier, $this->matchMode);
    }

    /**
     * Returns a new instance with a different position.
     */
    public function withPosition(int $position): self
    {
        return new self($this->field, $position, $this->boostMultiplier, $this->matchMode);
    }

    /**
     * Returns a new instance with a different boost multiplier.
     */
    public function withBoostMultiplier(float $boostMultiplier): self
    {
        return new self($this->field, $this->position, $boostMultiplier, $this->matchMode);
    }

    /**
     * Returns a new instance with a different match mode.
     */
    public function withMatchMode(MatchMode $matchMode): self
    {
        return new self($this->field, $this->position, $this->boostMultiplier, $matchMode);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'field' => $this->field,
            'position' => $this->position,
            'boost_multiplier' => $this->boostMultiplier,
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

    /**
     * Validates that the boost multiplier is within the valid range.
     *
     * @throws InvalidArgumentException If boost multiplier is out of range
     */
    private function validateBoostMultiplier(float $boostMultiplier): void
    {
        if ($boostMultiplier < self::MIN_BOOST_MULTIPLIER || $boostMultiplier > self::MAX_BOOST_MULTIPLIER) {
            throw new InvalidArgumentException(
                sprintf(
                    'Boost multiplier must be between %.2f and %.2f, got %.2f.',
                    self::MIN_BOOST_MULTIPLIER,
                    self::MAX_BOOST_MULTIPLIER,
                    $boostMultiplier
                ),
                'boost_multiplier',
                $boostMultiplier
            );
        }
    }
}
