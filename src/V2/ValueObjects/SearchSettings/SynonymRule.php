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
 * (`synonym_rules[lang][]`). Models the engine contract, not brad-app storage;
 * the optional `id` / `pair_id` are carried through so a round trip keeps pairs.
 */
final readonly class SynonymRule extends ValueObject
{
    public string $when;

    public string $match;

    public string $field;

    public bool $enabled;

    public ?string $id;

    public ?string $pairId;

    /**
     * @param string $when Trigger text, one or more words the query must contain
     * @param string $match Text matched on the field when the rule fires
     * @param string $field Configured query_config field name the rule targets
     * @param bool $enabled A rule switched off stays configured but never fires
     * @param string|null $id Optional rule identifier, only written when set
     * @param string|null $pairId Optional identifier shared by the two halves of a two-way pair
     *
     * @throws InvalidArgumentException If any part is empty after trimming
     */
    public function __construct(
        string $when,
        string $match,
        string $field,
        bool $enabled = true,
        ?string $id = null,
        ?string $pairId = null,
    ) {
        $this->when = self::requireText($when, 'when');
        $this->match = self::requireText($match, 'match');
        $this->field = self::requireText($field, 'field');
        $this->enabled = $enabled;
        $this->id = self::optionalText($id);
        $this->pairId = self::optionalText($pairId);
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

        if (isset($data['enabled']) && !is_bool($data['enabled'])) {
            throw new InvalidArgumentException(
                'Synonym rule "enabled" must be a boolean.',
                'enabled',
                $data['enabled']
            );
        }

        foreach (['id', 'pair_id'] as $key) {
            if (isset($data[$key]) && !is_string($data[$key])) {
                throw new InvalidArgumentException(
                    sprintf('Synonym rule "%s" must be a string.', $key),
                    $key,
                    $data[$key]
                );
            }
        }

        return new self(
            $data['when'],
            $data['match'],
            $data['field'],
            $data['enabled'] ?? true,
            $data['id'] ?? null,
            $data['pair_id'] ?? null,
        );
    }

    /**
     * `enabled` is only written when false — the engine treats an absent flag as enabled.
     * `id` and `pair_id` are only written when set.
     *
     * @return array{when: string, match: string, field: string, enabled?: bool, id?: string, pair_id?: string}
     */
    public function jsonSerialize(): array
    {
        $rule = [
            'when' => $this->when,
            'match' => $this->match,
            'field' => $this->field,
        ];

        if (!$this->enabled) {
            $rule['enabled'] = false;
        }
        if ($this->id !== null) {
            $rule['id'] = $this->id;
        }
        if ($this->pairId !== null) {
            $rule['pair_id'] = $this->pairId;
        }

        return $rule;
    }

    private static function requireText(string $value, string $name): string
    {
        $trimmed = self::trimUnicode($value);
        if ($trimmed === '') {
            throw new InvalidArgumentException(
                sprintf('Synonym rule "%s" cannot be empty.', $name),
                $name,
                $value
            );
        }

        return $trimmed;
    }

    private static function optionalText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = self::trimUnicode($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function trimUnicode(string $value): string
    {
        return preg_replace('/^[\s\p{Z}\x{200B}\x{FEFF}]+|[\s\p{Z}\x{200B}\x{FEFF}]+$/u', '', $value) ?? trim($value);
    }
}
