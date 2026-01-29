<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Index;

/**
 * Enum representing the supported field types for index field definitions.
 *
 * These values correspond to the OpenAPI FieldDefinition schema.
 */
enum FieldType: string
{
    case TEXT = 'text';
    case KEYWORD = 'keyword';
    case DOUBLE = 'double';
    case INTEGER = 'integer';
    case BOOLEAN = 'boolean';
    case IMAGE_URL = 'image_url';
    case VARIANTS = 'variants';
}
