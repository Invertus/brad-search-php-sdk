<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\Exceptions;

use BradSearch\SyncSdk\Exceptions\ApiException;
use BradSearch\SyncSdk\Exceptions\TransportException;
use PHPUnit\Framework\TestCase;

class TransportExceptionTest extends TestCase
{
    public function testIsAnApiExceptionWithStatusZeroAndNoBody(): void
    {
        $e = new TransportException('cURL error: Connection refused', CURLE_COULDNT_CONNECT, connectionFailed: true);

        $this->assertInstanceOf(ApiException::class, $e);
        $this->assertSame(0, $e->statusCode);
        $this->assertNull($e->responseBody);
        $this->assertSame(CURLE_COULDNT_CONNECT, $e->curlErrno);
        $this->assertTrue($e->connectionFailed);
    }

    public function testConnectPhaseErrorsAreConnectionFailures(): void
    {
        foreach ([CURLE_COULDNT_RESOLVE_PROXY, CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_CONNECT, CURLE_SSL_CONNECT_ERROR] as $errno) {
            $this->assertTrue(TransportException::isConnectionFailure($errno, 0.0), "errno {$errno}");
            // Still a connection failure even if cURL reports a connect time (TLS handshake after TCP).
            $this->assertTrue(TransportException::isConnectionFailure($errno, 0.4), "errno {$errno} with connect time");
        }
    }

    public function testTimeoutBeforeConnectingIsAConnectionFailure(): void
    {
        $this->assertTrue(TransportException::isConnectionFailure(CURLE_OPERATION_TIMEDOUT, 0.0));
    }

    public function testTimeoutAfterConnectingIsAReadTimeoutNotAConnectionFailure(): void
    {
        $this->assertFalse(TransportException::isConnectionFailure(CURLE_OPERATION_TIMEDOUT, 0.031));
    }

    public function testOtherErrorsAreNotConnectionFailures(): void
    {
        $this->assertFalse(TransportException::isConnectionFailure(CURLE_RECV_ERROR, 0.02));
        $this->assertFalse(TransportException::isConnectionFailure(CURLE_GOT_NOTHING, 0.02));
        $this->assertFalse(TransportException::isConnectionFailure(0, 0.0));
    }
}
