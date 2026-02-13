<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Index;

enum SearchAnalysis: string
{
    case FULL = 'full';
    case BASIC = 'basic';
}
