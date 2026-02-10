<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\Exceptions;

/**
 * Exception thrown when an invalid locale is provided.
 */
class InvalidLocaleException extends InvalidArgumentException
{
    public function __construct(
        string $invalidLocale,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            "Invalid locale: '{$invalidLocale}'. Locale must match pattern 'xx-XX' (e.g., 'en-US', 'lt-LT').",
            'locale',
            $invalidLocale,
            $previous
        );
    }
}
