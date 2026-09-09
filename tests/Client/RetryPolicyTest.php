<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\Client;

use BradSearch\SyncSdk\Client\RetryPolicy;
use BradSearch\SyncSdk\Exceptions\InvalidFieldConfigException;
use PHPUnit\Framework\TestCase;

class RetryPolicyTest extends TestCase
{
    public function testDefaults(): void
    {
        $policy = new RetryPolicy();

        $this->assertSame(3, $policy->maxAttempts);
        $this->assertSame(1.0, $policy->baseDelaySeconds);
        $this->assertSame(8.0, $policy->maxDelaySeconds);
    }

    public function testNoneMeansSingleAttempt(): void
    {
        $this->assertSame(1, RetryPolicy::none()->maxAttempts);
    }

    public function testRetryableStatuses(): void
    {
        $policy = new RetryPolicy();

        $this->assertTrue($policy->isRetryableStatus(429));
        $this->assertTrue($policy->isRetryableStatus(500));
        $this->assertTrue($policy->isRetryableStatus(503));
        $this->assertFalse($policy->isRetryableStatus(400));
        $this->assertFalse($policy->isRetryableStatus(404));
        $this->assertFalse($policy->isRetryableStatus(422));
        $this->assertFalse($policy->isRetryableStatus(200));
    }

    public function testDelayGrowsExponentiallyWithEqualJitterAndCap(): void
    {
        $policy = new RetryPolicy(maxAttempts: 6, baseDelaySeconds: 1.0, maxDelaySeconds: 8.0);
        $ceilings = [1 => 1.0, 2 => 2.0, 3 => 4.0, 4 => 8.0, 5 => 8.0];

        foreach ($ceilings as $attempt => $ceiling) {
            for ($i = 0; $i < 50; $i++) {
                $delay = $policy->delayBeforeRetry($attempt);
                $this->assertGreaterThanOrEqual($ceiling / 2, $delay, "attempt {$attempt}");
                $this->assertLessThanOrEqual($ceiling, $delay, "attempt {$attempt}");
            }
        }
    }

    public function testRejectsZeroAttempts(): void
    {
        $this->expectException(InvalidFieldConfigException::class);

        new RetryPolicy(maxAttempts: 0);
    }

    public function testRejectsCapBelowBase(): void
    {
        $this->expectException(InvalidFieldConfigException::class);

        new RetryPolicy(baseDelaySeconds: 2.0, maxDelaySeconds: 1.0);
    }
}
