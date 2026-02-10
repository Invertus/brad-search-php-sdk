<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects;

use JsonSerializable;

/**
 * Base abstract class for all V2 ValueObjects.
 *
 * All ValueObjects extending this class must:
 * - Be declared as readonly classes
 * - Use constructor property promotion with readonly properties
 * - Implement jsonSerialize() returning API-compatible structure
 * - Validate input in the constructor
 */
abstract readonly class ValueObject implements JsonSerializable
{
    /**
     * Returns the API-compatible array representation.
     *
     * @return array<string, mixed>
     */
    abstract public function jsonSerialize(): array;

    /**
     * Returns the API-compatible array representation.
     * Alias for jsonSerialize() for explicit usage.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
