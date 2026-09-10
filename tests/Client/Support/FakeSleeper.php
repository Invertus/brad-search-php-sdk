<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\Client\Support;

use BradSearch\SyncSdk\Client\Transport\Sleeper;

/**
 * Records requested delays instead of sleeping.
 */
final class FakeSleeper implements Sleeper
{
    /** @var list<float> */
    public array $delays = [];

    public function sleep(float $seconds): void
    {
        $this->delays[] = $seconds;
    }
}
