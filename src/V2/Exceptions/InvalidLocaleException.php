<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\Exceptions;

use BradSearch\SyncSdk\Exceptions\FailureClass;

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
            "Invalid locale: '{$invalidLocale}'. Locale must match pattern 'xx' or 'xx-XX' (e.g., 'en', 'en-US').",
            'locale',
            $invalidLocale,
            $previous
        );
    }

    /**
     * A locale comes from the integration's language configuration, not from a
     * product row. Retrying cannot help, and no single item is at fault.
     */
    public function failureClass(): FailureClass
    {
        return FailureClass::PermanentConfig;
    }
}
