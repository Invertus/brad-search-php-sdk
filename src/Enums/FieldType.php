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
    case IMAGE_URL = 'image_url';
    case URL = 'url';
} 