<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents the scoring configuration for search settings.
 *
 * This immutable ValueObject defines scoring-related configurations including
 * function score settings and minimum score threshold.
 */
final readonly class ScoringConfig extends ValueObject
{
    private const MIN_SCORE_MIN = 0.0;
    private const MIN_SCORE_MAX = 1.0;

    /**
     * @param FunctionScoreConfig|null $functionScore Optional function score configuration
     * @param float|null $minScore Optional minimum score threshold (0.0 to 1.0)
     */
    public function __construct(
        public ?FunctionScoreConfig $functionScore = null,
        public ?float $minScore = null
    ) {
        $this->validateMinScore($minScore);
    }

    /**
     * Returns a new instance with a different function score configuration.
     */
    public function withFunctionScore(?FunctionScoreConfig $functionScore): self
    {
        return new self($functionScore, $this->minScore);
    }

    /**
     * Returns a new instance with a different minimum score.
     */
    public function withMinScore(?float $minScore): self
    {
        return new self($this->functionScore, $minScore);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [];

        if ($this->functionScore !== null) {
            $result['function_score'] = $this->functionScore->jsonSerialize();
        }

        if ($this->minScore !== null) {
            $result['min_score'] = $this->minScore;
        }

        return $result;
    }

    /**
     * Validates that the minimum score is within the valid range.
     *
     * @throws InvalidArgumentException If min_score is out of range
     */
    private function validateMinScore(?float $minScore): void
    {
        if ($minScore === null) {
            return;
        }

        if ($minScore < self::MIN_SCORE_MIN || $minScore > self::MIN_SCORE_MAX) {
            throw new InvalidArgumentException(
                sprintf(
                    'Minimum score must be between %.1f and %.1f, got %.2f.',
                    self::MIN_SCORE_MIN,
                    self::MIN_SCORE_MAX,
                    $minScore
                ),
                'min_score',
                $minScore
            );
        }
    }
}
