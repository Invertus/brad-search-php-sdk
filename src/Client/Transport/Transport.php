<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Client\Transport;

use BradSearch\SyncSdk\Exceptions\TransportException;

/**
 * Sends one HTTP request and returns whatever the server answered.
 *
 * Implementations do not interpret status codes; that is the caller's job.
 */
interface Transport
{
    /**
     * @throws TransportException when no HTTP response was obtained (connect failure, timeout, DNS, TLS)
     */
    public function send(HttpRequest $request): HttpResponse;
}
