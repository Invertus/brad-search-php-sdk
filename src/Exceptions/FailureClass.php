<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Exceptions;

/**
 * Whether retrying an operation can help.
 *
 * The same three classes are used by the search engine, this SDK and brad-app,
 * so a sync job picks its retry policy without matching message strings.
 */
enum FailureClass: string
{
    /** One product is invalid. Retrying cannot help. */
    case PermanentItem = 'PERMANENT_ITEM';

    /** The tenant configuration is malformed or missing. Retrying cannot help. */
    case PermanentConfig = 'PERMANENT_CONFIG';

    /** Timeout, connection error, 5xx or 429. Retry with backoff. */
    case Transient = 'TRANSIENT';

    public function isPermanent(): bool
    {
        return $this !== self::Transient;
    }
}
