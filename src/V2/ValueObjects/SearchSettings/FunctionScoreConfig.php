<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents a function score configuration for search settings.
 *
 * This immutable ValueObject defines how field values should be used to modify search scores,
 * including the field, modifier function, factor, default value, and boost settings.
 */
final readonly class FunctionScoreConfig extends ValueObject
{
    private const MIN_FACTOR = 0.01;
    private const MAX_FACTOR = 100.0;
    private const MIN_MISSING = 0.0;
    private const MIN_MAX_BOOST = 1.0;
    private const MAX_MAX_BOOST = 1000.0;

    /**
     * @param string $field The field to use for function scoring
     * @param FunctionScoreModifier $modifier The modifier function to apply to the field value
     * @param float $factor The factor to multiply the modified value by
     * @param float $missing The default value when the field is missing
     * @param BoostMode $boostMode How to combine function score with query score
     * @param float|null $maxBoost Optional maximum boost cap (1.0 to 1000.0)
     */
    public function __construct(
        public string $field,
        public FunctionScoreModifier $modifier = FunctionScoreModifier::LOG1P,
        public float $factor = 1.0,
        public float $missing = 1.0,
        public BoostMode $boostMode = BoostMode::MULTIPLY,
        public ?float $maxBoost = null
    ) {
        $this->validateField($field);
        $this->validateFactor($factor);
        $this->validateMissing($missing);
        $this->validateMaxBoost($maxBoost);
    }

    /**
     * Returns a new instance with a different field.
     */
    public function withField(string $field): self
    {
        return new self(
            $field,
            $this->modifier,
            $this->factor,
            $this->missing,
            $this->boostMode,
            $this->maxBoost
        );
    }

    /**
     * Returns a new instance with a different modifier.
     */
    public function withModifier(FunctionScoreModifier $modifier): self
    {
        return new self(
            $this->field,
            $modifier,
            $this->factor,
            $this->missing,
            $this->boostMode,
            $this->maxBoost
        );
    }

    /**
     * Returns a new instance with a different factor.
     */
    public function withFactor(float $factor): self
    {
        return new self(
            $this->field,
            $this->modifier,
            $factor,
            $this->missing,
            $this->boostMode,
            $this->maxBoost
        );
    }

    /**
     * Returns a new instance with a different missing value.
     */
    public function withMissing(float $missing): self
    {
        return new self(
            $this->field,
            $this->modifier,
            $this->factor,
            $missing,
            $this->boostMode,
            $this->maxBoost
        );
    }

    /**
     * Returns a new instance with a different boost mode.
     */
    public function withBoostMode(BoostMode $boostMode): self
    {
        return new self(
            $this->field,
            $this->modifier,
            $this->factor,
            $this->missing,
            $boostMode,
            $this->maxBoost
        );
    }

    /**
     * Returns a new instance with a different max boost.
     */
    public function withMaxBoost(?float $maxBoost): self
    {
        return new self(
            $this->field,
            $this->modifier,
            $this->factor,
            $this->missing,
            $this->boostMode,
            $maxBoost
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [
            'field' => $this->field,
            'modifier' => $this->modifier->value,
            'factor' => $this->factor,
            'missing' => $this->missing,
            'boost_mode' => $this->boostMode->value,
        ];

        if ($this->maxBoost !== null) {
            $result['max_boost'] = $this->maxBoost;
        }

        return $result;
    }

    /**
     * Validates that the field is not empty.
     *
     * @throws InvalidArgumentException If field is empty
     */
    private function validateField(string $field): void
    {
        if ($field === '') {
            throw new InvalidArgumentException(
                'Function score field cannot be empty.',
                'field',
                $field
            );
        }
    }

    /**
     * Validates that the factor is within the valid range.
     *
     * @throws InvalidArgumentException If factor is out of range
     */
    private function validateFactor(float $factor): void
    {
        if ($factor < self::MIN_FACTOR || $factor > self::MAX_FACTOR) {
            throw new InvalidArgumentException(
                sprintf(
                    'Factor must be between %.2f and %.2f, got %.2f.',
                    self::MIN_FACTOR,
                    self::MAX_FACTOR,
                    $factor
                ),
                'factor',
                $factor
            );
        }
    }

    /**
     * Validates that the missing value is non-negative.
     *
     * @throws InvalidArgumentException If missing is negative
     */
    private function validateMissing(float $missing): void
    {
        if ($missing < self::MIN_MISSING) {
            throw new InvalidArgumentException(
                sprintf('Missing value must be at least %.1f, got %.2f.', self::MIN_MISSING, $missing),
                'missing',
                $missing
            );
        }
    }

    /**
     * Validates that the max boost is within the valid range.
     *
     * @throws InvalidArgumentException If max boost is out of range
     */
    private function validateMaxBoost(?float $maxBoost): void
    {
        if ($maxBoost === null) {
            return;
        }

        if ($maxBoost < self::MIN_MAX_BOOST || $maxBoost > self::MAX_MAX_BOOST) {
            throw new InvalidArgumentException(
                sprintf(
                    'Max boost must be between %.1f and %.1f, got %.2f.',
                    self::MIN_MAX_BOOST,
                    self::MAX_MAX_BOOST,
                    $maxBoost
                ),
                'max_boost',
                $maxBoost
            );
        }
    }
}
