<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents a search behavior configuration.
 *
 * This immutable ValueObject defines how a specific field should behave during search,
 * including the search type, subfield targeting, operator, boost factor, and fuzziness settings.
 */
final readonly class SearchBehavior extends ValueObject
{
    private const MIN_BOOST = 0.01;
    private const MAX_BOOST = 100.0;
    private const MIN_FUZZINESS = 0;
    private const MAX_FUZZINESS = 2;
    private const MIN_PREFIX_LENGTH = 0;
    private const MAX_PREFIX_LENGTH = 10;

    /**
     * @param SearchBehaviorType $type The type of search behavior
     * @param string|null $subfield Optional subfield to target (e.g., 'keyword', 'ngram')
     * @param string|null $operator Optional operator for the search (e.g., 'and', 'or')
     * @param float|null $boost Optional boost factor for relevance scoring (0.01 to 100.0)
     * @param int|null $fuzziness Optional fuzziness level for fuzzy matching (0 to 2)
     * @param int|null $prefixLength Optional prefix length for fuzzy matching (0 to 10)
     */
    public function __construct(
        public SearchBehaviorType $type,
        public ?string $subfield = null,
        public ?string $operator = null,
        public ?float $boost = null,
        public ?int $fuzziness = null,
        public ?int $prefixLength = null
    ) {
        $this->validateBoost($boost);
        $this->validateFuzziness($fuzziness);
        $this->validatePrefixLength($prefixLength);
    }

    /**
     * Returns a new instance with a different type.
     */
    public function withType(SearchBehaviorType $type): self
    {
        return new self(
            $type,
            $this->subfield,
            $this->operator,
            $this->boost,
            $this->fuzziness,
            $this->prefixLength
        );
    }

    /**
     * Returns a new instance with a different subfield.
     */
    public function withSubfield(?string $subfield): self
    {
        return new self(
            $this->type,
            $subfield,
            $this->operator,
            $this->boost,
            $this->fuzziness,
            $this->prefixLength
        );
    }

    /**
     * Returns a new instance with a different operator.
     */
    public function withOperator(?string $operator): self
    {
        return new self(
            $this->type,
            $this->subfield,
            $operator,
            $this->boost,
            $this->fuzziness,
            $this->prefixLength
        );
    }

    /**
     * Returns a new instance with a different boost.
     */
    public function withBoost(?float $boost): self
    {
        return new self(
            $this->type,
            $this->subfield,
            $this->operator,
            $boost,
            $this->fuzziness,
            $this->prefixLength
        );
    }

    /**
     * Returns a new instance with a different fuzziness.
     */
    public function withFuzziness(?int $fuzziness): self
    {
        return new self(
            $this->type,
            $this->subfield,
            $this->operator,
            $this->boost,
            $fuzziness,
            $this->prefixLength
        );
    }

    /**
     * Returns a new instance with a different prefix length.
     */
    public function withPrefixLength(?int $prefixLength): self
    {
        return new self(
            $this->type,
            $this->subfield,
            $this->operator,
            $this->boost,
            $this->fuzziness,
            $prefixLength
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [
            'type' => $this->type->value,
        ];

        if ($this->subfield !== null) {
            $result['subfield'] = $this->subfield;
        }

        if ($this->operator !== null) {
            $result['operator'] = $this->operator;
        }

        if ($this->boost !== null) {
            $result['boost'] = $this->boost;
        }

        if ($this->fuzziness !== null) {
            $result['fuzziness'] = $this->fuzziness;
        }

        if ($this->prefixLength !== null) {
            $result['prefix_length'] = $this->prefixLength;
        }

        return $result;
    }

    /**
     * Validates that the boost is within the valid range.
     *
     * @throws InvalidArgumentException If boost is out of range
     */
    private function validateBoost(?float $boost): void
    {
        if ($boost === null) {
            return;
        }

        if ($boost < self::MIN_BOOST || $boost > self::MAX_BOOST) {
            throw new InvalidArgumentException(
                sprintf(
                    'Boost must be between %.2f and %.2f, got %.2f.',
                    self::MIN_BOOST,
                    self::MAX_BOOST,
                    $boost
                ),
                'boost',
                $boost
            );
        }
    }

    /**
     * Validates that the fuzziness is within the valid range.
     *
     * @throws InvalidArgumentException If fuzziness is out of range
     */
    private function validateFuzziness(?int $fuzziness): void
    {
        if ($fuzziness === null) {
            return;
        }

        if ($fuzziness < self::MIN_FUZZINESS || $fuzziness > self::MAX_FUZZINESS) {
            throw new InvalidArgumentException(
                sprintf(
                    'Fuzziness must be between %d and %d, got %d.',
                    self::MIN_FUZZINESS,
                    self::MAX_FUZZINESS,
                    $fuzziness
                ),
                'fuzziness',
                $fuzziness
            );
        }
    }

    /**
     * Validates that the prefix length is within the valid range.
     *
     * @throws InvalidArgumentException If prefix length is out of range
     */
    private function validatePrefixLength(?int $prefixLength): void
    {
        if ($prefixLength === null) {
            return;
        }

        if ($prefixLength < self::MIN_PREFIX_LENGTH || $prefixLength > self::MAX_PREFIX_LENGTH) {
            throw new InvalidArgumentException(
                sprintf(
                    'Prefix length must be between %d and %d, got %d.',
                    self::MIN_PREFIX_LENGTH,
                    self::MAX_PREFIX_LENGTH,
                    $prefixLength
                ),
                'prefix_length',
                $prefixLength
            );
        }
    }
}
