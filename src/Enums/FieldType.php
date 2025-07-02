<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Enums;

enum FieldType: string
{
    case TEXT_KEYWORD = 'text_keyword';
    case TEXT = 'text';
    case KEYWORD = 'keyword';
    case HIERARCHY = 'hierarchy';
    case VARIANTS = 'variants';
    case NAME_VALUE_LIST = 'name_value_list';
    case IMAGE_URL = 'image_url';
    case URL = 'url';
    case FLOAT = 'float';
    case INTEGER = 'integer';
}
