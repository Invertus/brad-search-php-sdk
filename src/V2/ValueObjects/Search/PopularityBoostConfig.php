<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Search;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents a popularity boost configuration matching PopularityBoostConfig schema.
 *
 * This immutable ValueObject defines how popularity boosting should be applied,
 * including whether it's enabled, the field to boost by, the algorithm to use,
 * and the maximum boost factor.
 */
final readonly class PopularityBoostConfig extends ValueObject
{
    private const MAX_BOOST_MIN = 1.0;
    private const MAX_BOOST_MAX = 10.0;

    /**
     * @param bool $enabled Whether popularity boosting is enabled
     * @param string $field The field to use for popularity boosting
     * @param BoostAlgorithm $algorithm The boost algorithm to use (logarithmic, linear, or square_root)
     * @param float $maxBoost The maximum boost factor (1.0 to 10.0)
     */
    public function __construct(
        public bool $enabled,
        public string $field,
        public BoostAlgorithm $algorithm = BoostAlgorithm::LOGARITHMIC,
        public float $maxBoost = 2.0
    ) {
        $this->validateMaxBoost($maxBoost);
    }

    /**
     * Creates a PopularityBoostConfig from an API response array.
     *
     * @param array<string, mixed> $data Raw API response data
     *
     * @return self
     *
     * @throws InvalidArgumentException If required fields are missing or invalid
     */
    public static function fromArray(array $data): self
    {
        self::validateRequiredFields($data, ['enabled', 'field']);

        $algorithm = BoostAlgorithm::LOGARITHMIC;
        if (isset($data['algorithm'])) {
            $algorithm = BoostAlgorithm::from($data['algorithm']);
        }
        $maxBoost = $data['max_boost'] ?? 2.0;

        return new self(
            enabled: (bool) $data['enabled'],
            field: (string) $data['field'],
            algorithm: $algorithm,
            maxBoost: (float) $maxBoost
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
     * Returns a new instance with a different enabled value.
     */
    public function withEnabled(bool $enabled): self
    {
        return new self($enabled, $this->field, $this->algorithm, $this->maxBoost);
    }

    /**
     * Returns a new instance with a different field.
     */
    public function withField(string $field): self
    {
        return new self($this->enabled, $field, $this->algorithm, $this->maxBoost);
    }

    /**
     * Returns a new instance with a different algorithm.
     */
    public function withAlgorithm(BoostAlgorithm $algorithm): self
    {
        return new self($this->enabled, $this->field, $algorithm, $this->maxBoost);
    }

    /**
     * Returns a new instance with a different max boost.
     */
    public function withMaxBoost(float $maxBoost): self
    {
        return new self($this->enabled, $this->field, $this->algorithm, $maxBoost);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'enabled' => $this->enabled,
            'field' => $this->field,
            'algorithm' => $this->algorithm->value,
            'max_boost' => $this->maxBoost,
        ];
    }

    /**
     * Validates that the max boost is within the valid range.
     *
     * @throws InvalidArgumentException If max_boost is out of range
     */
    private function validateMaxBoost(float $maxBoost): void
    {
        if ($maxBoost < self::MAX_BOOST_MIN || $maxBoost > self::MAX_BOOST_MAX) {
            throw new InvalidArgumentException(
                sprintf(
                    'Max boost must be between %.1f and %.1f, got %.1f.',
                    self::MAX_BOOST_MIN,
                    self::MAX_BOOST_MAX,
                    $maxBoost
                ),
                'max_boost',
                $maxBoost
            );
        }
    }
}
