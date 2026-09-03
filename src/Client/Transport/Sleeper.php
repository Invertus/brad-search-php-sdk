<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Client\Transport;

interface Sleeper
{
    public function sleep(float $seconds): void;
}
