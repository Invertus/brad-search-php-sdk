<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Search;

/**
 * Enum representing the supported multi-word operators for query configurations.
 *
 * These values correspond to the OpenAPI QueryConfigurationRequest schema.
 */
enum MultiWordOperator: string
{
    case AND = 'and';
    case OR = 'or';
}
