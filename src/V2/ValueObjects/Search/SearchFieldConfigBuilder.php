<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Search;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;

/**
 * Builder for creating SearchFieldConfig ValueObjects with fluent API.
 */
final class SearchFieldConfigBuilder
{
    private ?string $field = null;
    private ?int $position = null;
    private ?float $boostMultiplier = null;
    private MatchMode $matchMode = MatchMode::FUZZY;

    /**
     * Sets the field name.
     */
    public function withField(string $field): self
    {
        $this->field = $field;
        return $this;
    }

    /**
     * Sets the position in search order.
     */
    public function withPosition(int $position): self
    {
        $this->position = $position;
        return $this;
    }

    /**
     * Sets the boost multiplier for relevance scoring.
     */
    public function withBoostMultiplier(float $boostMultiplier): self
    {
        $this->boostMultiplier = $boostMultiplier;
        return $this;
    }

    /**
     * Sets the match mode.
     */
    public function withMatchMode(MatchMode $matchMode): self
    {
        $this->matchMode = $matchMode;
        return $this;
    }

    /**
     * Builds and returns the immutable SearchFieldConfig.
     *
     * @throws InvalidArgumentException If required fields are missing
     */
    public function build(): SearchFieldConfig
    {
        if ($this->field === null) {
            throw new InvalidArgumentException(
                'Field name is required.',
                'field',
                null
            );
        }

        if ($this->position === null) {
            throw new InvalidArgumentException(
                'Position is required.',
                'position',
                null
            );
        }

        if ($this->boostMultiplier === null) {
            throw new InvalidArgumentException(
                'Boost multiplier is required.',
                'boost_multiplier',
                null
            );
        }

        return new SearchFieldConfig(
            $this->field,
            $this->position,
            $this->boostMultiplier,
            $this->matchMode
        );
    }

    /**
     * Resets the builder to its initial state.
     */
    public function reset(): self
    {
        $this->field = null;
        $this->position = null;
        $this->boostMultiplier = null;
        $this->matchMode = MatchMode::FUZZY;
        return $this;
    }
}
