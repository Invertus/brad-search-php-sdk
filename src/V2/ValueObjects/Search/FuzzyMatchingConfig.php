<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Search;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents a fuzzy matching configuration matching FuzzyMatchingConfig schema.
 *
 * This immutable ValueObject defines how fuzzy matching should be applied,
 * including whether it's enabled, the mode to use, and the minimum similarity threshold.
 */
final readonly class FuzzyMatchingConfig extends ValueObject
{
    private const MIN_SIMILARITY_MIN = 0;
    private const MIN_SIMILARITY_MAX = 2;

    /**
     * @param bool $enabled Whether fuzzy matching is enabled
     * @param FuzzyMode $mode The fuzzy matching mode (auto or fixed)
     * @param int $minSimilarity The minimum similarity threshold (0 to 2)
     */
    public function __construct(
        public bool $enabled = true,
        public FuzzyMode $mode = FuzzyMode::AUTO,
        public int $minSimilarity = 2
    ) {
        $this->validateMinSimilarity($minSimilarity);
    }

    /**
     * Creates a FuzzyMatchingConfig from an API response array.
     *
     * @param array<string, mixed> $data Raw API response data
     *
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $enabled = $data['enabled'] ?? true;
        $mode = FuzzyMode::AUTO;
        if (isset($data['mode'])) {
            $mode = FuzzyMode::from($data['mode']);
        }
        $minSimilarity = $data['min_similarity'] ?? 2;

        return new self(
            enabled: (bool) $enabled,
            mode: $mode,
            minSimilarity: (int) $minSimilarity
        );
    }

    /**
     * Returns a new instance with a different enabled value.
     */
    public function withEnabled(bool $enabled): self
    {
        return new self($enabled, $this->mode, $this->minSimilarity);
    }

    /**
     * Returns a new instance with a different mode.
     */
    public function withMode(FuzzyMode $mode): self
    {
        return new self($this->enabled, $mode, $this->minSimilarity);
    }

    /**
     * Returns a new instance with a different minimum similarity.
     */
    public function withMinSimilarity(int $minSimilarity): self
    {
        return new self($this->enabled, $this->mode, $minSimilarity);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'enabled' => $this->enabled,
            'mode' => $this->mode->value,
            'min_similarity' => $this->minSimilarity,
        ];
    }

    /**
     * Validates that the minimum similarity is within the valid range.
     *
     * @throws InvalidArgumentException If min_similarity is out of range
     */
    private function validateMinSimilarity(int $minSimilarity): void
    {
        if ($minSimilarity < self::MIN_SIMILARITY_MIN || $minSimilarity > self::MIN_SIMILARITY_MAX) {
            throw new InvalidArgumentException(
                sprintf(
                    'Minimum similarity must be between %d and %d, got %d.',
                    self::MIN_SIMILARITY_MIN,
                    self::MIN_SIMILARITY_MAX,
                    $minSimilarity
                ),
                'min_similarity',
                $minSimilarity
            );
        }
    }
}
