<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\SearchSettings;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * One query-time synonym rule: when the customer's query contains `when`,
 * the engine also matches `match` on the configured field `field`.
 *
 * The rule inherits the target field's search types, boosts and typo
 * tolerance on the engine side. Direction is one-way; a "both directions"
 * rule is two SynonymRule instances.
 *
 * Mirrors the `SynonymRule` schema of the search configuration
 * (`synonym_rules[lang][]`).
 */
final readonly class SynonymRule extends ValueObject
{
    public string $when;

    public string $match;

    public string $field;

    /**
     * @param string $when Trigger text, one or more words the query must contain
     * @param string $match Text matched on the field when the rule fires
     * @param string $field Configured query_config field name the rule targets
     *
     * @throws InvalidArgumentException If any part is empty after trimming
     */
    public function __construct(string $when, string $match, string $field)
    {
        $this->when = self::requireText($when, 'when');
        $this->match = self::requireText($match, 'match');
        $this->field = self::requireText($field, 'field');
    }

    /**
     * Creates a SynonymRule from an array (typically from JSON).
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException If a key is missing or not a string
     */
    public static function fromArray(array $data): self
    {
        foreach (['when', 'match', 'field'] as $key) {
            if (!isset($data[$key]) || !is_string($data[$key])) {
                throw new InvalidArgumentException(
                    sprintf('Synonym rule "%s" must be a string.', $key),
                    $key,
                    $data[$key] ?? null
                );
            }
        }

        return new self($data['when'], $data['match'], $data['field']);
    }

    /**
     * @return array{when: string, match: string, field: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'when' => $this->when,
            'match' => $this->match,
            'field' => $this->field,
        ];
    }

    private static function requireText(string $value, string $name): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new InvalidArgumentException(
                sprintf('Synonym rule "%s" cannot be empty.', $name),
                $name,
                $value
            );
        }

        return $trimmed;
    }
}
