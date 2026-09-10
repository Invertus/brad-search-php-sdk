<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\Client\Support;

use BradSearch\SyncSdk\Client\Transport\HttpRequest;
use BradSearch\SyncSdk\Client\Transport\HttpResponse;
use BradSearch\SyncSdk\Client\Transport\Transport;
use BradSearch\SyncSdk\Exceptions\TransportException;
use LogicException;

/**
 * Replays a scripted sequence of outcomes and records every request it received.
 */
final class FakeTransport implements Transport
{
    /** @var list<HttpRequest> */
    public array $requests = [];

    /** @var list<HttpResponse|TransportException> */
    private array $outcomes;

    /**
     * @param list<HttpResponse|TransportException> $outcomes
     */
    public function __construct(array $outcomes)
    {
        $this->outcomes = $outcomes;
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;

        if ($this->outcomes === []) {
            throw new LogicException('FakeTransport received more requests than scripted outcomes');
        }

        $outcome = array_shift($this->outcomes);

        if ($outcome instanceof TransportException) {
            throw $outcome;
        }

        return $outcome;
    }
}
