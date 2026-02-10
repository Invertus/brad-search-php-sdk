<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\Exceptions;

/**
 * Exception thrown when an invalid field type is provided.
 */
class InvalidFieldTypeException extends InvalidArgumentException
{
    public function __construct(
        string $invalidType,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            "Invalid field type: '{$invalidType}'",
            'type',
            $invalidType,
            $previous
        );
    }
}
